<?php
date_default_timezone_set('America/Sao_Paulo');

// Define nome da aplicação para logs
if (!defined('NOME_APLICACAO')) {
    define('NOME_APLICACAO', 'JALLCARD_FETCHER');
}

require_once __DIR__ . '/../Repositories/DatabaseRepository.php';
require_once __DIR__ . '/../helpers/JallCardHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Repositories\DatabaseRepository;
use Helpers\JallCardHelper;
use Helpers\LogHelper;
use PDOException;
use Exception;

// Gera traceId para toda execução do job
LogHelper::gerarTraceId();

try {
    $databaseRepository = new DatabaseRepository();

    LogHelper::logBitrixHelpers("Iniciando JallCardFetchJob: Coleta de pedidos da JallCard.", 'JallCardFetchJob::executar');

    // 1. Coletar dados da JallCard (últimos 7 dias)
    $pedidosJallCardRaw = JallCardHelper::getArquivosProcessadosUltimos7Dias();
    LogHelper::logBitrixHelpers("Dados brutos da JallCard (últimos 7 dias): " . json_encode($pedidosJallCardRaw), 'JallCardFetchJob::executar');
    
    if (empty($pedidosJallCardRaw)) {
        LogHelper::logBitrixHelpers("Nenhum arquivo processado encontrado na JallCard nos últimos 7 dias.", 'JallCardFetchJob::executar');
        exit("Nenhum arquivo processado encontrado na JallCard nos últimos 7 dias.\n"); // Adicionado para feedback CLI
    }

    // 2. Processar cada pedido da JallCard e salvar/atualizar na tabela vinculacao_jallcard
    foreach ($pedidosJallCardRaw as $pedidoJallCardItem) {
        $pedidoProducaoJallCard = $pedidoJallCardItem['pedidoProducao'];
        LogHelper::logBitrixHelpers("Processando PedidoProducao: {$pedidoProducaoJallCard}", 'JallCardFetchJob::executar');

        // Verificar se este pedidoProducao já foi processado na tabela temporária
        $vinculacaoExistente = $databaseRepository->findVinculacaoJallCardByPedidoProducao($pedidoProducaoJallCard);
        LogHelper::logBitrixHelpers("Resultado da busca por vinculação existente para {$pedidoProducaoJallCard}: " . json_encode($vinculacaoExistente), 'JallCardFetchJob::executar');

        if ($vinculacaoExistente) {
            LogHelper::logBitrixHelpers("PedidoProducao {$pedidoProducaoJallCard} já processado na tabela temporária. Ignorando.", 'JallCardFetchJob::executar');
            continue;
        }

        // Obter detalhes do pedido (OP e nomes de arquivos)
        $detalhesPedido = JallCardHelper::getPedidoProducao($pedidoProducaoJallCard);
        LogHelper::logBitrixHelpers("Detalhes do pedido para {$pedidoProducaoJallCard}: " . json_encode($detalhesPedido), 'JallCardFetchJob::executar');

        if (!$detalhesPedido || empty($detalhesPedido['ops'])) {
            LogHelper::logBitrixHelpers("Não foi possível obter detalhes ou OP para PedidoProducao {$pedidoProducaoJallCard}.", 'JallCardFetchJob::executar');
            continue;
        }

        $opJallCard = $detalhesPedido['ops'][0]; // Assumindo uma única OP por pedido

        $nomeArquivoOriginal = null;
        $nomeArquivoConvertido = null;

        foreach ($detalhesPedido['arquivos'] as $arquivo) {
            if (str_ends_with($arquivo['nome'], '.TXT.ICS')) {
                $nomeArquivoOriginal = $arquivo['nome'];
            } elseif (str_ends_with($arquivo['nome'], '.env.fpl')) {
                $nomeArquivoConvertido = $arquivo['nome'];
            }
        }
        LogHelper::logBitrixHelpers("OP: {$opJallCard}, Arquivo Original: {$nomeArquivoOriginal}, Arquivo Convertido: {$nomeArquivoConvertido} para PedidoProducao {$pedidoProducaoJallCard}.", 'JallCardFetchJob::executar');

        // Inserir na tabela temporária
        $dadosParaInserir = [
            'pedido_producao_jallcard' => $pedidoProducaoJallCard,
            'op_jallcard' => $opJallCard,
            'nome_arquivo_original_jallcard' => $nomeArquivoOriginal,
            'nome_arquivo_convertido_jallcard' => $nomeArquivoConvertido,
            'data_processamento_jallcard' => $pedidoJallCardItem['dataProcessamento']
        ];
        $databaseRepository->inserirVinculacaoJallCard($dadosParaInserir);
        LogHelper::logBitrixHelpers("Dados inseridos na tabela vinculacao_jallcard para PedidoProducao {$pedidoProducaoJallCard}: " . json_encode($dadosParaInserir), 'JallCardFetchJob::executar');
    }

    LogHelper::logBitrixHelpers("JallCardFetchJob finalizado.", 'JallCardFetchJob::executar');
    exit("JallCardFetchJob finalizado com sucesso.\n"); // Adicionado para feedback CLI

} catch (PDOException $e) {
    LogHelper::logBitrixHelpers("Erro de banco de dados no JallCardFetchJob: " . $e->getMessage(), 'JallCardFetchJob::executar');
    exit("Erro de banco de dados: " . $e->getMessage() . "\n"); // Adicionado para feedback CLI
} catch (Exception $e) {
    LogHelper::logBitrixHelpers("Erro geral no JallCardFetchJob: " . $e->getMessage(), 'JallCardFetchJob::executar');
    exit("Erro geral: " . $e->getMessage() . "\n"); // Adicionado para feedback CLI
}
