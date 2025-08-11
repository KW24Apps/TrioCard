<?php
// Roteador para o webhook Flash
require_once __DIR__ . '/../controllers/FlashWebhookController.php';
$controller = new FlashWebhookController();
$controller->executar();
