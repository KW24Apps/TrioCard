<?php
namespace Helpers;

class LogHelper
{
    private static $config;
    private static $logLevels = [
        'DEBUG' => 100,
        'INFO' => 200,
        'WARNING' => 300,
        'ERROR' => 400,
        'CRITICAL' => 500,
    ];

    public static function init()
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/Variaveis.php';
        }
    }

    // Gera um trace ID único para cada requisição
    public static function gerarTraceId(): void
    {
        if (!defined('TRACE_ID')) {
            $traceId = bin2hex(random_bytes(4));
            define('TRACE_ID', $traceId);
        }
    }

    // Retorna o trace ID atual
    public static function getTraceId(): string
    {
        return defined('TRACE_ID') ? TRACE_ID : 'sem_trace';
    }

    // Registra uma entrada global no log
    public static function registrarEntradaGlobal(string $uri, string $method): void
    {
        self::init();
        $evento = null;
        $aplicacao = defined('NOME_APLICACAO') ? NOME_APLICACAO : 'desconhecida';
        $contexto = 'Index::EntradaGlobal';

        if ($uri === 'clicksignretorno' && $method === 'POST') {
            $body = file_get_contents('php://input');
            $json = json_decode($body, true);
            $evento = $json['event']['name'] ?? null;
        }

        $mensagem = "URI: $uri | MÉTODO: $method";
        if ($evento) $mensagem .= " | EVENTO: $evento";
        
        self::log('entradas', $mensagem, $contexto, 'INFO');
    }

    // Registra um erro global no log
    public static function registrarErroGlobal($errno = null, $errstr = '', $errfile = '', $errline = ''): void
    {
        self::init();
        $aplicacao = defined('NOME_APLICACAO') ? NOME_APLICACAO : 'desconhecida';
        $contexto = 'Index::ErroGlobal';
        $mensagem = "[Erro]";
        if ($errno !== null) {
            $mensagem .= " [$errno] $errstr em $errfile na linha $errline";
        } else {
            $mensagem .= " Erro não identificado";
        }

        self::log('erros_global', $mensagem, $contexto, 'ERROR');
    }

    // Registra uma rota não encontrada no log
    public static function registrarRotaNaoEncontrada(string $uri, string $method, string $arquivoRota): void
    {
        self::init();
        $aplicacao = defined('NOME_APLICACAO') ? NOME_APLICACAO : 'desconhecida';
        $contexto = 'Index::RotaNaoEncontrada';

        $mensagem = "$arquivoRota | Rota não encontrada | URI: $uri | MÉTODO: $method";
        self::log('rotas_nao_encontradas', $mensagem, $contexto, 'WARNING');
    }

    // Registra uma mensagem de log para Bitrix
    public static function logBitrix(string $mensagem, string $contexto = '', string $level = 'INFO'): void
    {
        self::init();
        self::log('bitrix', $mensagem, $contexto, $level);
    }

    // Registra uma mensagem de log para JallCard
    public static function logJallCard(string $mensagem, string $contexto = '', string $level = 'INFO'): void
    {
        self::init();
        self::log('jallcard', $mensagem, $contexto, $level);
    }

    // Registra uma mensagem de log geral para TrioCard
    public static function logTrioCardGeral(string $mensagem, string $contexto = '', string $level = 'INFO'): void
    {
        self::init();
        self::log('triocard_geral', $mensagem, $contexto, $level);
    }

    // Método genérico para registrar logs
    private static function log(string $fileKey, string $mensagem, string $contexto = '', string $level = 'INFO'): void
    {
        self::init();
        $logConfig = self::$config['logging'];
        $minLogLevel = self::$logLevels[$logConfig['log_level']] ?? self::$logLevels['INFO'];
        $currentLogLevel = self::$logLevels[$level] ?? self::$logLevels['INFO'];

        if ($currentLogLevel < $minLogLevel) {
            return; // Não registra logs abaixo do nível configurado
        }

        $arquivoLog = $logConfig['files'][$fileKey] ?? null;

        if (!$arquivoLog) {
            // Fallback para log de erro global se o arquivo de log não for encontrado
            error_log("Configuração de arquivo de log para '{$fileKey}' não encontrada em Variaveis.php", 0);
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $traceId = defined('TRACE_ID') ? TRACE_ID : 'sem_trace';
        $aplicacao = defined('NOME_APLICACAO') ? NOME_APLICACAO : 'desconhecida';

        if (!$contexto) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $classe = $bt[2]['class'] ?? '';
            $funcao = $bt[2]['function'] ?? 'desconhecido';
            $contexto = $classe ? ($classe . '::' . $funcao) : $funcao;
        }

        $linha = "[$timestamp] [$traceId] [$aplicacao] [$level] [$contexto] - $mensagem" . PHP_EOL;
        file_put_contents($arquivoLog, $linha, FILE_APPEND);
    }
}
