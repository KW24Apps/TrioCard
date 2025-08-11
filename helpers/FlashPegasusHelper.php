<?php
namespace Helpers;

require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\LogHelper;

class FlashPegasusHelper
{
    private static $jwtToken = null;
    
    // Base URL da API FlashPegasus
    private static function getBaseUrl() 
    {
        return 'https://api.flashpegasus.com.br'; // URL base conforme documentação
    }
    
    // Obter token JWT para autenticação
    private static function obterTokenJWT()
    {
        if (self::$jwtToken !== null) {
            return self::$jwtToken;
        }
        
        $credentials = [
            'username' => $GLOBALS['ACESSO_AUTENTICADO']['flash_username'] ?? '',
            'password' => $GLOBALS['ACESSO_AUTENTICADO']['flash_password'] ?? ''
        ];
        
        if (empty($credentials['username']) || empty($credentials['password'])) {
            LogHelper::logFlashPegasus("Credenciais FlashPegasus não configuradas", __CLASS__ . '::' . __FUNCTION__);
            return null;
        }
        
        $url = self::getBaseUrl() . '/auth/login';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($credentials));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $resposta = curl_exec($ch);
        $curlErro = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $respostaJson = json_decode($resposta, true);
        
        $traceId = defined('TRACE_ID') ? TRACE_ID : 'sem_trace';
        $resumo = "[$traceId] Auth JWT | HTTP: $httpCode | Erro: $curlErro";
        
        if ($httpCode === 200 && !empty($respostaJson['token'])) {
            self::$jwtToken = $respostaJson['token'];
            $resumo .= " | Token obtido com sucesso";
            LogHelper::logFlashPegasus($resumo, __CLASS__ . '::' . __FUNCTION__);
            return self::$jwtToken;
        } else {
            $resumo .= " | Falha na autenticação";
            LogHelper::logFlashPegasus($resumo, __CLASS__ . '::' . __FUNCTION__);
            return null;
        }
    }
    
    // Método genérico para chamar endpoints da API FlashPegasus
    public static function chamarApi($endpoint, $params = [], $method = 'GET')
    {
        $token = self::obterTokenJWT();
        
        if (!$token) {
            return ['error' => 'Falha na autenticação JWT'];
        }
        
        $url = self::getBaseUrl() . '/' . ltrim($endpoint, '/');
        
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
            $postData = null;
        } else {
            $postData = !empty($params) ? json_encode($params) : null;
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($postData) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            }
        }
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $resposta = curl_exec($ch);
        $curlErro = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $respostaJson = json_decode($resposta, true);
        
        $traceId = defined('TRACE_ID') ? TRACE_ID : 'sem_trace';
        $resumo = "[$traceId] Endpoint: $endpoint | Method: $method | HTTP: $httpCode | Erro: $curlErro";
        
        if (!empty($respostaJson['error'])) {
            $resumo .= " | Erro API: " . $respostaJson['error'];
        }
        
        LogHelper::logFlashPegasus($resumo, __CLASS__ . '::' . __FUNCTION__);
        
        return $respostaJson;
    }
    
    // Consultar dados de entrega no FlashPegasus
    public static function consultarEntrega($clienteId, $cttId, $numEncCli)
    {
        $params = [
            'clienteId' => $clienteId,
            'cttId' => $cttId,
            'numEncCli' => $numEncCli
        ];
        
        return self::chamarApi('/delivery/track', $params, 'GET');
    }
    
    // Consultar múltiplas entregas
    public static function consultarEntregasLote($entregas)
    {
        return self::chamarApi('/delivery/track/batch', ['deliveries' => $entregas], 'POST');
    }
    
    // Limpar token (forçar nova autenticação)
    public static function limparToken()
    {
        self::$jwtToken = null;
    }
}
