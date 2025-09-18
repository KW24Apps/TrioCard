<?php
date_default_timezone_set('America/Sao_Paulo');

// Define nome da aplicação para logs
if (!defined('NOME_APLICACAO')) {
    define('NOME_APLICACAO', 'JALLCARD_CORRECTION_SCRIPT');
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

// Gera traceId para toda execução do script
LogHelper::gerarTraceId();

try {
    $databaseRepository = new DatabaseRepository();

    LogHelper::logBitrixHelpers("Iniciando JallCardCorrectionScript: Correção de IDs de rastreamento e transportadoras no Bitrix.", 'JallCardCorrectionScript::executar');

    // Obter TODOS os pedidos vinculados (incluindo FINALIZADA/CANCELADA para correção)
    // Para este script de correção, precisamos de todos os pedidos vinculados,
    // independentemente do status, para garantir que todos os IDs de rastreamento sejam corrigidos.
    $sqlAllLinked = "SELECT * FROM pedidos_integracao WHERE vinculacao_jallcard = 'VINCULADO'";
    $stmtAllLinked = $databaseRepository->getConnection()->query($sqlAllLinked);
    $pedidosVinculados = $stmtAllLinked->fetchAll(PDO::FETCH_ASSOC);

    LogHelper::logBitrixHelpers("Pedidos vinculados encontrados para correção: " . count($pedidosVinculados) . " itens.", 'JallCardCorrectionScript::executar');

    if (empty($pedidosVinculados)) {
        LogHelper::logBitrixHelpers("Nenhum pedido vinculado encontrado para correção.", 'JallCardCorrectionScript::executar');
        exit("Nenhum pedido vinculado encontrado para correção.\n");
    }

    foreach ($pedidosVinculados as $pedido) {
        $opJallCard = $pedido['op_jallcard'];
        $idDealBitrix = $pedido['id_deal_bitrix'];
        $statusAtualLocal = $pedido['status_jallcard'] ?? 'INDEFINIDO';

        LogHelper::logBitrixHelpers("Processando Deal ID: {$idDealBitrix}, OP JallCard: {$opJallCard}. Status local: {$statusAtualLocal}", 'JallCardCorrectionScript::executar');

        if (empty($opJallCard)) {
            LogHelper::logBitrixHelpers("OP JallCard vazia para Deal ID: {$idDealBitrix}. Ignorando correção.", 'JallCardCorrectionScript::executar');
            continue;
        }

        $ordemProducao = JallCardHelper::getOrdemProducao($opJallCard);
        LogHelper::logBitrixHelpers("Resposta da API JallCard para OP {$opJallCard}: " . json_encode($ordemProducao), 'JallCardCorrectionScript::executar');

        if ($ordemProducao && isset($ordemProducao['status'])) {
            $novoStatusJallCard = $ordemProducao['status'];
            $dataStatus = '';
            $mensagemStatus = ''; // A mensagem de status principal não será atualizada por este script
            $commentTimeline = '';
            $idRastreamento = null;
            $transportadora = null;

            // Buscar dados da transportadora e ID de rastreamento o mais cedo possível
            LogHelper::logBitrixHelpers("Buscando documentos para OP {$opJallCard} para ID de rastreamento e transportadora.", 'JallCardCorrectionScript::executar');
            $documentos = JallCardHelper::getDocumentosByOp($opJallCard, true);
            LogHelper::logBitrixHelpers("Resposta da API JallCard /documentos para OP {$opJallCard}: " . json_encode($documentos), 'JallCardCorrectionScript::executar');

            if (!empty($documentos) && isset($documentos[0])) {
                $doc = $documentos[0];
                $transportadora = $doc['entregadora'] ?? null;
                $idRastreamento = $doc['codigoPostagem'] ?? null;
                LogHelper::logBitrixHelpers("Dados de rastreamento encontrados para OP {$opJallCard}: Transportadora: " . ($transportadora ?? 'N/A') . ", ID: " . ($idRastreamento ?? 'N/A'), 'JallCardCorrectionScript::executar');
            } else {
                LogHelper::logBitrixHelpers("Nenhum documento encontrado para OP {$opJallCard} para buscar ID de rastreamento e transportadora.", 'JallCardCorrectionScript::executar');
            }

            // Preparar campos para atualização no Bitrix
            $camposBitrix = [];
            $campoNomeTransportadoraBitrix = 'ufCrm8_1758216263';
            $campoIdRastreamentoBitrix = 'ufCrm8_1758216333';

            if ($transportadora && !empty($campoNomeTransportadoraBitrix)) {
                $camposBitrix[$campoNomeTransportadoraBitrix] = $transportadora;
                LogHelper::logBitrixHelpers("Adicionando Nome da Transportadora '{$transportadora}' ao Bitrix para Deal ID: {$idDealBitrix}.", 'JallCardCorrectionScript::executar');
            }
            if ($idRastreamento && !empty($campoIdRastreamentoBitrix)) {
                $camposBitrix[$campoIdRastreamentoBitrix] = $idRastreamento;
                LogHelper::logBitrixHelpers("Adicionando ID de rastreamento '{$idRastreamento}' ao Bitrix para Deal ID: {$idDealBitrix}.", 'JallCardCorrectionScript::executar');
            }

            // Forçar a atualização no Bitrix para os campos de rastreamento, se houver dados
            if (!empty($camposBitrix)) {
                $resultadoUpdateBitrix = BitrixDealHelper::editarDeal(1042, $idDealBitrix, $camposBitrix);

                if ($resultadoUpdateBitrix['success']) {
                    LogHelper::logBitrixHelpers("Deal ID: {$idDealBitrix} atualizado no Bitrix24 com campos de rastreamento: " . json_encode(array_keys($camposBitrix)) . ".", 'JallCardCorrectionScript::executar');
                } else {
                    LogHelper::logBitrixHelpers("Erro ao atualizar Deal ID: {$idDealBitrix} no Bitrix24: " . ($resultadoUpdateBitrix['error'] ?? 'Erro desconhecido'), 'JallCardCorrectionScript::executar');
                }
            } else {
                LogHelper::logBitrixHelpers("Nenhum campo de rastreamento para atualizar no Bitrix para Deal ID: {$idDealBitrix}.", 'JallCardCorrectionScript::executar');
            }

            // Opcional: Adicionar um comentário na timeline indicando a correção
            // if ($transportadora || $idRastreamento) {
            //     $commentTimeline = "JallCard: Correção de dados de rastreamento. Transportadora: " . ($transportadora ?? 'N/A') . ", ID Rastreamento: " . ($idRastreamento ?? 'N/A');
            //     $entityTypeTimeline = 'dynamic_1042';
            //     $resultadoCommentBitrix = BitrixHelper::adicionarComentarioTimeline($entityTypeTimeline, $idDealBitrix, $commentTimeline, 36);
            //     if ($resultadoCommentBitrix['success']) {
            //         LogHelper::logBitrixHelpers("Comentário de correção adicionado à timeline do Deal ID: {$idDealBitrix}.", 'JallCardCorrectionScript::executar');
            //     } else {
            //         LogHelper::logBitrixHelpers("Erro ao adicionar comentário de correção à timeline do Deal ID: {$idDealBitrix}: " . ($resultadoCommentBitrix['error'] ?? 'Erro desconhecido'), 'JallCardCorrectionScript::executar');
            //     }
            // }

        } else {
            LogHelper::logBitrixHelpers("Não foi possível obter status ou a resposta da API JallCard está incompleta para OP: {$opJallCard}. Ignorando correção.", 'JallCardCorrectionScript::executar');
        }
    }

    LogHelper::logBitrixHelpers("JallCardCorrectionScript finalizado.", 'JallCardCorrectionScript::executar');
    exit("JallCardCorrectionScript finalizado com sucesso.\n");

} catch (PDOException $e) {
    LogHelper::logBitrixHelpers("Erro de banco de dados no JallCardCorrectionScript: " . $e->getMessage(), 'JallCardCorrectionScript::executar');
    exit("Erro de banco de dados: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    LogHelper::logBitrixHelpers("Erro geral no JallCardCorrectionScript: " . $e->getMessage(), 'JallCardCorrectionScript::executar');
    exit("Erro geral: " . $e->getMessage() . "\n");
}
