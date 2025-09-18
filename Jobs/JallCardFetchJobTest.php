<?php
date_default_timezone_set('America/Sao_Paulo');

// Define nome da aplicação para logs
if (!defined('NOME_APLICACAO')) {
    define('NOME_APLICACAO', 'JALLCARD_FETCHER_TESTE_HISTORICO');
}

require_once __DIR__ . '/../Repositories/DatabaseRepository.php';
require_once __DIR__ . '/../helpers/JallCardHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Repositories\DatabaseRepository;
use Helpers\JallCardHelper;
use Helpers\LogHelper;

// Gera traceId para toda execução do job
LogHelper::gerarTraceId();

try {
    $databaseRepository = new DatabaseRepository();

    LogHelper::logBitrixHelpers("Iniciando JallCardFetchJobTest: Coleta de pedidos históricos da JallCard.", 'JallCardFetchJobTest::executar');

    // 1. Coletar dados da JallCard para o período específico (08/09/2025 por 7 dias)
    $dataInicioBusca = '2025-09-08T00:00:00';
    $dataFimBusca = (new DateTime('2025-09-08T00:00:00'))->modify('+7 days')->format('Y-m-d\TH:i:s');

    LogHelper::logBitrixHelpers("Buscando arquivos processados da JallCard para o período: {$dataInicioBusca} até {$dataFimBusca}.", 'JallCardFetchJobTest::executar');
    $pedidosJallCardRaw = JallCardHelper::getArquivosProcessadosPorPeriodo($dataInicioBusca, $dataFimBusca);
    LogHelper::logBitrixHelpers("Dados brutos da JallCard para o período: " . json_encode($pedidosJallCardRaw), 'JallCardFetchJobTest::executar');
    
    if (empty($pedidosJallCardRaw)) {
        LogHelper::logBitrixHelpers("Nenhum arquivo processado encontrado na JallCard para o período especificado.", 'JallCardFetchJobTest::executar');
        exit("Nenhum arquivo processado encontrado na JallCard para o período especificado.\n"); // Adicionado para feedback CLI
    }

    // 2. Processar cada pedido da JallCard e salvar/atualizar na tabela vinculacao_jallcard
    foreach ($pedidosJallCardRaw as $pedidoJallCardItem) {
        $pedidoProducaoJallCard = $pedidoJallCardItem['pedidoProducao'];
        LogHelper::logBitrixHelpers("Processando PedidoProducao: {$pedidoProducaoJallCard}", 'JallCardFetchJobTest::executar');

        // Verificar se este pedidoProducao já foi processado na tabela temporária
        $vinculacaoExistente = $databaseRepository->findVinculacaoJallCardByPedidoProducao($pedidoProducaoJallCard);
        LogHelper::logBitrixHelpers("Resultado da busca por vinculação existente para {$pedidoProducaoJallCard}: " . json_encode($vinculacaoExistente), 'JallCardFetchJobTest::executar');

        if ($vinculacaoExistente) {
            LogHelper::logBitrixHelpers("PedidoProducao {$pedidoProducaoJallCard} já processado na tabela temporária. Ignorando.", 'JallCardFetchJobTest::executar');
            continue;
        }

        // Obter detalhes do pedido (OP e nomes de arquivos)
        $detalhesPedido = JallCardHelper::getPedidoProducao($pedidoProducaoJallCard);
        LogHelper::logBitrixHelpers("Detalhes do pedido para {$pedidoProducaoJallCard}: " . json_encode($detalhesPedido), 'JallCardFetchJobTest::executar');

        if (!$detalhesPedido || empty($detalhesPedido['ops'])) {
            LogHelper::logBitrixHelpers("Não foi possível obter detalhes ou OP para PedidoProducao {$pedidoProducaoJallCard}.", 'JallCardFetchJobTest::executar');
            continue;
        }

        $opJallCard = $detalhesPedido['ops'][0]; // Assumindo uma única OP por pedido

        $nomeArquivoOriginal = null;
        $nomeArquivoConvertido = null;

        foreach ($detalhesPedido['arquivos'] as $arquivo) {
            if ((substr($arquivo['nome'], -strlen('.TXT.ICS')) === '.TXT.ICS')) {
                $nomeArquivoOriginal = $arquivo['nome'];
            } elseif ((substr($arquivo['nome'], -strlen('.env.fpl')) === '.env.fpl')) {
                $nomeArquivoConvertido = $arquivo['nome'];
            }
        }
        LogHelper::logBitrixHelpers("OP: {$opJallCard}, Arquivo Original: {$nomeArquivoOriginal}, Arquivo Convertido: {$nomeArquivoConvertido} para PedidoProducao {$pedidoProducaoJallCard}.", 'JallCardFetchJobTest::executar');

        // Inserir na tabela temporária
        $dadosParaInserir = [
            'pedido_producao_jallcard' => $pedidoProducaoJallCard,
            'op_jallcard' => $opJallCard,
            'nome_arquivo_original_jallcard' => $nomeArquivoOriginal,
            'nome_arquivo_convertido_jallcard' => $nomeArquivoConvertido,
            'data_processamento_jallcard' => $pedidoJallCardItem['dataProcessamento']
        ];
        $databaseRepository->inserirVinculacaoJallCard($dadosParaInserir);
        LogHelper::logBitrixHelpers("Dados inseridos na tabela vinculacao_jallcard para PedidoProducao {$pedidoProducaoJallCard}: " . json_encode($dadosParaInserir), 'JallCardFetchJobTest::executar');
    }

    LogHelper::logBitrixHelpers("JallCardFetchJobTest finalizado.", 'JallCardFetchJobTest::executar');
    exit("JallCardFetchJobTest finalizado com sucesso.\n"); // Adicionado para feedback CLI

} catch (PDOException $e) {
    LogHelper::logBitrixHelpers("Erro de banco de dados no JallCardFetchJobTest: " . $e->getMessage(), 'JallCardFetchJobTest::executar');
    exit("Erro de banco de dados: " . $e->getMessage() . "\n"); // Adicionado para feedback CLI
} catch (Exception $e) {
    LogHelper::logBitrixHelpers("Erro geral no JallCardFetchJobTest: " . $e->getMessage(), 'JallCardFetchJobTest::executar');
    exit("Erro geral: " . $e->getMessage() . "\n"); // Adicionado para feedback CLI
}
