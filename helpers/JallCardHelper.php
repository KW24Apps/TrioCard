<?php
namespace Helpers;

require_once __DIR__ . '/LogHelper.php';
use Helpers\LogHelper;
use Exception; // Re-adicionado para resolver o erro de classe desconhecida
use DateTime; // Re-adicionado para resolver o erro de classe desconhecida

class JallCardHelper {

    private static $config;

    public static function init() {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/Variaveis.php';
        }
    }

    /**
     * Método principal para chamadas à API JallCard
     */
    private static function makeRequest(string $endpoint, string $method = 'GET', array $queryParams = [], array $bodyParams = []): ?array
    {
        self::init(); // Garante que a configuração seja carregada
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
     * Consulta arquivos processados por período (últimos 7 dias)
     */
    public static function getArquivosProcessadosUltimos7Dias(): ?array
    {
        $dataFim = new DateTime();
        $dataInicio = (clone $dataFim)->modify('-7 days');

        return self::getArquivosProcessadosPorPeriodo(
            $dataInicio->format('Y-m-d\TH:i:s'),
            $dataFim->format('Y-m-d\TH:i:s')
        );
    }

    /**
     * Consulta arquivos processados por período específico
     */
    public static function getArquivosProcessadosPorPeriodo(string $dataInicioStr, string $dataFimStr): ?array
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
     * Consulta pedido específico por ID
     */
    public static function getPedidoProducao(string $idPedidoProducao): ?array
    {
        return self::makeRequest("/pedidosProducao/{$idPedidoProducao}");
    }

    /**
     * Consulta documentos por ordem de produção
     */
    public static function getDocumentosByOp(string $op, bool $incluirItens = true): ?array
    {
        $queryParams = [
            'op' => $op,
            'incluirItens' => $incluirItens ? 'true' : 'false'
        ];
        return self::makeRequest('/documentos', 'GET', $queryParams);
    }

    /**
     * Consulta ordem específica (importante para status de finalização)
     */
    public static function getOrdemProducao(string $codigoOrdem): ?array
    {
        return self::makeRequest("/ordensProducao/{$codigoOrdem}");
    }

    /**
     * Extrai a data (YYMMDD) e o número de sequência de um nome de arquivo.
     * Ex: "ELOTRI_50929497_JAL_250917_141-20250917-060035.TXT.ICS" -> ['data' => '250917', 'sequencia' => '141']
     * Ex: "ELOTRICL                       250917 141                    .RET" -> ['data' => '250917', 'sequencia' => '141']
     */
    public static function extractMatchKeys(string $fileName): ?array
    {
        // Padrão para JallCard: _YYMMDD_NNN-
        if (preg_match('/_(\d{6})_(\d{3,})-/', $fileName, $matchesJallCard)) {
            return [
                'data' => $matchesJallCard[1],
                'sequencia' => $matchesJallCard[2]
            ];
        }
        // Padrão para Telenet: YYMMDD NNN (com ou sem espaços)
        // Assume que a data e sequência são precedidas por espaços e seguidas por espaços ou fim da string
        if (preg_match('/(\d{6})\s*(\d{3,})/', $fileName, $matchesTelenet)) {
            return [
                'data' => $matchesTelenet[1],
                'sequencia' => $matchesTelenet[2]
            ];
        }

        return null;
    }
}
