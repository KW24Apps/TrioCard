<?php
echo 'Usuário executando: ' . get_current_user();
//teste
// ATENÇÃO: Corrigido o caminho para o repositório do TrioCard
$repoDir = '/home/kw24co49/triocard.kw24.com.br'; 
$secret = 'hF9kL2xV7qP3sY8mZ4bW1cN0'; // MESMA chave configurada no Webhook

// Recebe os dados brutos do POST
$payload = file_get_contents('php://input');

// Cabeçalho enviado pelo GitHub
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

// Gera assinatura local
$hash = 'sha256=' . hash_hmac('sha256', $payload, $secret, false);

// Compara assinatura
if (!hash_equals($hash, $signature)) {
    http_response_code(403);
    exit('Acesso negado. Assinatura inválida.');
}

// Executa a sincronização forçada
// O '2>&1' redireciona a saída de erro para a saída padrão, para que possamos vê-la.
$output = shell_exec("cd {$repoDir} && git fetch --all && git reset --hard origin/main 2>&1");

echo "<pre>$output</pre>";
?>
