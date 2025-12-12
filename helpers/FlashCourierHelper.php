<?php

namespace Helpers;

require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\LogHelper;

class FlashCourierHelper
{
    private static $config;
    private static $accessToken = null;
    private static $tokenExpiry = 0; // Timestamp de expiração do token

    public static function init()
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/Variaveis.php';
        }
    }

    /**
     * Obtém o token de acesso JWT da Flash Courier.
     * O token é armazenado e reutilizado até expirar (24 horas).
     *
     * @return string|null O token de acesso JWT ou null em caso de falha.
     */
    private static function getAccessToken(): ?string
    {
        self::init();
        $flashConfig = self::$config['flash_courier'];

        // Verifica se o token ainda é válido
        if (self::$accessToken !== null && time() < self::$tokenExpiry) {
            LogHelper::logTrioCardGeral("Reutilizando token Flash Courier existente.", __CLASS__ . '::' . __FUNCTION__, 'DEBUG');
            return self::$accessToken;
        }

        $login = $flashConfig['login'];
        $senha = $flashConfig['senha'];
        $authKey = $flashConfig['auth_key'];
        $tokenUrl = $flashConfig['token_url'];
        $sslVerifyPeer = $flashConfig['ssl_verify_peer'];

        if (empty($login) || empty($senha) || empty($authKey)) {
            LogHelper::logTrioCardGeral("Credenciais Flash Courier (login, senha ou auth_key) não configuradas para produção.", __CLASS__ . '::' . __FUNCTION__, 'CRITICAL');
            return null;
        }

        $payload = json_encode([
            "login" => $login,
            "senha" => $senha
        ]);

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerifyPeer);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerifyPeer ? 2 : 0); // Corrigido para usar 2 ou 0

        $resposta = curl_exec($ch);
        $curlErro = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $traceId = LogHelper::getTraceId();
        $resumo = "[$traceId] Flash Courier Auth | HTTP: $httpCode | Erro cURL: $curlErro";

        if ($httpCode === 200) {
            $respostaJson = json_decode($resposta, true);
            if (isset($respostaJson['access_token'])) {
                self::$accessToken = $respostaJson['access_token'];
                self::$tokenExpiry = time() + ($respostaJson['expires_in'] ?? 86400) - 60; // 1 minuto de margem
                LogHelper::logTrioCardGeral($resumo . " | Token obtido com sucesso.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                return self::$accessToken;
            } else {
                LogHelper::logTrioCardGeral($resumo . " | Resposta de autenticação inválida: " . $resposta, __CLASS__ . '::' . __FUNCTION__, 'ERROR');
            }
        } else {
            LogHelper::logTrioCardGeral($resumo . " | Falha na autenticação. Resposta: " . $resposta, __CLASS__ . '::' . __FUNCTION__, 'ERROR');
        }
        return null;
    }

    /**
     * Consulta o rastreamento de objetos na API da Flash Courier.
     *
     * @param array $numEncCli Array de números de encomenda do cliente (ARs).
     * @return array|null Os dados de rastreamento ou null em caso de falha.
     */
    public static function consultarRastreamento(array $numEncCli): ?array
    {
        self::init();
        $flashConfig = self::$config['flash_courier'];

        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            LogHelper::logTrioCardGeral("Não foi possível obter o token de acesso para Flash Courier.", __CLASS__ . '::' . __FUNCTION__, 'ERROR');
            return null;
        }

        $clienteId = $flashConfig['cliente_id'];
        $cttId = $flashConfig['ctt_id']; // Já é um array devido ao explode em Variaveis.php
        $consultaUrl = $flashConfig['consulta_url'];
        $sslVerifyPeer = $flashConfig['ssl_verify_peer'];

        if (empty($numEncCli)) {
            LogHelper::logTrioCardGeral("Nenhum número de encomenda fornecido para consulta de rastreamento.", __CLASS__ . '::' . __FUNCTION__, 'WARNING');
            return null;
        }

        $body = json_encode([
            "clienteId" => $clienteId,
            "cttId" => $cttId,
            "numEncCli" => $numEncCli
        ]);

        $ch = curl_init($consultaUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerifyPeer);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerifyPeer ? 2 : 0); // Corrigido para usar 2 ou 0

        $resposta = curl_exec($ch);
        $curlErro = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $traceId = LogHelper::getTraceId();
        $resumo = "[$traceId] Flash Courier Consulta Rastreamento | HTTP: $httpCode | Erro cURL: $curlErro | ARs: " . implode(', ', $numEncCli);

        if ($httpCode === 200) {
            $respostaJson = json_decode($resposta, true);
            if (isset($respostaJson['statusRetorno']) && $respostaJson['statusRetorno'] === '00') {
                LogHelper::logTrioCardGeral($resumo . " | Consulta de rastreamento bem-sucedida.", __CLASS__ . '::' . __FUNCTION__, 'INFO');
                return $respostaJson['hawbs'] ?? [];
            } else {
                LogHelper::logTrioCardGeral($resumo . " | Falha na consulta de rastreamento. Status Retorno: " . ($respostaJson['statusRetorno'] ?? 'N/A') . " | Resposta: " . $resposta, __CLASS__ . '::' . __FUNCTION__, 'ERROR');
            }
        } else {
            LogHelper::logTrioCardGeral($resumo . " | Erro HTTP na consulta de rastreamento. Resposta: " . $resposta, __CLASS__ . '::' . __FUNCTION__, 'ERROR');
        }
        return null;
    }
}
