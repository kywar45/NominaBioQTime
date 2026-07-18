<?php

require __DIR__ . '/helpers.php';

api_headers();

$configPaths = [
    dirname(__DIR__) . '/public/api-config.json',
    dirname(__DIR__) . '/dist/spa/api-config.json',
];
$publicConfigPath = $configPaths[0];

function read_api_config(string $path): array
{
    if (!file_exists($path)) {
        return ['apiBaseUrl' => 'http://localhost/NominaBioQTime/api'];
    }

    $data = json_decode(file_get_contents($path), true);

    return is_array($data) ? $data : ['apiBaseUrl' => 'http://localhost/NominaBioQTime/api'];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response([
        'ok' => true,
        'config' => read_api_config($publicConfigPath),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
}

$input = json_input();
$apiBaseUrl = trim($input['apiBaseUrl'] ?? '');

if ($apiBaseUrl === '') {
    json_response(['ok' => false, 'message' => 'La ruta de API es obligatoria.'], 422);
}

$apiBaseUrl = rtrim($apiBaseUrl, '/');
$config = ['apiBaseUrl' => $apiBaseUrl];
$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

$saved = false;

foreach ($configPaths as $path) {
    $directory = dirname($path);

    if (!is_dir($directory)) {
        continue;
    }

    if (file_put_contents($path, $json, LOCK_EX) !== false) {
        $saved = true;
    }
}

if (!$saved) {
    json_response(['ok' => false, 'message' => 'No se pudo guardar la configuracion.'], 500);
}

json_response([
    'ok' => true,
    'message' => 'Configuracion guardada correctamente.',
    'config' => $config,
]);
