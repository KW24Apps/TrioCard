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

class JallCardFetchJob {
    private static $config;

    public static function init() {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/Variaveis.php';
        }
    }

    public static function executar() {
        self::init(); // Garante que a configuração seja carregada
        // $bitrixConfig = self::$config['bitrix']; // Não é usado diretamente neste job, mas pode ser útil para logs futuros

        // Gera traceId para toda execução do job
    LogHelper::gerarTraceId();

try {
    $databaseRepository = new DatabaseRepository();

    // LogHelper::logJallCard("Iniciando JallCardFetchJob: Coleta de pedidos da JallCard.", 'JallCardFetchJob::executar', 'INFO'); // Removido: log positivo não essencial

    // 1. Coletar dados da JallCard (últimos 7 dias)
    $pedidosJallCardRaw = JallCardHelper::getArquivosProcessadosUltimos7Dias();
    // LogHelper::logJallCard("Dados brutos da JallCard (últimos 7 dias): " . json_encode($pedidosJallCardRaw), 'JallCardFetchJob::executar', 'DEBUG'); // Removido: log positivo não essencial
    
    if (empty($pedidosJallCardRaw)) {
        LogHelper::logJallCard("Nenhum arquivo processado encontrado na JallCard nos últimos 7 dias.", 'JallCardFetchJob::executar', 'WARNING');
        exit("Nenhum arquivo processado encontrado na JallCard nos últimos 7 dias.\n"); // Adicionado para feedback CLI
    }

    // 2. Processar cada pedido da JallCard e salvar/atualizar na tabela vinculacao_jallcard
    foreach ($pedidosJallCardRaw as $pedidoJallCardItem) {
        $pedidoProducaoJallCard = $pedidoJallCardItem['pedidoProducao'];
        // LogHelper::logJallCard("Processando PedidoProducao: {$pedidoProducaoJallCard}", 'JallCardFetchJob::executar', 'DEBUG'); // Removido: log positivo não essencial

        // Verificar se este pedidoProducao já foi processado na tabela temporária
        $vinculacaoExistente = $databaseRepository->findVinculacaoJallCardByPedidoProducao($pedidoProducaoJallCard);
        // LogHelper::logJallCard("Resultado da busca por vinculação existente para {$pedidoProducaoJallCard}: " . json_encode($vinculacaoExistente), 'JallCardFetchJob::executar', 'DEBUG'); // Removido: log positivo não essencial

        if ($vinculacaoExistente) {
            LogHelper::logJallCard("PedidoProducao {$pedidoProducaoJallCard} já processado na tabela temporária. Ignorando.", 'JallCardFetchJob::executar', 'WARNING');
            continue;
        }

        // Obter detalhes do pedido (OP e nomes de arquivos)
        $detalhesPedido = JallCardHelper::getPedidoProducao($pedidoProducaoJallCard);
        // LogHelper::logJallCard("Detalhes do pedido para {$pedidoProducaoJallCard}: " . json_encode($detalhesPedido), 'JallCardFetchJob::executar', 'DEBUG'); // Removido: log positivo não essencial

        if (!$detalhesPedido || empty($detalhesPedido['ops'])) {
            LogHelper::logJallCard("Não foi possível obter detalhes ou OP para PedidoProducao {$pedidoProducaoJallCard}.", 'JallCardFetchJob::executar', 'ERROR');
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
        // LogHelper::logJallCard("OP: {$opJallCard}, Arquivo Original: {$nomeArquivoOriginal}, Arquivo Convertido: {$nomeArquivoConvertido} para PedidoProducao {$pedidoProducaoJallCard}.", 'JallCardFetchJob::executar', 'DEBUG'); // Removido: log positivo não essencial

        // Inserir na tabela temporária
        $dadosParaInserir = [
            'pedido_producao_jallcard' => $pedidoProducaoJallCard,
            'op_jallcard' => $opJallCard,
            'nome_arquivo_original_jallcard' => $nomeArquivoOriginal,
            'nome_arquivo_convertido_jallcard' => $nomeArquivoConvertido,
            'data_processamento_jallcard' => $pedidoJallCardItem['dataProcessamento']
        ];
        $databaseRepository->inserirVinculacaoJallCard($dadosParaInserir);
        // LogHelper::logJallCard("Dados inseridos na tabela vinculacao_jallcard para PedidoProducao {$pedidoProducaoJallCard}: " . json_encode($dadosParaInserir), 'JallCardFetchJob::executar', 'INFO'); // Removido: log positivo não essencial
    }

    // LogHelper::logJallCard("JallCardFetchJob finalizado.", 'JallCardFetchJob::executar', 'INFO'); // Removido: log positivo não essencial
    exit("JallCardFetchJob finalizado com sucesso.\n"); // Adicionado para feedback CLI

} catch (PDOException $e) {
    LogHelper::logJallCard("Erro de banco de dados no JallCardFetchJob: " . $e->getMessage(), 'JallCardFetchJob::executar', 'CRITICAL');
    exit("Erro de banco de dados: " . $e->getMessage() . "\n"); // Adicionado para feedback CLI
} catch (Exception $e) {
    LogHelper::logJallCard("Erro geral no JallCardFetchJob: " . $e->getMessage(), 'JallCardFetchJob::executar', 'CRITICAL');
    exit("Erro geral: " . $e->getMessage() . "\n"); // Adicionado para feedback CLI
}
} // Fecha o método executar
} // Fecha a classe JallCardFetchJob

// Chama o método estático executar
JallCardFetchJob::executar();
