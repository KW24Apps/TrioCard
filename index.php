<?php
// index.php principal do projeto TrioCard

// Carrega autoload se existir (para helpers, controllers, etc.)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Decide qual router carregar com base na URL
$uri = $_SERVER['REQUEST_URI'] ?? '';

if (stripos($uri, 'telenet') !== false) {
    require_once __DIR__ . '/routers/TelenetRouter.php';
    exit;
}

// Exemplo para outros webhooks/rotas futuras:
// if (stripos($uri, 'geocard') !== false) {
//     require_once __DIR__ . '/routers/GeoCardRouter.php';
//     exit;
// }

// Se não encontrou nenhuma rota conhecida
http_response_code(404);
echo json_encode(['success' => false, 'error' => 'Rota não encontrada']);
