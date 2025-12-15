<?php
date_default_timezone_set('America/Sao_Paulo');

// Define nome da aplicação para logs
if (!defined('NOME_APLICACAO')) {
    define('NOME_APLICACAO', 'JALLCARD_PROCESSADOR_PROVISORIO');
}

require_once __DIR__ . '/Repositories/DatabaseRepository.php'; // Necessário para inserir dados
require_once __DIR__ . '/Jobs/JallCardLinkJob.php';
require_once __DIR__ . '/Jobs/JallCardStatusUpdateJob.php';
require_once __DIR__ . '/helpers/LogHelper.php';
require_once __DIR__ . '/config/Variaveis.php'; // Necessário para as configurações da JallCard

use Repositories\DatabaseRepository;
use Helpers\LogHelper;
use Jobs\JallCardLinkJob; // Adicionado para chamar o job
use Jobs\JallCardStatusUpdateJob; // Adicionado para chamar o job

class JallCardProcessadorProvisorio {

    private static $config;

    public static function init() {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/config/Variaveis.php';
        }
    }

    /**
     * Método principal para chamadas à API JallCard (copiado do JallCardHelper)
     */
    private static function makeRequest(string $endpoint, string $method = 'GET', array $queryParams = [], array $bodyParams = []): ?array
    {
        self::init();
        $jallcardConfig = self::$config['jallcard'];

        $baseUrl = $jallcardConfig['base_url'];
        $credentials = $jallcardConfig['credentials'];
        $sslVerifyPeer = $jallcardConfig['ssl_verify_peer'];

        $url = $baseUrl . $endpoint;

        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($credentials),
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => $sslVerifyPeer,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        if (in_array($method, ['POST', 'PUT']) && !empty($bodyParams)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($bodyParams));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            LogHelper::logJallCard("Erro cURL para {$url}: {$error}", __CLASS__ . '::' . __FUNCTION__, 'ERROR');
            throw new Exception("Erro na requisição cURL: " . $error);
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMessage = $data['error'] ?? "Erro desconhecido na API JallCard. HTTP Code: {$httpCode}";
            LogHelper::logJallCard("Erro na API JallCard para {$url}: {$errorMessage}", __CLASS__ . '::' . __FUNCTION__, 'ERROR');
            throw new Exception("Erro na API JallCard: " . $errorMessage);
        }

        return $data;
    }

    /**
     * Consulta arquivos processados por período específico (copiado do JallCardHelper)
     */
    private static function getArquivosProcessadosPorPeriodo(string $dataInicioStr, string $dataFimStr): ?array
    {
        try {
            $dataInicio = new DateTime($dataInicioStr);
            $dataFim = new DateTime($dataFimStr);
        } catch (Exception $e) {
            LogHelper::logJallCard("Erro ao parsear datas para getArquivosProcessadosPorPeriodo: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'ERROR');
            throw new Exception("Formato de data inválido. Use Y-m-d\TH:i:s.");
        }

        $queryParams = [
            'de' => $dataInicio->format('Y-m-d\TH:i:s'),
            'para' => $dataFim->format('Y-m-d\TH:i:s')
        ];
        return self::makeRequest('/arquivos/processados', 'GET', $queryParams);
    }

    /**
     * Consulta pedido específico por ID (copiado do JallCardHelper)
     */
    private static function getPedidoProducao(string $idPedidoProducao): ?array
    {
        return self::makeRequest("/pedidosProducao/{$idPedidoProducao}");
    }

    public static function executarPorPeriodo(string $dataInicioStr, string $dataFimStr) {
        self::init(); // Garante que a configuração seja carregada
        LogHelper::gerarTraceId();
        LogHelper::logJallCard("Iniciando JallCardProcessadorProvisorio para o período: {$dataInicioStr} a {$dataFimStr}", __CLASS__ . '::' . __FUNCTION__, 'INFO');

        try {
            $dataInicio = new DateTime($dataInicioStr);
            $dataFim = new DateTime($dataFimStr);
        } catch (Exception $e) {
            LogHelper::logJallCard("Erro ao parsear datas: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'CRITICAL');
            exit("Erro: Formato de data inválido. Use Y-m-d H:i:s.\n");
        }

        if ($dataInicio > $dataFim) {
            LogHelper::logJallCard("Data de início ({$dataInicioStr}) é maior que a data de fim ({$dataFimStr}).", __CLASS__ . '::' . __FUNCTION__, 'ERROR');
            exit("Erro: A data de início não pode ser maior que a data de fim.\n");
        }

        $intervaloSemanal = new DateInterval('P7D'); // Intervalo de 7 dias
        $periodoAtualInicio = clone $dataInicio;
        $databaseRepository = new DatabaseRepository(); // Instancia o repositório de banco de dados

        while ($periodoAtualInicio <= $dataFim) {
            $periodoAtualFim = (clone $periodoAtualInicio)->add($intervaloSemanal);

            // Ajusta a data de fim do período atual para não ultrapassar a data de fim geral
            if ($periodoAtualFim > $dataFim) {
                $periodoAtualFim = clone $dataFim;
            }

            $dataInicioSemana = $periodoAtualInicio->format('Y-m-d\TH:i:s');
            $dataFimSemana = $periodoAtualFim->format('Y-m-d\TH:i:s');

            LogHelper::logJallCard("Processando semana: {$dataInicioSemana} a {$dataFimSemana}", __CLASS__ . '::' . __FUNCTION__, 'INFO');
            echo "Processando semana: {$dataInicioSemana} a {$dataFimSemana}...\n";

            // 1. Coletar dados da JallCard para a semana atual (substituindo JallCardFetchJob para este período)
            try {
                $pedidosJallCardRaw = self::getArquivosProcessadosPorPeriodo($dataInicioSemana, $dataFimSemana);
                
                if (empty($pedidosJallCardRaw)) {
                    LogHelper::logJallCard("Nenhum arquivo processado encontrado na JallCard para a semana: {$dataInicioSemana} a {$dataFimSemana}.", __CLASS__ . '::' . __FUNCTION__, 'WARNING');
                    echo "Nenhum arquivo processado encontrado para esta semana.\n";
                    $periodoAtualInicio->add($intervaloSemanal); // Avança para a próxima semana
                    continue; // Pula para a próxima iteração do loop
                }

                // 2. Processar cada pedido da JallCard e salvar/atualizar na tabela vinculacao_jallcard
                foreach ($pedidosJallCardRaw as $pedidoJallCardItem) {
                    $pedidoProducaoJallCard = $pedidoJallCardItem['pedidoProducao'];

                    $vinculacaoExistente = $databaseRepository->findVinculacaoJallCardByPedidoProducao($pedidoProducaoJallCard);

                    if ($vinculacaoExistente) {
                        LogHelper::logJallCard("PedidoProducao {$pedidoProducaoJallCard} já processado na tabela temporária. Ignorando.", __CLASS__ . '::' . __FUNCTION__, 'WARNING');
                        continue;
                    }

                    $detalhesPedido = self::getPedidoProducao($pedidoProducaoJallCard);

                    if (!$detalhesPedido || empty($detalhesPedido['ops'])) {
                        LogHelper::logJallCard("Não foi possível obter detalhes ou OP para PedidoProducao {$pedidoProducaoJallCard}.", __CLASS__ . '::' . __FUNCTION__, 'ERROR');
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

                    $dadosParaInserir = [
                        'pedido_producao_jallcard' => $pedidoProducaoJallCard,
                        'op_jallcard' => $opJallCard,
                        'nome_arquivo_original_jallcard' => $nomeArquivoOriginal,
                        'nome_arquivo_convertido_jallcard' => $nomeArquivoConvertido,
                        'data_processamento_jallcard' => $pedidoJallCardItem['dataProcessamento']
                    ];
                    $databaseRepository->inserirVinculacaoJallCard($dadosParaInserir);
                    LogHelper::logJallCard("Dados inseridos na tabela vinculacao_jallcard para PedidoProducao {$pedidoProducaoJallCard}.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                }

                // 3. Executar JallCardLinkJob para vincular os pedidos recém-inseridos
                LogHelper::logJallCard("Executando JallCardLinkJob para a semana: {$dataInicioSemana} a {$dataFimSemana}", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                echo "Executando JallCardLinkJob...\n";
                JallCardLinkJob::executar(); // Este job opera sobre o banco de dados, não precisa de datas

                // 4. Executar JallCardStatusUpdateJob para atualizar o status dos pedidos
                LogHelper::logJallCard("Executando JallCardStatusUpdateJob para a semana: {$dataInicioSemana} a {$dataFimSemana}", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                echo "Executando JallCardStatusUpdateJob...\n";
                JallCardStatusUpdateJob::executar(); // Este job opera sobre o banco de dados, não precisa de datas

            } catch (Exception $e) {
                LogHelper::logJallCard("Erro durante o processamento da semana {$dataInicioSemana} a {$dataFimSemana}: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'CRITICAL');
                echo "Erro durante o processamento da semana: " . $e->getMessage() . "\n";
            }

            // Avança para a próxima semana
            $periodoAtualInicio->add($intervaloSemanal);
        }

        LogHelper::logJallCard("JallCardProcessadorProvisorio finalizado para o período: {$dataInicioStr} a {$dataFimStr}", __CLASS__ . '::' . __FUNCTION__, 'INFO');
        echo "Processamento concluído para o período.\n";
    }
}

// --- CONFIGURAÇÃO DO PERÍODO DE EXECUÇÃO ---
// Altere estas datas para o período desejado.
// Formato: 'YYYY-MM-DD HH:MM:SS'
$dataInicioDesejada = '2025-11-01 00:00:00'; // Exemplo: 1º de Novembro de 2025
$dataFimDesejada = '2025-12-15 23:59:59';   // Exemplo: 15 de Dezembro de 2025

// Executa o processador
JallCardProcessadorProvisorio::executarPorPeriodo($dataInicioDesejada, $dataFimDesejada);
