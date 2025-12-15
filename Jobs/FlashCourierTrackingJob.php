<?php
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

            $campoStatusTransportadoraBitrix = $bitrixConfig['mapeamento_campos_jallcard']['campo_retorno_telenet']; // Campo de status existente
            $entityTypeTimeline = 'dynamic_' . $bitrixConfig['entity_type_id_deal'];
            $authorIdComments = $bitrixConfig['user_id_comments'];

            foreach ($pedidosParaRastreamento as $pedido) {
                $ar = $pedido['id_rastreio_transportador'];
                $idDealBitrix = $pedido['id_deal_bitrix'];
                $statusAtualTransportadoraLocal = isset($pedido['status_transportadora']) ? $pedido['status_transportadora'] : 'INDEFINIDO';

                if (empty($ar)) {
                    LogHelper::logTrioCardGeral("AR vazio para Deal ID: {$idDealBitrix}. Ignorando rastreamento.", __CLASS__ . '::' . __FUNCTION__, 'WARNING');
                    continue;
                }

                LogHelper::logTrioCardGeral("Consultando rastreamento para AR: {$ar} (Deal ID: {$idDealBitrix}).", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                
                try {
                    // Consulta individual na API
                    $resultadosRastreamento = FlashCourierHelper::consultarRastreamento([$ar]);

                    if ($resultadosRastreamento && !empty($resultadosRastreamento[0])) {
                        $hawb = $resultadosRastreamento[0];
                        $historico = isset($hawb['historico']) ? $hawb['historico'] : [];
                        $baixa = isset($hawb['baixa']) ? $hawb['baixa'] : [];

                        $mensagemStatus = '';
                        $commentTimeline = '';
                        $dataAtualizacao = (new DateTime())->format('Y-m-d H:i:s');
                        $novoStatusLocal = $statusAtualTransportadoraLocal; // Assume que o status não muda

                        // Lógica para determinar a mensagem de status e o comentário da timeline
                        if (!empty($baixa)) {
                            $ultimoEvento = end($baixa);
                            $recebedor = isset($ultimoEvento['recebedor']) ? $ultimoEvento['recebedor'] : 'N/A';
                            $grauParentesco = isset($ultimoEvento['grauParentesco']) ? $ultimoEvento['grauParentesco'] : 'N/A';
                            $dtBaixa = isset($ultimoEvento['dtBaixa']) ? $ultimoEvento['dtBaixa'] : $dataAtualizacao;
                            $mensagemStatus = "Transportadora: Entrega registrada. Recebedor: {$recebedor} ({$grauParentesco}). Data: {$dtBaixa}";
                            $commentTimeline = "Flash Courier: Pedido ENTREGUE.\n{$mensagemStatus}";
                            $novoStatusLocal = 'ENTREGUE'; // Marcar como entregue no banco local
                        } elseif (!empty($historico)) {
                            $ultimoEvento = end($historico);
                            $eventoDesc = isset($ultimoEvento['evento']) ? $ultimoEvento['evento'] : 'Status desconhecido';
                            $ocorrencia = isset($ultimoEvento['ocorrencia']) ? $ultimoEvento['ocorrencia'] : $dataAtualizacao;
                            $local = isset($ultimoEvento['local']) ? $ultimoEvento['local'] : 'N/A';
                            
                            $mensagemStatus = "Transportadora: {$ocorrencia} - {$eventoDesc} - {$local}";
                            $commentTimeline = "Flash Courier: Status atualizado.\n{$mensagemStatus}";
                            $novoStatusLocal = $mensagemStatus; // Salva a mensagem completa como status
                        } else {
                            $mensagemStatus = "Transportadora: Sem informações de rastreamento detalhadas.";
                            $commentTimeline = "Flash Courier: Sem informações de rastreamento detalhadas.";
                            $novoStatusLocal = $mensagemStatus;
                        }

                        // Lógica para determinar se a atualização deve ocorrer
                        $deveAtualizar = false;

                        // Se o status local for indefinido ou diferente do novo status
                        if ($novoStatusLocal !== $statusAtualTransportadoraLocal) {
                            $deveAtualizar = true;
                        }

                        // Se houver um novo status e a atualização for necessária
                        if ($deveAtualizar) {
                            // Atualizar no banco de dados local
                            $databaseRepository->atualizarCampoPedidoIntegracao($idDealBitrix, 'status_transportadora', $novoStatusLocal);
                            LogHelper::logTrioCardGeral("Status Flash Courier para AR {$ar} (Deal ID: {$idDealBitrix}) atualizado no banco local para: '{$novoStatusLocal}'.", __CLASS__ . '::' . __FUNCTION__, 'INFO');

                            // Atualizar no Bitrix
                            $camposBitrix = [$campoStatusTransportadoraBitrix => $mensagemStatus];
                            $resultadoUpdateBitrix = BitrixDealHelper::editarDeal($bitrixConfig['entity_type_id_deal'], $idDealBitrix, $camposBitrix);

                            if ($resultadoUpdateBitrix['success']) {
                                LogHelper::logBitrix("Deal ID: {$idDealBitrix} atualizado no Bitrix24 com status da transportadora: '{$mensagemStatus}'.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                            } else {
                                LogHelper::logBitrix("Erro ao atualizar Deal ID: {$idDealBitrix} no Bitrix24 com status: " . ($resultadoUpdateBitrix['error'] ?? 'Erro desconhecido'), __CLASS__ . '::' . __FUNCTION__, 'ERROR');
                            }

                            // Adicionar comentário na Timeline
                            $resultadoCommentBitrix = BitrixHelper::adicionarComentarioTimeline($entityTypeTimeline, $idDealBitrix, $commentTimeline, $authorIdComments);

                            if ($resultadoCommentBitrix['success']) {
                                LogHelper::logBitrix("Comentário de rastreamento adicionado à timeline do Deal ID: {$idDealBitrix}.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                            } else {
                                LogHelper::logBitrix("Erro ao adicionar comentário de rastreamento à timeline do Deal ID: {$idDealBitrix}: " . ($resultadoCommentBitrix['error'] ?? 'Erro desconhecido'), __CLASS__ . '::' . __FUNCTION__, 'ERROR');
                            }
                        } else {
                            LogHelper::logTrioCardGeral("Status Flash Courier para AR {$ar} (Deal ID: {$idDealBitrix}) não mudou ou não é um avanço. Nenhuma atualização no Bitrix.", __CLASS__ . '::' . __FUNCTION__, 'DEBUG');
                        }
                    } else {
                        LogHelper::logTrioCardGeral("Nenhuma informação de rastreamento detalhada para AR {$ar} (Deal ID: {$idDealBitrix}). Nenhuma atualização no Bitrix.", __CLASS__ . '::' . __FUNCTION__, 'WARNING');
                    }
                } catch (Exception $e) {
                    LogHelper::logTrioCardGeral("Erro ao consultar rastreamento para AR {$ar} (Deal ID: {$idDealBitrix}): " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'ERROR');
                }

                // Pausa de 4 segundos entre as consultas
                sleep(4);
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
