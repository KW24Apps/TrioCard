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

    LogHelper::logBitrixHelpers("Iniciando JallCardCorrectionScript: Correção de IDs de rastreamento e transportadoras no banco de dados local.", 'JallCardCorrectionScript::executar');

    // Obter TODOS os pedidos vinculados (incluindo FINALIZADA/CANCELADA para correção)
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
            $idRastreamento = null;
            $transportadora = null;

            // Buscar dados da transportadora e ID de rastreamento
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

            // Atualizar o ID de rastreamento na tabela local
            if ($idRastreamento !== null) {
                $databaseRepository->atualizarIdRastreioTransportador($idDealBitrix, $idRastreamento);
                LogHelper::logBitrixHelpers("ID de rastreamento '{$idRastreamento}' atualizado no banco de dados local para Deal ID: {$idDealBitrix}.", 'JallCardCorrectionScript::executar');
            } else {
                LogHelper::logBitrixHelpers("Nenhum ID de rastreamento encontrado para OP {$opJallCard}. Não atualizando o banco de dados local para Deal ID: {$idDealBitrix}.", 'JallCardCorrectionScript::executar');
            }

            // Não há atualização do Bitrix neste script de correção, apenas do banco de dados local.

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
