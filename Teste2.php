<?php

// ================================================
// FUNÇÃO HTTP POST COM DEBUG SIMPLES
// ================================================
function http_post_debug($url, $headers, $body, $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HEADER         => true,
        CURLOPT_SSL_VERIFYPEER => false, // Para testes, pode ser útil desativar
        CURLOPT_SSL_VERIFYHOST => 0      // Corrigido para compatibilidade com PHP mais antigo
    ]);

    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile); // Salva cookies
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile); // Envia cookies
    }

    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$code, $response];
}


// ================================================
// CONFIG
// ================================================
$logFile = __DIR__ . '/TESTE_LOG_LOTE.md';

// recria o log do zero
file_put_contents($logFile, "# LOG FLASHPEGASUS - TESTE EM LOTE\n\n");

// função pra escrever
function log_write($t) {
    global $logFile;
    file_put_contents($logFile, $t . "\n", FILE_APPEND);
}

$urlToken    = "https://webservice.flashpegasus.com.br/FlashPegasus/rest/api/v1/token";
$urlConsulta = "https://webservice.flashpegasus.com.br/FlashPegasus/rest/padrao/v2/consulta";

// >>> CREDENCIAIS OFICIAIS QUE JÁ FUNCIONAVAM NO TEU CÓDIGO <<<
$login = 'ws.personalcardg';
$senha = 'UwVmwOiNdHC)';
$hmac  = hash_hmac('sha256', $senha, $login);


// ================================================
// 1) HASH
// ================================================
log_write("## HASH");
log_write("Login: **$login**");
log_write("Senha: **$senha**");
log_write("Hash (HMAC-SHA256):\n```\n$hmac\n```\n");


// ================================================
// 2) TOKEN
// ================================================
$headersToken = [
    "Authorization: $hmac",
    "Content-Type: application/json; charset=utf-8",
    "Accept: application/json"
];

$payloadToken = json_encode([
    "login" => $login,
    "senha" => $senha
]);

list($codeToken, $rawToken) = http_post_debug($urlToken, $headersToken, $payloadToken);

log_write("## TOKEN");
log_write("HTTP CODE: **$codeToken**");
log_write("Resposta bruta do Token:\n```\n$rawToken\n```\n"); // Log da resposta bruta

// Encontrar o início do JSON (primeira chave de abertura '{')
$jsonStart = strpos($rawToken, '{');
$tokenBody = '';
if ($jsonStart !== false) {
    $tokenBody = substr($rawToken, $jsonStart);
    // Limpeza agressiva de caracteres de controle no corpo do JSON
    $tokenBody = preg_replace('/[[:cntrl:]]/', '', $tokenBody);
    $tokenBody = trim($tokenBody); // Trim final para espaços em branco
}

$tokenJson = json_decode($tokenBody, true);

if (!$tokenJson || !isset($tokenJson["access_token"])) {
    log_write("Token NÃO GERADO");
    log_write("Resposta (body, após extração e limpeza):\n```\n$tokenBody\n```");
    exit("Erro ao gerar token! TESTE_LOG_LOTE.md criado.\n");
}

$accessToken = $tokenJson["access_token"];
// Limpeza adicional do token, caso ainda haja caracteres de controle
$accessToken = preg_replace('/[[:cntrl:]]/', '', $accessToken);

log_write("Token Gerado (limpo e extraído com precisão):\n```\n$accessToken\n```\n");


// ================================================
// 3) CONSULTA EM LOTE
// ================================================
$numerosTesteLote = [
    "2510840126001", "2510847134001", "2510847141001", "2510848135001",
    "2510848137001", "2510848140001", "2510848142001", "2510850129001",
    "2510850132001", "2510850136001", "2510850138001", "2510856128001",
    "2510856130001", "2510856139001", "2510857131001", "2510858133001",
    "2510859125001", "2510866127001"
];

$payloadConsulta = json_encode([
    "clienteId" => 5917,
    "cttId"     => [14913],
    "numEncCli" => $numerosTesteLote
]);

$headersConsulta = [
    "Content-Type: application/json; charset=utf-8",
    "Accept: application/json"
];

log_write("Access Token gerado (mas não usado na consulta):\n```\n$accessToken\n```\n"); // Log do token gerado
log_write("Payload da Consulta:\n```\n" . $payloadConsulta . "\n```\n"); // Log do payload da consulta
log_write("Headers da Consulta:\n```\n" . json_encode($headersConsulta) . "\n```\n"); // Log dos headers da consulta

$cookieFile = __DIR__ . '/flash_cookies.txt'; // Arquivo para armazenar cookies
list($codeCons, $rawCons) = http_post_debug($urlConsulta, $headersConsulta, $payloadConsulta, $cookieFile);

// separar body
$partes2  = explode("\r\n\r\n", $rawCons, 2);
$consBody = isset($partes2[1]) ? $partes2[1] : ""; // Corrigido para PHP mais antigo

log_write("## CONSULTA EM LOTE");
log_write("Números consultados: `" . implode(", ", $numerosTesteLote) . "`");
log_write("HTTP CODE: **$codeCons**");

if (trim($consBody) === "") {
    log_write("Body:\n```\n[VAZIO]\n```");
} else {
    log_write("Body:\n```\n$consBody\n```");
}

echo "Log TESTE_LOG_LOTE.md atualizado.\n";
