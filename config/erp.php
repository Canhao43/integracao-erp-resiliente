<?php

return [

    /*
    | Onde está o ERP e quanto esperar por ele.
    */

    'url' => env('ERP_URL', 'http://web/erp-simulado'),

    'timeout' => (int) env('ERP_TIMEOUT', 3),

    // Segundos de espera antes de cada nova tentativa; depois da última, repete a última.
    'backoff' => array_map('intval', explode(',', env('ERP_BACKOFF', '10,30,90,270,810'))),

    'client_id' => env('ERP_CLIENT_ID', 'integracao'),

    'client_secret' => env('ERP_CLIENT_SECRET'),

    // Renova o token estes segundos antes de ele vencer, para não vencer no meio de uma chamada.
    'token_margem' => (int) env('ERP_TOKEN_MARGEM', 30),

    // Por quantos dias a auditoria das chamadas ao ERP é guardada.
    'auditoria_retencao_dias' => (int) env('ERP_AUDITORIA_RETENCAO_DIAS', 90),

    /*
    | ERP simulado: o "outro lado", para a integração ter contra o que ser testada.
    | As probabilidades vão de 0 a 1.
    */

    'simulador' => [
        'ativo' => (bool) env('ERP_SIMULADO_ATIVO', true),
        'falha' => (float) env('ERP_SIMULADO_FALHA', 0),
        'falha_depois_de_gravar' => (float) env('ERP_SIMULADO_FALHA_DEPOIS_DE_GRAVAR', 0),
        'latencia_maxima_ms' => (int) env('ERP_SIMULADO_LATENCIA_MAXIMA_MS', 0),
        // Credencial que o simulador aceita, e por quantos segundos o token dele vale.
        'client_id' => env('ERP_SIMULADO_CLIENT_ID', 'integracao'),
        'client_secret' => env('ERP_SIMULADO_CLIENT_SECRET'),
        'token_validade' => (int) env('ERP_SIMULADO_TOKEN_VALIDADE', 3600),
    ],

];
