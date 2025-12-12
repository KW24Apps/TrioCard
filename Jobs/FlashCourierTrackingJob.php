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
                        $ar = $hawb['codigoCartao'] ?? null; // AR/Número de Encomenda Cliente
                        $historico = $hawb['historico'] ?? [];
                        $baixa = $hawb['baixa'] ?? [];

                        if ($ar && isset($pedidosPorAr[$ar])) {
                            $pedido = $pedidosPorAr[$ar];
                            $idDealBitrix = $pedido['id_deal_bitrix'];
                            $statusAtualTransportadoraLocal = $pedido['status_transportadora'] ?? 'INDEFINIDO';

                            $novoStatusTransportadora = 'INDEFINIDO';
                            $mensagemStatus = '';
                            $commentTimeline = '';
                            $dataAtualizacao = (new DateTime())->format('Y-m-d H:i:s');

                            // Lógica para determinar o status mais recente e a mensagem
                            if (!empty($baixa)) {
                                $ultimoEvento = end($baixa);
                                $novoStatusTransportadora = 'ENTREGUE';
                                $recebedor = $ultimoEvento['recebedor'] ?? 'N/A';
                                $grauParentesco = $ultimoEvento['grauParentesco'] ?? 'N/A';
                                $dtBaixa = $ultimoEvento['dtBaixa'] ?? $dataAtualizacao;
                                $mensagemStatus = "Flash Courier: Entrega registrada. Recebedor: {$recebedor} ({$grauParentesco}). Data: {$dtBaixa}";
                                $commentTimeline = "Flash Courier: Pedido ENTREGUE.\n{$mensagemStatus}";
                            } elseif (!empty($historico)) {
                                $ultimoEvento = end($historico);
                                $eventoId = $ultimoEvento['eventoId'] ?? null;
                                $eventoDesc = $ultimoEvento['evento'] ?? 'Status desconhecido';
                                $ocorrencia = $ultimoEvento['ocorrencia'] ?? $dataAtualizacao;

                                // Mapear eventoId para um status mais genérico
                                switch ($eventoId) {
                                    case '4300': // Entrega registrada
                                    case '5000': // Comprovante registrado
                                        $novoStatusTransportadora = 'ENTREGUE';
                                        break;
                                    case '4100': // Entrega em andamento (na rua)
                                        $novoStatusTransportadora = 'EM_ROTA';
                                        break;
                                    case '4200': // Entrega NAO efetuada
                                    case '4255': // Entrega NAO efetuada (RT)
                                        $novoStatusTransportadora = 'TENTATIVA_FALHA';
                                        break;
                                    case '1400': // Postado - logistica iniciada
                                        $novoStatusTransportadora = 'POSTADO';
                                        break;
                                    case '1500': // HAWB Cancelada - sem fisico
                                        $novoStatusTransportadora = 'CANCELADO';
                                        break;
                                    default:
                                        $novoStatusTransportadora = 'EM_PROCESSAMENTO';
                                        break;
                                }
                                $mensagemStatus = "Flash Courier: {$eventoDesc}. Data: {$ocorrencia}";
                                $commentTimeline = "Flash Courier: Status atualizado para '{$novoStatusTransportadora}'.\n{$mensagemStatus}";
                            } else {
                                $mensagemStatus = "Flash Courier: Sem informações de rastreamento detalhadas.";
                                $commentTimeline = "Flash Courier: Sem informações de rastreamento detalhadas.";
                            }

                            // Verificar se o status mudou ou se é a primeira vez que estamos registrando
                            if ($novoStatusTransportadora !== $statusAtualTransportadoraLocal || empty($statusAtualTransportadoraLocal) || $statusAtualTransportadoraLocal === 'INDEFINIDO') {
                                // Atualizar no banco de dados local
                                $databaseRepository->atualizarCampoPedidoIntegracao($idDealBitrix, 'status_transportadora', $novoStatusTransportadora);
                                $databaseRepository->atualizarCampoPedidoIntegracao($idDealBitrix, 'data_atualizacao_transportadora', $dataAtualizacao);
                                LogHelper::logTrioCardGeral("Status Flash Courier para AR {$ar} (Deal ID: {$idDealBitrix}) atualizado para '{$novoStatusTransportadora}' no banco local (anterior: '{$statusAtualTransportadoraLocal}').", __CLASS__ . '::' . __FUNCTION__, 'INFO');

                                // Atualizar no Bitrix
                                $campoStatusTransportadoraBitrix = $bitrixConfig['mapeamento_campos_jallcard']['campo_retorno_telenet']; // Reutilizando o campo de retorno da Telenet para o status geral
                                $camposBitrix = [$campoStatusTransportadoraBitrix => $mensagemStatus];

                                $resultadoUpdateBitrix = BitrixDealHelper::editarDeal($bitrixConfig['entity_type_id_deal'], $idDealBitrix, $camposBitrix);

                                if ($resultadoUpdateBitrix['success']) {
                                    LogHelper::logBitrix("Deal ID: {$idDealBitrix} atualizado no Bitrix24 com status da transportadora: '{$mensagemStatus}'.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                                } else {
                                    LogHelper::logBitrix("Erro ao atualizar Deal ID: {$idDealBitrix} no Bitrix24 com status da transportadora: " . ($resultadoUpdateBitrix['error'] ?? 'Erro desconhecido'), __CLASS__ . '::' . __FUNCTION__, 'ERROR');
                                }

                                // Adicionar comentário na Timeline do Deal
                                $entityTypeTimeline = 'dynamic_' . $bitrixConfig['entity_type_id_deal'];
                                $resultadoCommentBitrix = BitrixHelper::adicionarComentarioTimeline($entityTypeTimeline, $idDealBitrix, $commentTimeline, $bitrixConfig['user_id_comments']);

                                if ($resultadoCommentBitrix['success']) {
                                    LogHelper::logBitrix("Comentário de status da transportadora adicionado à timeline do Deal ID: {$idDealBitrix}.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                                } else {
                                    LogHelper::logBitrix("Erro ao adicionar comentário de status da transportadora à timeline do Deal ID: {$idDealBitrix}: " . ($resultadoCommentBitrix['error'] ?? 'Erro desconhecido'), __CLASS__ . '::' . __FUNCTION__, 'ERROR');
                                }
                            } else {
                                LogHelper::logTrioCardGeral("Status Flash Courier para AR {$ar} (Deal ID: {$idDealBitrix}) não mudou ('{$novoStatusTransportadora}'). Nenhuma atualização no Bitrix.", __CLASS__ . '::' . __FUNCTION__, 'DEBUG');
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
