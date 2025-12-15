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

            $dealsParaAtualizar = [];
            $comentariosParaAdicionar = [];
            $campoStatusTransportadoraBitrix = $bitrixConfig['mapeamento_campos_jallcard']['campo_retorno_telenet']; // Campo de status existente
            $entityTypeTimeline = 'dynamic_' . $bitrixConfig['entity_type_id_deal'];
            $authorIdComments = $bitrixConfig['user_id_comments'];

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
                            // $statusAtualTransportadoraLocal = isset($pedido['status_transportadora']) ? $pedido['status_transportadora'] : 'INDEFINIDO'; // Não usado diretamente

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

                            // Coletar dados para atualização em lote
                            if (!empty($baixa) || !empty($historico)) {
                                // Atualizar no banco de dados local
                                $databaseRepository->atualizarCampoPedidoIntegracao($idDealBitrix, 'status_transportadora', $mensagemStatus);
                                LogHelper::logTrioCardGeral("Status Flash Courier para AR {$ar} (Deal ID: {$idDealBitrix}) atualizado no banco local com a mensagem: '{$mensagemStatus}'.", __CLASS__ . '::' . __FUNCTION__, 'INFO');

                                // Adicionar à lista de deals para atualização em lote no Bitrix
                                $dealsParaAtualizar[] = [
                                    'id' => $idDealBitrix,
                                    'fields' => [$campoStatusTransportadoraBitrix => $mensagemStatus]
                                ];

                                // Adicionar à lista de comentários para adição em lote na Timeline
                                $comentariosParaAdicionar[] = [
                                    'entityType' => $entityTypeTimeline,
                                    'entityId' => $idDealBitrix,
                                    'comment' => $commentTimeline,
                                    'authorId' => $authorIdComments
                                ];
                            } else {
                                LogHelper::logTrioCardGeral("Nenhuma informação de rastreamento detalhada para AR {$ar} (Deal ID: {$idDealBitrix}). Nenhuma atualização no Bitrix.", __CLASS__ . '::' . __FUNCTION__, 'DEBUG');
                            }
                        }
                    }
                } else {
                    LogHelper::logTrioCardGeral("Falha ao consultar rastreamento para o lote de ARs: " . implode(', ', $lote), __CLASS__ . '::' . __FUNCTION__, 'ERROR');
                }
            }

            // Executar atualizações em lote no Bitrix
            if (!empty($dealsParaAtualizar)) {
                LogHelper::logTrioCardGeral("Iniciando atualização em lote de " . count($dealsParaAtualizar) . " deals no Bitrix24.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                $resultadoUpdateBitrixLote = BitrixDealHelper::editarDealsEmLote($bitrixConfig['entity_type_id_deal'], $dealsParaAtualizar);

                if ($resultadoUpdateBitrixLote['status'] === 'sucesso') {
                    LogHelper::logBitrix("Atualização em lote de deals no Bitrix24 concluída: " . $resultadoUpdateBitrixLote['mensagem'], __CLASS__ . '::' . __FUNCTION__, 'INFO');
                } else {
                    LogHelper::logBitrix("Erro na atualização em lote de deals no Bitrix24: " . $resultadoUpdateBitrixLote['mensagem'], __CLASS__ . '::' . __FUNCTION__, 'ERROR');
                }
            }

            // Executar adição de comentários em lote no Bitrix
            if (!empty($comentariosParaAdicionar)) {
                LogHelper::logTrioCardGeral("Iniciando adição em lote de " . count($comentariosParaAdicionar) . " comentários na timeline do Bitrix24.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                $resultadoCommentBitrixLote = BitrixHelper::adicionarComentariosTimelineEmLote($comentariosParaAdicionar);

                if ($resultadoCommentBitrixLote['status'] === 'sucesso') {
                    LogHelper::logBitrix("Adição em lote de comentários na timeline do Bitrix24 concluída: " . $resultadoCommentBitrixLote['mensagem'], __CLASS__ . '::' . __FUNCTION__, 'INFO');
                } else {
                    LogHelper::logBitrix("Erro na adição em lote de comentários na timeline do Bitrix24: " . $resultadoCommentBitrixLote['mensagem'], __CLASS__ . '::' . __FUNCTION__, 'ERROR');
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
