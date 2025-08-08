<?php
// index.php principal do projeto TrioCard

// Carrega autoload se existir (para helpers, controllers, etc.)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Carrega o roteador principal
require_once __DIR__ . '/routers/index.php';
