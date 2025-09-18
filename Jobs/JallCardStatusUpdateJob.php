<?php
date_default_timezone_set('America/Sao_Paulo');

// Define nome da aplicação para logs
if (!defined('NOME_APLICACAO')) {
    define('NOME_APLICACAO', 'JALLCARD_STATUS_UPDATER');
}

require_once __DIR__ . '/../Repositories/DatabaseRepository.php';
require_once __DIR__ . '/../helpers/JallCardHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php'; // Adicionado para adicionar comentário na timeline

use Repositories\DatabaseRepository;
use Helpers\JallCardHelper;
use Helpers\LogHelper;
use Helpers\BitrixDealHelper;
use Helpers\BitrixHelper; // Adicionado para adicionar comentário na timeline
// As classes DateTime, PDOException e Exception são globais e não precisam de 'use' statement.

// Gera traceId para toda execução do job
LogHelper::gerarTraceId();

try {
    $databaseRepository = new DatabaseRepository();

    LogHelper::logBitrixHelpers("Iniciando JallCardStatusUpdateJob: Atualização de status de pedidos vinculados.", 'JallCardStatusUpdateJob::executar');

    // 1. Obter todos os pedidos vinculados da tabela principal
    // (Opcional: filtrar por status_jallcard diferente de 'FINALIZADA' ou 'CANCELADA' para otimizar)
    $pedidosVinculados = $databaseRepository->getPedidosVinculados(); // Este método precisa ser adicionado ao DatabaseRepository
    LogHelper::logBitrixHelpers("Pedidos vinculados encontrados para atualização de status: " . count($pedidosVinculados) . " itens.", 'JallCardStatusUpdateJob::executar');

    if (empty($pedidosVinculados)) {
        LogHelper::logBitrixHelpers("Nenhum pedido vinculado encontrado para atualização de status.", 'JallCardStatusUpdateJob::executar');
        exit("Nenhum pedido vinculado encontrado para atualização de status.\n");
    }

    // 2. Para cada pedido vinculado, consultar o status na JallCard e atualizar o banco local e o Bitrix
    foreach ($pedidosVinculados as $pedido) {
        $opJallCard = $pedido['op_jallcard'];
        $idDealBitrix = $pedido['id_deal_bitrix'];
        $statusAtualLocal = $pedido['status_jallcard'] ?? 'INDEFINIDO'; // Status atual no banco local

        LogHelper::logBitrixHelpers("Processando Deal ID: {$idDealBitrix}, OP JallCard: {$opJallCard}. Status local: {$statusAtualLocal}", 'JallCardStatusUpdateJob::executar');

        if (empty($opJallCard)) {
            LogHelper::logBitrixHelpers("OP JallCard vazia para Deal ID: {$idDealBitrix}. Ignorando atualização de status.", 'JallCardStatusUpdateJob::executar');
            continue;
        }

        $ordemProducao = JallCardHelper::getOrdemProducao($opJallCard);
        LogHelper::logBitrixHelpers("Resposta da API JallCard para OP {$opJallCard}: " . json_encode($ordemProducao), 'JallCardStatusUpdateJob::executar');

        if ($ordemProducao && isset($ordemProducao['status'])) {
            $novoStatusJallCard = $ordemProducao['status'];
            $dataStatus = '';
            $statusDetalhado = '';
            $mensagemStatus = '';
            $commentTimeline = '';
            $idRastreamento = null;
            $transportadora = null;

            // Buscar dados da transportadora e ID de rastreamento o mais cedo possível
            LogHelper::logBitrixHelpers("Buscando documentos para OP {$opJallCard} para ID de rastreamento e transportadora.", 'JallCardStatusUpdateJob::executar');
            $documentos = JallCardHelper::getDocumentosByOp($opJallCard, true);
            LogHelper::logBitrixHelpers("Resposta da API JallCard /documentos para OP {$opJallCard}: " . json_encode($documentos), 'JallCardStatusUpdateJob::executar');

            if (!empty($documentos) && isset($documentos[0])) {
                    $doc = $documentos[0];
                    $transportadora = $doc['entregadora'] ?? null;
                    $idRastreamento = $doc['codigoPostagem'] ?? null;
                    LogHelper::logBitrixHelpers("Dados de rastreamento encontrados para OP {$opJallCard}: Transportadora: " . ($transportadora ?? 'N/A') . ", ID: " . ($idRastreamento ?? 'N/A'), 'JallCardStatusUpdateJob::executar');
                } else {
                    LogHelper::logBitrixHelpers("Nenhum documento encontrado para OP {$opJallCard} para buscar ID de rastreamento e transportadora.", 'JallCardStatusUpdateJob::executar');
                }

            // Determinar o status mais recente e sua data
            if (!empty($ordemProducao['producao']['expedicao'])) {
                $statusDetalhado = 'Expedição';
                $dataStatus = (new DateTime($ordemProducao['producao']['expedicao']))->format('d/m/Y H:i:s');
                
                if ($transportadora && $idRastreamento) {
                    $mensagemStatus = "JallCard: Recolhido pela transportadora"; // Mensagem simplificada para campo retorno
                    $commentTimeline = "JallCard: Status finalizado: {$transportadora} - {$idRastreamento}.\nData: {$dataStatus}";
                } else {
                    $mensagemStatus = "JallCard: Aguardando recolhimento da transportadora"; // Mensagem simplificada para campo retorno
                    $commentTimeline = "JallCard: Status atualizado para 'Expedição'.\nData: {$dataStatus}";
                }

            } elseif (!empty($ordemProducao['producao']['preExpedicao'])) {
                $statusDetalhado = 'Pré-Expedição';
                $dataStatus = (new DateTime($ordemProducao['producao']['preExpedicao']))->format('d/m/Y H:i:s');
                $mensagemStatus = "JallCard: Aguardando recolhimento da transportadora"; // Mensagem simplificada para campo retorno
                $commentTimeline = "JallCard: Aguardando recolhimento da transportadora.\nData: {$dataStatus}";
            } elseif (!empty($ordemProducao['producao']['gravacao'])) {
                $statusDetalhado = 'Gravação';
                $dataStatus = (new DateTime($ordemProducao['producao']['gravacao']))->format('d/m/Y H:i:s');
                $mensagemStatus = "JallCard: Cartões enviados para gravação"; // Mensagem simplificada para campo retorno
                $commentTimeline = "JallCard: Cartões enviados para gravação.\nData: {$dataStatus}";
            } else {
                $statusDetalhado = $novoStatusJallCard; // Usar o status geral se não houver etapas de produção
                $mensagemStatus = "JallCard: Outros status"; // Mensagem simplificada para campo retorno
                $commentTimeline = "JallCard: Status atualizado para '{$statusDetalhado}'.";
            }

            // Verificar se o status mudou antes de atualizar
            if ($novoStatusJallCard !== $statusAtualLocal) {
                // Atualizar status no banco de dados local
                $databaseRepository->atualizarStatusJallCard($opJallCard, $novoStatusJallCard);
                LogHelper::logBitrixHelpers("Status JallCard para OP {$opJallCard} atualizado para '{$novoStatusJallCard}' no banco local.", 'JallCardStatusUpdateJob::executar');

                // Atualizar status no Bitrix (campo retorno)
                $campoRetornoBitrix = 'ufCrm8_1756758530'; // Campo retorno da Telenet
                $camposBitrix = [$campoRetornoBitrix => $mensagemStatus];

                // Adicionar campos de rastreamento e transportadora se encontrados
                $campoNomeTransportadoraBitrix = 'ufCrm8_1758216263'; // UF do campo Nome da Transportadora no Bitrix
                $campoIdRastreamentoBitrix = 'ufCrm8_1758216333';     // UF do campo ID Rastreamento Transportadora no Bitrix
                $campoIdRastreamentoDB = 'id_rastreamento_flash_pegasus'; // Placeholder: Nome do campo no banco de dados local

                if ($transportadora && !empty($campoNomeTransportadoraBitrix)) {
                    $camposBitrix[$campoNomeTransportadoraBitrix] = $transportadora;
                    LogHelper::logBitrixHelpers("Adicionando Nome da Transportadora '{$transportadora}' ao Bitrix para Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJob::executar');
                }
                if ($idRastreamento && !empty($campoIdRastreamentoBitrix)) {
                    $camposBitrix[$campoIdRastreamentoBitrix] = $idRastreamento;
                    LogHelper::logBitrixHelpers("Adicionando ID de rastreamento '{$idRastreamento}' ao Bitrix para Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJob::executar');
                }

                // TODO: Salvar ID de rastreamento no banco de dados local (pedidos_integracao)
                // if ($idRastreamento && !empty($campoIdRastreamentoDB)) {
                //     $databaseRepository->atualizarCampoPedidoIntegracao($idDealBitrix, $campoIdRastreamentoDB, $idRastreamento); // Esta função precisa ser criada no DatabaseRepository
                //     LogHelper::logBitrixHelpers("ID de rastreamento '{$idRastreamento}' salvo no banco de dados local para Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJob::executar');
                // }

                $resultadoUpdateBitrix = BitrixDealHelper::editarDeal(1042, $idDealBitrix, $camposBitrix); // 1042 é o entity_type_id para Deals

                if ($resultadoUpdateBitrix['success']) {
                    LogHelper::logBitrixHelpers("Deal ID: {$idDealBitrix} atualizado no Bitrix24 com mensagem de status e/ou rastreamento: '{$mensagemStatus}'.", 'JallCardStatusUpdateJob::executar');
                } else {
                    LogHelper::logBitrixHelpers("Erro ao atualizar Deal ID: {$idDealBitrix} no Bitrix24 com status: " . ($resultadoUpdateBitrix['error'] ?? 'Erro desconhecido'), 'JallCardStatusUpdateJob::executar');
                }

                // Adicionar comentário na Timeline do Deal
                $entityTypeTimeline = 'dynamic_1042'; // dynamic_ + entity_type_id para Deals
                $resultadoCommentBitrix = BitrixHelper::adicionarComentarioTimeline($entityTypeTimeline, $idDealBitrix, $commentTimeline, 36); // 36 é o ID do usuário (exemplo)

                if ($resultadoCommentBitrix['success']) {
                    LogHelper::logBitrixHelpers("Comentário de status adicionado à timeline do Deal ID: {$idDealBitrix}.", 'JallCardStatusUpdateJob::executar');
                } else {
                    LogHelper::logBitrixHelpers("Erro ao adicionar comentário de status à timeline do Deal ID: {$idDealBitrix}: " . ($resultadoCommentBitrix['error'] ?? 'Erro desconhecido'), 'JallCardStatusUpdateJob::executar');
                }
            } else {
                LogHelper::logBitrixHelpers("Status JallCard para OP {$opJallCard} não mudou ('{$novoStatusJallCard}'). Nenhuma atualização no Bitrix.", 'JallCardStatusUpdateJob::executar');
            }
        } else {
            LogHelper::logBitrixHelpers("Não foi possível obter status ou a resposta da API JallCard está incompleta para OP: {$opJallCard}.", 'JallCardStatusUpdateJob::executar');
        }
    }

    LogHelper::logBitrixHelpers("JallCardStatusUpdateJob finalizado.", 'JallCardStatusUpdateJob::executar');
    exit("JallCardStatusUpdateJob finalizado com sucesso.\n");

} catch (PDOException $e) {
    LogHelper::logBitrixHelpers("Erro de banco de dados no JallCardStatusUpdateJob: " . $e->getMessage(), 'JallCardStatusUpdateJob::executar');
    exit("Erro de banco de dados: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    LogHelper::logBitrixHelpers("Erro geral no JallCardStatusUpdateJob: " . $e->getMessage(), 'JallCardStatusUpdateJob::executar');
    exit("Erro geral: " . $e->getMessage() . "\n");
}
