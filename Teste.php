<?php

// ================================================
// FUNÇÃO HTTP POST COM DEBUG SIMPLES
// ================================================
function http_post_debug($url, $headers, $body) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HEADER         => true
    ]);

    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$code, $response];
}


// ================================================
// CONFIG
// ================================================
$logFile = __DIR__ . '/TESTE_LOG.md';

// recria o log do zero
file_put_contents($logFile, "# LOG FLASHPEGASUS\n\n");

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

// separar header/body
$partes    = explode("\r\n\r\n", $rawToken, 2);
$tokenBody = $partes[1] ?? "";

log_write("## TOKEN");
log_write("HTTP CODE: **$codeToken**");

$tokenJson = json_decode($tokenBody, true);

if (!$tokenJson || !isset($tokenJson["access_token"])) {
    log_write("Token NÃO GERADO");
    log_write("Resposta:\n```\n$tokenBody\n```");
    exit("Erro ao gerar token! TESTE_LOG.md criado.\n");
}

$accessToken = $tokenJson["access_token"];

log_write("Token Gerado:\n```\n$accessToken\n```\n");


// ================================================
// 3) CONSULTA
// ================================================
$numeroTeste = "2510850235001";   // ajuste aqui quando quiser

$payloadConsulta = json_encode([
    "clienteId" => 5917,
    "cttId"     => [14913],
    "numEncCli" => [$numeroTeste]
]);

$headersConsulta = [
    "Authorization: $accessToken",
    "Content-Type: application/json; charset=utf-8",
    "Accept: application/json"
];

list($codeCons, $rawCons) = http_post_debug($urlConsulta, $headersConsulta, $payloadConsulta);

// separar body
$partes2  = explode("\r\n\r\n", $rawCons, 2);
$consBody = $partes2[1] ?? "";

log_write("## CONSULTA");
log_write("Número consultado: `$numeroTeste`");
log_write("HTTP CODE: **$codeCons**");

if (trim($consBody) === "") {
    log_write("Body:\n```\n[VAZIO]\n```");
} else {
    log_write("Body:\n```\n$consBody\n```");
}

echo "Log TESTE_LOG.md atualizado.\n";
