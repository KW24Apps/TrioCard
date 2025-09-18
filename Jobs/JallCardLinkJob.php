<?php
date_default_timezone_set('America/Sao_Paulo');

// Define nome da aplicação para logs
if (!defined('NOME_APLICACAO')) {
    define('NOME_APLICACAO', 'JALLCARD_LINKER');
}

require_once __DIR__ . '/../Repositories/DatabaseRepository.php';
require_once __DIR__ . '/../helpers/JallCardHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php'; // Adicionado
require_once __DIR__ . '/../helpers/BitrixHelper.php';     // Adicionado

use Repositories\DatabaseRepository;
use Helpers\JallCardHelper;
use Helpers\LogHelper;
use Helpers\BitrixDealHelper; // Adicionado
use Helpers\BitrixHelper;     // Adicionado

// Gera traceId para toda execução do job
LogHelper::gerarTraceId();

try {
    $databaseRepository = new DatabaseRepository();

    LogHelper::logBitrixHelpers("Iniciando JallCardLinkJob: Vinculação de pedidos pendentes.", 'JallCardLinkJob::executar');

    // 1. Obter pedidos pendentes de vinculação na tabela principal (pedidos_integracao)
    $pedidosPendentesBitrix = $databaseRepository->getPedidosPendentesVinculacao();
    LogHelper::logBitrixHelpers("Pedidos pendentes de vinculação na tabela principal: " . count($pedidosPendentesBitrix) . " itens.", 'JallCardLinkJob::executar');

    if (empty($pedidosPendentesBitrix)) {
        LogHelper::logBitrixHelpers("Nenhum pedido pendente de vinculação encontrado na tabela principal.", 'JallCardLinkJob::executar');
        exit("Nenhum pedido pendente de vinculação encontrado na tabela principal.\n");
    }

    // 2. Obter registros pendentes de vinculação na tabela temporária da JallCard
    $vinculacoesJallCardPendentes = $databaseRepository->getVinculacoesJallCardPendentes(); // Assumindo que esta função existe ou será criada
    LogHelper::logBitrixHelpers("Registros pendentes de vinculação na tabela JallCard: " . count($vinculacoesJallCardPendentes) . " itens.", 'JallCardLinkJob::executar');

    if (empty($vinculacoesJallCardPendentes)) {
        LogHelper::logBitrixHelpers("Nenhum registro pendente de vinculação encontrado na tabela JallCard.", 'JallCardLinkJob::executar');
        exit("Nenhum registro pendente de vinculação encontrado na tabela JallCard.\n");
    }

    // 3. Tentar vincular os pedidos
    $vinculadosCount = 0;
    foreach ($pedidosPendentesBitrix as $indexBitrix => $pedidoBitrix) {
        $idDealBitrix = $pedidoBitrix['id_deal_bitrix'];
        $nomeArquivoTelenet = $pedidoBitrix['nome_arquivo_telenet'];

        LogHelper::logBitrixHelpers("Processando pedido Bitrix ID: {$idDealBitrix}, Arquivo Telenet: {$nomeArquivoTelenet}", 'JallCardLinkJob::executar');

        $keysTelenet = JallCardHelper::extractMatchKeys($nomeArquivoTelenet);

        if (!$keysTelenet) {
            LogHelper::logBitrixHelpers("Não foi possível extrair chaves de comparação do arquivo Telenet: {$nomeArquivoTelenet}", 'JallCardLinkJob::executar');
            continue;
        }

        foreach ($vinculacoesJallCardPendentes as $indexJallCard => $jallCardItem) {
            $nomeArquivoJallCard = $jallCardItem['nome_arquivo_original_jallcard'];
            $keysJallCard = JallCardHelper::extractMatchKeys($nomeArquivoJallCard);

            if (!$keysJallCard) {
                LogHelper::logBitrixHelpers("Não foi possível extrair chaves de comparação do arquivo JallCard: {$nomeArquivoJallCard}", 'JallCardLinkJob::executar');
                continue;
            }

            // Comparar as chaves
            if ($keysTelenet['data'] === $keysJallCard['data'] && $keysTelenet['sequencia'] === $keysJallCard['sequencia']) {
                // Vínculo encontrado!
                $databaseRepository->atualizarVinculacaoJallCard(
                    $idDealBitrix,
                    $jallCardItem['pedido_producao_jallcard'],
                    $jallCardItem['op_jallcard']
                );
                $databaseRepository->updateVinculacaoJallCardStatusTemp(
                    $jallCardItem['pedido_producao_jallcard'],
                    'VINCULADO_COM_SUCESSO'
                );
                LogHelper::logBitrixHelpers("Vínculo estabelecido para Deal ID: {$idDealBitrix} (Telenet: {$keysTelenet['data']}-{$keysTelenet['sequencia']}) com JallCard PedidoProducao: {$jallCardItem['pedido_producao_jallcard']} (JallCard: {$keysJallCard['data']}-{$keysJallCard['sequencia']}).", 'JallCardLinkJob::executar');
                $vinculadosCount++;

                // 4. Atualizar o Deal no Bitrix24 com os dados da JallCard
                $camposBitrix = [
                    'ufCrm8_1758208231' => $jallCardItem['op_jallcard'], // Ordem de Produção
                    'ufCrm8_1758208290' => $jallCardItem['pedido_producao_jallcard'], // ID Pedido Produção Jall Card
                    'ufCrm8_1756758530' => 'JallCard: Arquivo recebido com sucesso.' // Campo retorno
                ];
                $resultadoUpdateBitrix = BitrixDealHelper::editarDeal(1042, $idDealBitrix, $camposBitrix); // 1042 é o entity_type_id para Deals

                if ($resultadoUpdateBitrix['success']) {
                    LogHelper::logBitrixHelpers("Deal ID: {$idDealBitrix} atualizado no Bitrix24 com OP: {$jallCardItem['op_jallcard']} e PedidoProducao JallCard: {$jallCardItem['pedido_producao_jallcard']}.", 'JallCardLinkJob::executar');
                } else {
                    LogHelper::logBitrixHelpers("Erro ao atualizar Deal ID: {$idDealBitrix} no Bitrix24: " . ($resultadoUpdateBitrix['error'] ?? 'Erro desconhecido'), 'JallCardLinkJob::executar');
                }

                // 5. Adicionar comentário na Timeline do Deal
                $entityTypeTimeline = 'dynamic_1042'; // dynamic_ + entity_type_id para Deals
                $comment = "JallCard: Arquivo recebido.\nOrdem de Produção: {$jallCardItem['op_jallcard']}\nPedido Produção JallCard: {$jallCardItem['pedido_producao_jallcard']}";
                $resultadoCommentBitrix = BitrixHelper::adicionarComentarioTimeline($entityTypeTimeline, $idDealBitrix, $comment, 36); // 36 é o ID do usuário (exemplo)

                if ($resultadoCommentBitrix['success']) {
                    LogHelper::logBitrixHelpers("Comentário adicionado à timeline do Deal ID: {$idDealBitrix}.", 'JallCardLinkJob::executar');
                } else {
                    LogHelper::logBitrixHelpers("Erro ao adicionar comentário à timeline do Deal ID: {$idDealBitrix}: " . ($resultadoCommentBitrix['error'] ?? 'Erro desconhecido'), 'JallCardLinkJob::executar');
                }

                // Remover o item vinculado da lista de JallCard para evitar múltiplos matches
                unset($vinculacoesJallCardPendentes[$indexJallCard]);
                break; // Ir para o próximo pedido Bitrix
            }
        }
    }

    LogHelper::logBitrixHelpers("JallCardLinkJob finalizado. Total de itens vinculados: {$vinculadosCount}.", 'JallCardLinkJob::executar');
    exit("JallCardLinkJob finalizado com sucesso. Total de itens vinculados: {$vinculadosCount}.\n");

} catch (PDOException $e) {
    LogHelper::logBitrixHelpers("Erro de banco de dados no JallCardLinkJob: " . $e->getMessage(), 'JallCardLinkJob::executar');
    exit("Erro de banco de dados: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    LogHelper::logBitrixHelpers("Erro geral no JallCardLinkJob: " . $e->getMessage(), 'JallCardLinkJob::executar');
    exit("Erro geral: " . $e->getMessage() . "\n");
}
