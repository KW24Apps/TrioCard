<?php

return [
    'jallcard' => [
        'base_url' => getenv('JALLCARD_BASE_URL') ?: 'https://api-pr.jallcard.com.br:8143/api',
        'credentials' => getenv('JALLCARD_CREDENTIALS') ?: 'poolempresarial.apiproducaopr:P00lEmp!2025#xR9qT',
        'ssl_verify_peer' => getenv('JALLCARD_SSL_VERIFY_PEER') ?: false
    ],
    'bitrix' => [
        'webhook_base' => getenv('BITRIX_WEBHOOK_BASE') ?: 'https://triocard.bitrix24.com.br/rest/1/6b9h9uuclndlz6le/',
        'entity_type_id_deal' => 1042,
        'category_deal' => 14,
        'user_id_comments' => 36, // ID do usuário para comentários na timeline
        'mapeamento_campos_telenet' => [
            'nome_arquivo' => 'ufCrm8_1756758446',
            'protocolo' => 'ufCrm8_1756758502',
            'mensagem' => 'ufCrm8_1756758530', 
            'cliente' => 'ufCrm8_1756758572',
            'cnpj' => 'ufCrm8_1756758552',
            'data_solicitacao' => 'ufCrm8_1756758589',
            'data_retorno_solicitacao' => 'ufCrm8_1756758616',
            'codigo_cliente' => '',
            'quant_registros' => '',
            'cnpj_consulta_empresa' => 'ufcrm_1641693445101'
        ],
        'mapeamento_campos_jallcard' => [
            'op_jallcard' => 'ufCrm8_1758208231', // Ordem de Produção
            'pedido_producao_jallcard' => 'ufCrm8_1758208290', // ID Pedido Produção Jall Card
            'campo_retorno_telenet' => 'ufCrm8_1756758530', // Campo retorno da Telenet
            'nome_transportadora' => 'ufCrm8_1758216263', // UF do campo Nome da Transportadora no Bitrix
            'id_rastreamento' => 'ufCrm8_1758216333'      // UF do campo ID Rastreamento Transportadora no Bitrix
        ]
    ],
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'dbname' => getenv('DB_NAME') ?: 'kw24co49_TrioCard',
        'user' => getenv('DB_USER') ?: 'kw24co49_kw24',
        'password' => getenv('DB_PASSWORD') ?: 'BlFOyf%X}#jXwrR-vi'
    ],
    'logging' => [
        'log_level' => getenv('LOG_LEVEL') ?: 'INFO', // Nível mínimo de log a ser registrado (DEBUG, INFO, WARNING, ERROR, CRITICAL)
        'files' => [
            'triocard_geral' => __DIR__ . '/../logs/triocard_geral.log',
            'bitrix' => __DIR__ . '/../logs/bitrix.log',
            'jallcard' => __DIR__ . '/../logs/jallcard.log',
            'entradas' => __DIR__ . '/../logs/entradas.log',
            'erros_global' => __DIR__ . '/../logs/erros_global.log',
            'rotas_nao_encontradas' => __DIR__ . '/../logs/logRotasNaoEncontradas.log',
        ]
    ]
];
