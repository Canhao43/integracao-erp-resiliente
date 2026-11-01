<?php

return [

    /*
    | Onde está o ERP e quanto esperar por ele.
    */

    'url' => env('ERP_URL', 'http://web/erp-simulado'),

    'timeout' => (int) env('ERP_TIMEOUT', 3),

    // Segundos de espera antes de cada nova tentativa; depois da última, repete a última.
    'backoff' => array_map('intval', explode(',', env('ERP_BACKOFF', '10,30,90,270,810'))),

    /*
    | ERP simulado: o "outro lado", para a integração ter contra o que ser testada.
    | As probabilidades vão de 0 a 1.
    */

    'simulador' => [
        'ativo' => (bool) env('ERP_SIMULADO_ATIVO', true),
        'falha' => (float) env('ERP_SIMULADO_FALHA', 0),
        'falha_depois_de_gravar' => (float) env('ERP_SIMULADO_FALHA_DEPOIS_DE_GRAVAR', 0),
        'latencia_maxima_ms' => (int) env('ERP_SIMULADO_LATENCIA_MAXIMA_MS', 0),
    ],

];
