<?php

// Obtém a URI da requisição
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptName = dirname($_SERVER['SCRIPT_NAME']);

// Remove o diretório base se a aplicação estiver em um subdiretório
if (strpos($requestUri, $scriptName) === 0) {
    $requestUri = substr($requestUri, strlen($scriptName));
}

// Remove barras duplas e barra final
$requestUri = '/' . trim($requestUri, '/');

// Roteamento
switch ($requestUri) {
    case '/telenet':
        require __DIR__ . '/routers/TelenetRouter.php';
        break;
    default:
        // Se nenhuma rota corresponder, retorna 404
        header("HTTP/1.0 404 Not Found");
        echo "<h1>404 Not Found</h1>";
        echo "<p>The requested URL was not found on this server.</p>";
        break;
}
