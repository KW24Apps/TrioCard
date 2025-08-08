<?php
// Roteador para o webhook Telenet
require_once __DIR__ . '/../controllers/TelenetWebhookController.php';
$controller = new TelenetWebhookController();
$controller->executar();
