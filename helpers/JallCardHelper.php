<?php
namespace Helpers;

require_once __DIR__ . '/LogHelper.php';
use Helpers\LogHelper;

class JallCardHelper {
    
    // Configurações da API JallCard
    private const API_CONFIG = [
        'producao' => [
            'base_url' => 'https://api-sp.jallcard.com.br:8143',
            'usuario' => '', // Configurar credenciais
            'senha' => ''
        ],
        'homologacao' => [
            'base_url' => 'https://api-hom.jallcard.com.br:8144',
            'usuario' => '', // Configurar credenciais  
            'senha' => ''
        ]
    ];
    
    private static $ambiente = 'producao'; // ou 'homologacao'
    
    /**
     * Consulta arquivos processados por período
     * Útil para verificar se arquivo da Telenet foi processado
     */
    public static function consultarArquivosProcessados($dataInicio = null, $dataFim = null) {
        $params = [];
        
        if ($dataInicio) {
            $params['de'] = $dataInicio; // formato: Y-m-d\TH:i:s
        }
        if ($dataFim) {
            $params['para'] = $dataFim;
        }
        
        return self::chamarApi('/api/arquivos/processados', 'GET', $params);
    }
    
    /**
     * Consulta pedidos de produção
     * Principal método para integração com Telenet
     */
    public static function consultarPedidosProducao() {
        return self::chamarApi('/api/pedidosProducao', 'GET');
    }
    
    /**
     * Consulta pedido específico por ID
     */
    public static function consultarPedidoPorId($pedidoId) {
        return self::chamarApi("/api/pedidosProducao/{$pedidoId}", 'GET');
    }
    
    /**
     * Consulta ordens de produção (para verificar status)
     */
    public static function consultarOrdensProducao() {
        return self::chamarApi('/api/ordensProducao', 'GET');
    }
    
    /**
     * Consulta ordem específica (importante para status de finalização)
     */
    public static function consultarOrdemPorCodigo($codigoOrdem) {
        return self::chamarApi("/api/ordensProducao/{$codigoOrdem}", 'GET');
    }
    
    /**
     * Consulta ordens já gravadas (prontas para expedição)
     */
    public static function consultarOrdensGravadas() {
        return self::chamarApi('/api/ordensProducao/gravadas', 'GET');
    }
    
    /**
     * Consulta expedições (para obter dados de entrega)
     */
    public static function consultarExpedicoes() {
        return self::chamarApi('/api/expedicoes', 'GET');
    }
    
    /**
     * Consulta expedição específica (dados para Flash)
     */
    public static function consultarExpedicaoPorCodigo($codigoExpedicao) {
        return self::chamarApi("/api/expedicoes/{$codigoExpedicao}", 'GET');
    }
    
    /**
     * Consulta documentos por lote de expedição
     * Útil para rastrear documentos específicos
     */
    public static function consultarDocumentosPorLote($loteExpedicao, $incluirItens = true) {
        $params = [
            'loteExpedicao' => $loteExpedicao,
            'incluirItens' => $incluirItens
        ];
        
        return self::chamarApi('/api/documentos', 'GET', $params);
    }
    
    /**
     * Consulta documentos por ordem de produção
     */
    public static function consultarDocumentosPorOP($codigoOP, $incluirItens = true) {
        $params = [
            'op' => $codigoOP,
            'incluirItens' => $incluirItens
        ];
        
        return self::chamarApi('/api/documentos', 'GET', $params);
    }
    
    /**
     * Consulta estoque de insumos
     */
    public static function consultarEstoque($codigoInsumo = null) {
        $params = [];
        if ($codigoInsumo) {
            $params['codigoInsumo'] = $codigoInsumo;
        }
        
        return self::chamarApi('/api/estoque', 'GET', $params);
    }
    
    /**
     * Método principal para chamadas à API
     */
    private static function chamarApi($endpoint, $metodo = 'GET', $params = []) {
        $config = self::API_CONFIG[self::$ambiente];
        $url = $config['base_url'] . $endpoint;
        
        // Adicionar parâmetros para GET
        if ($metodo === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        // Log da chamada (usando método existente)
        error_log("JallCard API: {$metodo} {$url}");
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($config['usuario'] . ':' . $config['senha']),
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => false, // Para ambiente de teste
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        // Para POST/PUT, adicionar dados no body
        if (in_array($metodo, ['POST', 'PUT']) && !empty($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("JallCard cURL Error: {$error}");
            return ['error' => "Erro de conexão: {$error}"];
        }
        
        if ($httpCode !== 200) {
            error_log("JallCard HTTP {$httpCode}: {$response}");
            return ['error' => "HTTP {$httpCode}", 'response' => $response];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JallCard JSON Error: " . json_last_error_msg());
            return ['error' => 'Resposta inválida da API'];
        }
        
        error_log("JallCard API: Resposta recebida com sucesso");
        return $data;
    }
    
    /**
     * Configura ambiente (produção/homologação)
     */
    public static function configurarAmbiente($ambiente) {
        if (in_array($ambiente, ['producao', 'homologacao'])) {
            self::$ambiente = $ambiente;
        }
    }
    
    /**
     * Método de integração: busca dados para Flash baseado no protocolo Telenet
     */
    public static function buscarDadosParaFlash($protocoloTelenet) {
        // 1. Primeiro, buscar pedidos recentes
        $pedidos = self::consultarPedidosProducao();
        
        if (!$pedidos || isset($pedidos['error'])) {
            return ['error' => 'Erro ao consultar pedidos de produção'];
        }
        
        // 2. Para cada pedido, verificar ordens associadas
        foreach ($pedidos as $pedido) {
            if (isset($pedido['ops']) && is_array($pedido['ops'])) {
                foreach ($pedido['ops'] as $codigoOP) {
                    $ordem = self::consultarOrdemPorCodigo($codigoOP);
                    
                    if ($ordem && $ordem['status'] === 'FINALIZADA') {
                        // 3. Buscar dados de expedição
                        $expedicoes = self::consultarExpedicoes();
                        
                        foreach ($expedicoes as $expedicao) {
                            if (isset($expedicao['lotes'])) {
                                return [
                                    'pedido_id' => $pedido['id'],
                                    'ordem_codigo' => $codigoOP,
                                    'expedicao' => $expedicao,
                                    'protocolo_telenet' => $protocoloTelenet,
                                    'dados_flash' => [
                                        'entregadora' => $expedicao['entregadora'] ?? 'FLASH',
                                        'codigo_expedicao' => $expedicao['codigo'],
                                        'data_expedicao' => $expedicao['data']
                                    ]
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        return ['error' => 'Nenhuma ordem finalizada encontrada'];
    }
}

// Adicionar método ao LogHelper se não existir
// Usar error_log temporariamente até integrar com LogHelper existente

?>
