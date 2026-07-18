<?php

return [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'checador',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'token_secret' => 'cambia-esta-clave-en-produccion',
        'token_ttl_seconds' => 28800,
    ],
];

