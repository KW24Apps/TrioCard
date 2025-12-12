Você alterou alguma regra no Database? Alguma coisa que possa fazer com que o geocard status não funcione mais?<?php
date_default_timezone_set('America/Sao_Paulo');

// Define nome da aplicação para logs
if (!defined('NOME_APLICACAO')) {
    define('NOME_APLICACAO', 'FLASH_COURIER_TRACKING');
}

require_once __DIR__ . '/../Repositories/DatabaseRepository.php';
require_once __DIR__ . '/../helpers/FlashCourierHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Repositories\DatabaseRepository;
use Helpers\FlashCourierHelper;
use Helpers\LogHelper;
use Helpers\BitrixDealHelper;
use Helpers\BitrixHelper;

class FlashCourierTrackingJob {
    private static $config;

    public static function init() {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/Variaveis.php';
        }
    }

    public static function executar() {
        self::init();
        $bitrixConfig = self::$config['bitrix'];

        LogHelper::gerarTraceId();

        try {
            $databaseRepository = new DatabaseRepository();

            LogHelper::logTrioCardGeral("Iniciando FlashCourierTrackingJob: Consulta e atualização de rastreamento (Produção).", __CLASS__ . '::' . __FUNCTION__, 'INFO');

            // 1. Obter todos os pedidos que precisam de rastreamento da Flash Courier
            $pedidosParaRastreamento = $databaseRepository->getPedidosParaRastreamentoFlashCourier();

            if (empty($pedidosParaRastreamento)) {
                LogHelper::logTrioCardGeral("Nenhum pedido encontrado para rastreamento da Flash Courier.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                exit("Nenhum pedido encontrado para rastreamento da Flash Courier.\n");
            }

            // Agrupar os ARs por lote para consulta na API (até 100 objetos por chamada)
            $arsParaConsulta = [];
            $pedidosPorAr = []; // Mapeia AR para o pedido completo
            foreach ($pedidosParaRastreamento as $pedido) {
                $ar = $pedido['id_rastreio_transportador'];
                if (!empty($ar)) {
                    $arsParaConsulta[] = $ar;
                    $pedidosPorAr[$ar] = $pedido;
                }
            }

            $lotesArs = array_chunk($arsParaConsulta, 100); // Dividir em lotes de 100

            foreach ($lotesArs as $lote) {
                $resultadosRastreamento = FlashCourierHelper::consultarRastreamento($lote);

                if ($resultadosRastreamento) {
                    foreach ($resultadosRastreamento as $hawb) {
                        $ar = isset($hawb['codigoCartao']) ? $hawb['codigoCartao'] : null; // AR/Número de Encomenda Cliente
                        $historico = isset($hawb['historico']) ? $hawb['historico'] : [];
                        $baixa = isset($hawb['baixa']) ? $hawb['baixa'] : [];

                        if ($ar && isset($pedidosPorAr[$ar])) {
                            $pedido = $pedidosPorAr[$ar];
                            $idDealBitrix = $pedido['id_deal_bitrix'];
                            $statusAtualTransportadoraLocal = isset($pedido['status_transportadora']) ? $pedido['status_transportadora'] : 'INDEFINIDO';

                            $mensagemStatus = '';
                            $commentTimeline = '';
                            $dataAtualizacao = (new DateTime())->format('Y-m-d H:i:s');

                            // Lógica para determinar a mensagem de status e o comentário da timeline
                            if (!empty($baixa)) {
                                $ultimoEvento = end($baixa);
                                $recebedor = isset($ultimoEvento['recebedor']) ? $ultimoEvento['recebedor'] : 'N/A';
                                $grauParentesco = isset($ultimoEvento['grauParentesco']) ? $ultimoEvento['grauParentesco'] : 'N/A';
                                $dtBaixa = isset($ultimoEvento['dtBaixa']) ? $ultimoEvento['dtBaixa'] : $dataAtualizacao;
                                $mensagemStatus = "Transportadora: Entrega registrada. Recebedor: {$recebedor} ({$grauParentesco}). Data: {$dtBaixa}";
                                $commentTimeline = "Flash Courier: Pedido ENTREGUE.\n{$mensagemStatus}";
                            } elseif (!empty($historico)) {
                                $ultimoEvento = end($historico);
                                $eventoDesc = isset($ultimoEvento['evento']) ? $ultimoEvento['evento'] : 'Status desconhecido';
                                $ocorrencia = isset($ultimoEvento['ocorrencia']) ? $ultimoEvento['ocorrencia'] : $dataAtualizacao;
                                $local = isset($ultimoEvento['local']) ? $ultimoEvento['local'] : 'N/A';
                                
                                $mensagemStatus = "Transportadora: {$ocorrencia} - {$eventoDesc} - {$local}";
                                $commentTimeline = "Flash Courier: Status atualizado.\n{$mensagemStatus}";
                            } else {
                                $mensagemStatus = "Transportadora: Sem informações de rastreamento detalhadas.";
                                $commentTimeline = "Flash Courier: Sem informações de rastreamento detalhadas.";
                            }

                            // Sempre atualizar o Bitrix se houver informações de rastreamento
                            if (!empty($baixa) || !empty($historico)) {
                                // Atualizar no banco de dados local (opcional, mas mantém a consistência)
                                // Podemos definir um status genérico aqui se necessário, ou apenas a mensagem formatada
                                $databaseRepository->atualizarCampoPedidoIntegracao($idDealBitrix, 'status_transportadora', $mensagemStatus); // Armazena a mensagem formatada
                                $databaseRepository->atualizarCampoPedidoIntegracao($idDealBitrix, 'data_atualizacao_transportadora', $dataAtualizacao);
                                LogHelper::logTrioCardGeral("Status Flash Courier para AR {$ar} (Deal ID: {$idDealBitrix}) atualizado no banco local com a mensagem: '{$mensagemStatus}'.", __CLASS__ . '::' . __FUNCTION__, 'INFO');

                                // Atualizar no Bitrix
                                $campoStatusTransportadoraBitrix = $bitrixConfig['mapeamento_campos_jallcard']['campo_retorno_telenet']; // Campo de status existente
                                $camposBitrix = [$campoStatusTransportadoraBitrix => $mensagemStatus];

                                $resultadoUpdateBitrix = BitrixDealHelper::editarDeal($bitrixConfig['entity_type_id_deal'], $idDealBitrix, $camposBitrix);

                                if ($resultadoUpdateBitrix['success']) {
                                    LogHelper::logBitrix("Deal ID: {$idDealBitrix} atualizado no Bitrix24 com status da transportadora: '{$mensagemStatus}'.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                                } else {
                                    LogHelper::logBitrix("Erro ao atualizar Deal ID: {$idDealBitrix} no Bitrix24 com status da transportadora: " . (isset($resultadoUpdateBitrix['error']) ? $resultadoUpdateBitrix['error'] : 'Erro desconhecido'), __CLASS__ . '::' . __FUNCTION__, 'ERROR');
                                }

                                // Adicionar comentário na Timeline do Deal
                                $entityTypeTimeline = 'dynamic_' . $bitrixConfig['entity_type_id_deal'];
                                $resultadoCommentBitrix = BitrixHelper::adicionarComentarioTimeline($entityTypeTimeline, $idDealBitrix, $commentTimeline, $bitrixConfig['user_id_comments']);

                                if ($resultadoCommentBitrix['success']) {
                                    LogHelper::logBitrix("Comentário de status da transportadora adicionado à timeline do Deal ID: {$idDealBitrix}.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                                } else {
                                    LogHelper::logBitrix("Erro ao adicionar comentário de status da transportadora à timeline do Deal ID: {$idDealBitrix}: " . (isset($resultadoCommentBitrix['error']) ? $resultadoCommentBitrix['error'] : 'Erro desconhecido'), __CLASS__ . '::' . __FUNCTION__, 'ERROR');
                                }
                            } else {
                                LogHelper::logTrioCardGeral("Nenhuma informação de rastreamento detalhada para AR {$ar} (Deal ID: {$idDealBitrix}). Nenhuma atualização no Bitrix.", __CLASS__ . '::' . __FUNCTION__, 'DEBUG');
                            }
                        }
                    }
                } else {
                    LogHelper::logTrioCardGeral("Falha ao consultar rastreamento para o lote de ARs: " . implode(', ', $lote), __CLASS__ . '::' . __FUNCTION__, 'ERROR');
                }
            }

            LogHelper::logTrioCardGeral("FlashCourierTrackingJob finalizado (Produção).", __CLASS__ . '::' . __FUNCTION__, 'INFO');
            exit("FlashCourierTrackingJob finalizado com sucesso.\n");

        } catch (PDOException $e) {
            LogHelper::logTrioCardGeral("Erro de banco de dados no FlashCourierTrackingJob: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'CRITICAL');
            exit("Erro de banco de dados: " . $e->getMessage() . "\n");
        } catch (Exception $e) {
            LogHelper::logTrioCardGeral("Erro geral no FlashCourierTrackingJob: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'CRITICAL');
            exit("Erro geral: " . $e->getMessage() . "\n");
        }
    }
}

FlashCourierTrackingJob::executar();
