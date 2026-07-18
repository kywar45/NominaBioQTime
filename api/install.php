<?php

require __DIR__ . '/helpers.php';

api_headers();

try {
    $config = require __DIR__ . '/config.php';
    $db = $config['database'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;charset=%s',
        $db['host'],
        $db['port'],
        $db['charset']
    );
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schema);

    json_response([
        'ok' => true,
        'message' => 'Tablas creadas o actualizadas correctamente.',
        'default_user' => 'admin',
        'default_password' => 'admin123',
    ]);
} catch (Throwable $error) {
    json_response([
        'ok' => false,
        'message' => 'No se pudo instalar la base de datos.',
        'detail' => $error->getMessage(),
    ], 500);
}
