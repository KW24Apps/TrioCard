<?php
date_default_timezone_set('America/Sao_Paulo');

// Define nome da aplicação para logs
if (!defined('NOME_APLICACAO')) {
    define('NOME_APLICACAO', 'JALLCARD_STATUS_UPDATER_TEST');
}

require_once __DIR__ . '/../Repositories/DatabaseRepository.php';
require_once __DIR__ . '/../helpers/JallCardHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Repositories\DatabaseRepository;
use Helpers\JallCardHelper;
use Helpers\LogHelper;
use Helpers\BitrixDealHelper;
use Helpers\BitrixHelper;
// As classes DateTime, PDOException e Exception são globais e não precisam de 'use' statement.

// Gera traceId para toda execução do job
LogHelper::gerarTraceId();

try {
    $databaseRepository = new DatabaseRepository();

    LogHelper::logBitrixHelpers("Iniciando JallCardStatusUpdateJobTest: Atualização de status de pedido de teste.", 'JallCardStatusUpdateJobTest::executar');

    // Dados de teste fornecidos pelo usuário
    $pedidosVinculados = [
        [
            'op_jallcard' => 'PLE2500138',
            'id_deal_bitrix' => '60',
            'status_jallcard' => 'INDEFINIDO' // Pode ser qualquer status inicial para teste
        ]
    ];
    LogHelper::logBitrixHelpers("Pedidos de teste carregados: " . count($pedidosVinculados) . " itens.", 'JallCardStatusUpdateJobTest::executar');

    if (empty($pedidosVinculados)) {
        LogHelper::logBitrixHelpers("Nenhum pedido de teste encontrado para atualização de status.", 'JallCardStatusUpdateJobTest::executar');
        exit("Nenhum pedido de teste encontrado para atualização de status.\n");
    }

    foreach ($pedidosVinculados as $pedido) {
        $opJallCard = $pedido['op_jallcard'];
        $idDealBitrix = $pedido['id_deal_bitrix'];
        $statusAtualLocal = $pedido['status_jallcard'] ?? 'INDEFINIDO';

        LogHelper::logBitrixHelpers("Processando Deal ID: {$idDealBitrix}, OP JallCard: {$opJallCard}. Status local: {$statusAtualLocal}", 'JallCardStatusUpdateJobTest::executar');

        if (empty($opJallCard)) {
            LogHelper::logBitrixHelpers("OP JallCard vazia para Deal ID: {$idDealBitrix}. Ignorando atualização de status.", 'JallCardStatusUpdateJobTest::executar');
            continue;
        }

        $ordemProducao = JallCardHelper::getOrdemProducao($opJallCard);
        LogHelper::logBitrixHelpers("Resposta da API JallCard para OP {$opJallCard}: " . json_encode($ordemProducao), 'JallCardStatusUpdateJobTest::executar');

        if ($ordemProducao && isset($ordemProducao['status'])) {
            $novoStatusJallCard = $ordemProducao['status'];
            $dataStatus = '';
            $statusDetalhado = '';
            $mensagemStatus = '';
            $commentTimeline = '';
            $idRastreamento = null;
            $transportadora = null;

            if (!empty($ordemProducao['producao']['expedicao'])) {
                $statusDetalhado = 'Expedição';
                $dataStatus = (new DateTime($ordemProducao['producao']['expedicao']))->format('d/m/Y H:i:s');
                
                LogHelper::logBitrixHelpers("Status é Expedição para OP {$opJallCard}. Buscando documentos para ID de rastreamento.", 'JallCardStatusUpdateJobTest::executar');
                $documentos = JallCardHelper::getDocumentosByOp($opJallCard, true);
                LogHelper::logBitrixHelpers("Resposta da API JallCard /documentos para OP {$opJallCard}: " . json_encode($documentos), 'JallCardStatusUpdateJobTest::executar');

                if (!empty($documentos) && isset($documentos[0])) {
                    $doc = $documentos[0];
                    LogHelper::logBitrixHelpers("Detalhes do documento para OP {$opJallCard}: Entregadora: " . ($doc['entregadora'] ?? 'N/A') . ", CodigoPostagem: " . ($doc['codigoPostagem'] ?? 'N/A'), 'JallCardStatusUpdateJobTest::executar');
                    $transportadora = $doc['entregadora'] ?? null;
                    $idRastreamento = $doc['codigoPostagem'] ?? null;

                    if ($transportadora && $idRastreamento) {
                        $mensagemStatus = "JallCard: Recolhido pela transportadora";
                        $commentTimeline = "JallCard: Status finalizado: {$transportadora} - {$idRastreamento}.\nData: {$dataStatus}";
                        LogHelper::logBitrixHelpers("ID de rastreamento encontrado para OP {$opJallCard}: Transportadora: {$transportadora}, ID: {$idRastreamento}.", 'JallCardStatusUpdateJobTest::executar');
                    } else {
                        $mensagemStatus = "JallCard: Aguardando expedição sem transportadora";
                        $commentTimeline = "JallCard: Status atualizado para 'Expedição'.\nData: {$dataStatus}";
                        LogHelper::logBitrixHelpers("Transportadora ou ID de rastreamento não encontrados nos documentos para OP {$opJallCard}. Usando mensagem de expedição padrão.", 'JallCardStatusUpdateJobTest::executar');
                    }
                } else {
                    $mensagemStatus = "JallCard: Aguardando expedição sem transportadora";
                    $commentTimeline = "JallCard: Status atualizado para 'Expedição'.\nData: {$dataStatus}";
                    LogHelper::logBitrixHelpers("Nenhum documento encontrado para OP {$opJallCard} para buscar ID de rastreamento. Usando mensagem de expedição padrão.", 'JallCardStatusUpdateJobTest::executar');
                }

            } elseif (!empty($ordemProducao['producao']['preExpedicao'])) {
                $statusDetalhado = 'Pré-Expedição';
                $dataStatus = (new DateTime($ordemProducao['producao']['preExpedicao']))->format('d/m/Y H:i:s');
                $mensagemStatus = "JallCard: Aguardando recolhimento da transportadora";
                $commentTimeline = "JallCard: Aguardando recolhimento da transportadora.\nData: {$dataStatus}";
            } elseif (!empty($ordemProducao['producao']['gravacao'])) {
                $statusDetalhado = 'Gravação';
                $dataStatus = (new DateTime($ordemProducao['producao']['gravacao']))->format('d/m/Y H:i:s');
                $mensagemStatus = "JallCard: Cartões enviados para gravação";
                $commentTimeline = "JallCard: Cartões enviados para gravação.\nData: {$dataStatus}";
            } else {
                $statusDetalhado = $novoStatusJallCard;
                $mensagemStatus = "JallCard: Outros status";
                $commentTimeline = "JallCard: Status atualizado para '{$statusDetalhado}'.";
            }

            if ($novoStatusJallCard !== $statusAtualLocal) {
                // No arquivo de teste, não vamos atualizar o banco de dados real.
                // Apenas logamos a tentativa de atualização.
                LogHelper::logBitrixHelpers("Simulando atualização de status no banco de dados local para OP {$opJallCard} para '{$novoStatusJallCard}'.", 'JallCardStatusUpdateJobTest::executar');

                $campoRetornoBitrix = 'ufCrm8_1756758530';
                $camposBitrix = [$campoRetornoBitrix => $mensagemStatus];

                $campoNomeTransportadoraBitrix = 'ufCrm8_1758216263'; // UF do campo Nome da Transportadora no Bitrix (corrigido)
                $campoIdRastreamentoBitrix = 'ufCrm8_1758216333';
                $campoIdRastreamentoDB = 'id_rastreamento_flash_pegasus';

                if ($transportadora && !empty($campoNomeTransportadoraBitrix)) {
                    $camposBitrix[$campoNomeTransportadoraBitrix] = $transportadora;
                    LogHelper::logBitrixHelpers("Adicionando Nome da Transportadora '{$transportadora}' ao Bitrix para Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJobTest::executar');
                }
                if ($idRastreamento && !empty($campoIdRastreamentoBitrix)) {
                    $camposBitrix[$campoIdRastreamentoBitrix] = $idRastreamento;
                    LogHelper::logBitrixHelpers("Adicionando ID de rastreamento '{$idRastreamento}' ao Bitrix para Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJobTest::executar');
                }

                // TODO: Salvar ID de rastreamento no banco de dados local (pedidos_integracao) - Apenas logar no teste
                if ($idRastreamento && !empty($campoIdRastreamentoDB)) {
                    LogHelper::logBitrixHelpers("Simulando salvamento do ID de rastreamento '{$idRastreamento}' no banco de dados local para Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJobTest::executar');
                }

                $resultadoUpdateBitrix = BitrixDealHelper::editarDeal(1042, $idDealBitrix, $camposBitrix);

                if ($resultadoUpdateBitrix['success']) {
                    LogHelper::logBitrixHelpers("Deal ID: {$idDealBitrix} atualizado no Bitrix24 com mensagem de status e/ou rastreamento: '{$mensagemStatus}'.", 'JallCardStatusUpdateJobTest::executar');
                } else {
                    LogHelper::logBitrixHelpers("Erro ao atualizar Deal ID: {$idDealBitrix} no Bitrix24 com status: " . ($resultadoUpdateBitrix['error'] ?? 'Erro desconhecido'), 'JallCardStatusUpdateJobTest::executar');
                }

                $entityTypeTimeline = 'dynamic_1042';
                $resultadoCommentBitrix = BitrixHelper::adicionarComentarioTimeline($entityTypeTimeline, $idDealBitrix, $commentTimeline, 36);

                if ($resultadoCommentBitrix['success']) {
                    LogHelper::logBitrixHelpers("Comentário de status adicionado à timeline do Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJobTest::executar');
                } else {
                    LogHelper::logBitrixHelpers("Erro ao adicionar comentário de status à timeline do Deal ID: {$idDealBitrix}: " . ($resultadoCommentBitrix['error'] ?? 'Erro desconhecido'), 'JallCardStatusUpdateJobTest::executar');
                }
            } else {
                LogHelper::logBitrixHelpers("Status JallCard para OP {$opJallCard} não mudou ('{$novoStatusJallCard}'). Nenhuma atualização no Bitrix.", 'JallCardStatusUpdateJobTest::executar');
            }
        } else {
            LogHelper::logBitrixHelpers("Não foi possível obter status ou a resposta da API JallCard está incompleta para OP: {$opJallCard}.", 'JallCardStatusUpdateJobTest::executar');
        }
    }

    LogHelper::logBitrixHelpers("JallCardStatusUpdateJobTest finalizado.", 'JallCardStatusUpdateJobTest::executar');
    exit("JallCardStatusUpdateJobTest finalizado com sucesso.\n");

} catch (PDOException $e) {
    LogHelper::logBitrixHelpers("Erro de banco de dados no JallCardStatusUpdateJobTest: " . $e->getMessage(), 'JallCardStatusUpdateJobTest::executar');
    exit("Erro de banco de dados: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    LogHelper::logBitrixHelpers("Erro geral no JallCardStatusUpdateJobTest: " . $e->getMessage(), 'JallCardStatusUpdateJobTest::executar');
    exit("Erro geral: " . $e->getMessage() . "\n");
}
