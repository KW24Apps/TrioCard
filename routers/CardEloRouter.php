<?php
// Roteador principal do TrioCard

// Exemplo de roteamento simples para /telenet
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($uri) {
    case '/telenet':
        require_once __DIR__ . '/../controllers/TelenetWebhookController.php';
        $controller = new TelenetWebhookController();
        $controller->executar();
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Rota não encontrada']);
        break;
}
