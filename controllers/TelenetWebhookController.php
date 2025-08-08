<?php
// Controller para receber o webhook da Telenet
class TelenetWebhookController {
    public function executar() {
        header('Content-Type: application/json');
        $input = file_get_contents('php://input');
        $dados = json_decode($input, true);

        if (!$dados) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'JSON inválido ou não enviado'
            ]);
            return;
        }

        // Aqui pode validar campos se quiser, ou só logar
        // file_put_contents(__DIR__ . '/../logs/telenet_webhook.log', print_r($dados, true), FILE_APPEND);

        // Resposta padrão
        echo json_encode([
            'success' => true,
            'message' => 'Dados recebidos com sucesso',
            'protocolo' => $dados['protocolo'] ?? null
        ]);
    }
}
