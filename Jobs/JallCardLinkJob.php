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

class JallCardLinkJob {
    private static $config;

    public static function init() {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/Variaveis.php';
        }
    }

    public static function executar() {
        self::init(); // Garante que a configuração seja carregada
        $bitrixConfig = self::$config['bitrix'];

        // Gera traceId para toda execução do job
        LogHelper::gerarTraceId();

try {
    $databaseRepository = new DatabaseRepository();

    // LogHelper::logTrioCardGeral("Iniciando JallCardLinkJob: Vinculação de pedidos pendentes.", 'JallCardLinkJob::executar', 'INFO'); // Removido: log positivo não essencial

    // 1. Obter pedidos pendentes de vinculação na tabela principal (pedidos_integracao)
    $pedidosPendentesBitrix = $databaseRepository->getPedidosPendentesVinculacao();
    // LogHelper::logTrioCardGeral("Pedidos pendentes de vinculação na tabela principal: " . count($pedidosPendentesBitrix) . " itens.", 'JallCardLinkJob::executar', 'DEBUG'); // Removido: log positivo não essencial

    if (empty($pedidosPendentesBitrix)) {
        LogHelper::logTrioCardGeral("Nenhum pedido pendente de vinculação encontrado na tabela principal.", 'JallCardLinkJob::executar', 'INFO');
        exit("Nenhum pedido pendente de vinculação encontrado na tabela principal.\n");
    }

    // 2. Obter registros pendentes de vinculação na tabela temporária da JallCard
    $vinculacoesJallCardPendentes = $databaseRepository->getVinculacoesJallCardPendentes(); // Assumindo que esta função existe ou será criada
    // LogHelper::logTrioCardGeral("Registros pendentes de vinculação na tabela JallCard: " . count($vinculacoesJallCardPendentes) . " itens.", 'JallCardLinkJob::executar', 'DEBUG'); // Removido: log positivo não essencial

    if (empty($vinculacoesJallCardPendentes)) {
        LogHelper::logTrioCardGeral("Nenhum registro pendente de vinculação encontrado na tabela JallCard.", 'JallCardLinkJob::executar', 'INFO');
        exit("Nenhum registro pendente de vinculação encontrado na tabela JallCard.\n");
    }

    // 3. Tentar vincular os pedidos
    $vinculadosCount = 0;
    foreach ($pedidosPendentesBitrix as $indexBitrix => $pedidoBitrix) {
        $idDealBitrix = $pedidoBitrix['id_deal_bitrix'];
        $nomeArquivoTelenet = $pedidoBitrix['nome_arquivo_telenet'];

        // LogHelper::logTrioCardGeral("Processando pedido Bitrix ID: {$idDealBitrix}, Arquivo Telenet: {$nomeArquivoTelenet}", 'JallCardLinkJob::executar', 'DEBUG'); // Removido: log positivo não essencial

        $keysTelenet = JallCardHelper::extractMatchKeys($nomeArquivoTelenet);

        if (!$keysTelenet) {
            LogHelper::logTrioCardGeral("Não foi possível extrair chaves de comparação do arquivo Telenet: {$nomeArquivoTelenet}", 'JallCardLinkJob::executar', 'WARNING');
            continue;
        }

        foreach ($vinculacoesJallCardPendentes as $indexJallCard => $jallCardItem) {
            $nomeArquivoJallCard = $jallCardItem['nome_arquivo_original_jallcard'];
            $keysJallCard = JallCardHelper::extractMatchKeys($nomeArquivoJallCard);

            if (!$keysJallCard) {
                LogHelper::logTrioCardGeral("Não foi possível extrair chaves de comparação do arquivo JallCard: {$nomeArquivoJallCard}", 'JallCardLinkJob::executar', 'WARNING');
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
                LogHelper::logTrioCardGeral("Vínculo estabelecido para Deal ID: {$idDealBitrix} (Telenet: {$keysTelenet['data']}-{$keysTelenet['sequencia']}) com JallCard PedidoProducao: {$jallCardItem['pedido_producao_jallcard']} (JallCard: {$keysJallCard['data']}-{$keysJallCard['sequencia']}).", 'JallCardLinkJob::executar', 'INFO');
                $vinculadosCount++;

                // 4. Atualizar o Deal no Bitrix24 com os dados da JallCard
                $camposBitrix = [
                    $bitrixConfig['mapeamento_campos_jallcard']['op_jallcard'] => $jallCardItem['op_jallcard'], // Ordem de Produção
                    $bitrixConfig['mapeamento_campos_jallcard']['pedido_producao_jallcard'] => $jallCardItem['pedido_producao_jallcard'], // ID Pedido Produção Jall Card
                    $bitrixConfig['mapeamento_campos_jallcard']['campo_retorno_telenet'] => 'JallCard: Arquivo recebido com sucesso.' // Campo retorno
                ];
                $resultadoUpdateBitrix = BitrixDealHelper::editarDeal($bitrixConfig['entity_type_id_deal'], $idDealBitrix, $camposBitrix);

                if ($resultadoUpdateBitrix['success']) {
                    LogHelper::logBitrix("Deal ID: {$idDealBitrix} atualizado no Bitrix24 com OP: {$jallCardItem['op_jallcard']} e PedidoProducao JallCard: {$jallCardItem['pedido_producao_jallcard']}.", 'JallCardLinkJob::executar', 'INFO');
                } else {
                    LogHelper::logBitrix("Erro ao atualizar Deal ID: {$idDealBitrix} no Bitrix24: " . ($resultadoUpdateBitrix['error'] ?? 'Erro desconhecido'), 'JallCardLinkJob::executar', 'ERROR');
                }

                // 5. Adicionar comentário na Timeline do Deal
                $entityTypeTimeline = 'dynamic_' . $bitrixConfig['entity_type_id_deal'];
                $comment = "JallCard: Arquivo recebido.\nOrdem de Produção: {$jallCardItem['op_jallcard']}\nPedido Produção JallCard: {$jallCardItem['pedido_producao_jallcard']}";
                $resultadoCommentBitrix = BitrixHelper::adicionarComentarioTimeline($entityTypeTimeline, $idDealBitrix, $comment, $bitrixConfig['user_id_comments']);

                if ($resultadoCommentBitrix['success']) {
                    LogHelper::logBitrix("Comentário adicionado à timeline do Deal ID: {$idDealBitrix}.", 'JallCardLinkJob::executar', 'INFO');
                } else {
                    LogHelper::logBitrix("Erro ao adicionar comentário à timeline do Deal ID: {$idDealBitrix}: " . ($resultadoCommentBitrix['error'] ?? 'Erro desconhecido'), 'JallCardLinkJob::executar', 'ERROR');
                }

                // Remover o item vinculado da lista de JallCard para evitar múltiplos matches
                unset($vinculacoesJallCardPendentes[$indexJallCard]);
                break; // Ir para o próximo pedido Bitrix
            }
        }
    }

    // LogHelper::logTrioCardGeral("JallCardLinkJob finalizado. Total de itens vinculados: {$vinculadosCount}.", 'JallCardLinkJob::executar', 'INFO'); // Removido: log positivo não essencial
    exit("JallCardLinkJob finalizado com sucesso. Total de itens vinculados: {$vinculadosCount}.\n");

} catch (PDOException $e) {
    LogHelper::logTrioCardGeral("Erro de banco de dados no JallCardLinkJob: " . $e->getMessage(), 'JallCardLinkJob::executar', 'CRITICAL');
    exit("Erro de banco de dados: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    LogHelper::logTrioCardGeral("Erro geral no JallCardLinkJob: " . $e->getMessage(), 'JallCardLinkJob::executar', 'CRITICAL');
    exit("Erro geral: " . $e->getMessage() . "\n");
}
} // Fecha o método executar
} // Fecha a classe JallCardLinkJob

// Chama o método estático executar
JallCardLinkJob::executar();
