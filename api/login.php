<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

api_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
}

$input = json_input();
$username = trim($input['username'] ?? '');
$password = (string) ($input['password'] ?? '');

if ($username === '' || $password === '') {
    json_response(['ok' => false, 'message' => 'Usuario y contrasena son obligatorios.'], 422);
}

try {
    $stmt = db()->prepare(
        'SELECT id, username, password_hash, full_name, is_active
         FROM users
         WHERE username = :username
         LIMIT 1'
    );
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
        json_response(['ok' => false, 'message' => 'Credenciales incorrectas.'], 401);
    }

    $permissionsStmt = db()->prepare(
        'SELECT m.code, m.name, up.can_view, up.can_create, up.can_update, up.can_delete
         FROM user_module_permissions up
         INNER JOIN modules m ON m.id = up.module_id
         WHERE up.user_id = :user_id
         ORDER BY m.name'
    );
    $permissionsStmt->execute(['user_id' => $user['id']]);

    json_response([
        'ok' => true,
        'token' => create_token((int) $user['id']),
        'user' => [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
        ],
        'permissions' => $permissionsStmt->fetchAll(),
    ]);
} catch (Throwable $error) {
    json_response([
        'ok' => false,
        'message' => 'No se pudo iniciar sesion.',
        'detail' => $error->getMessage(),
    ], 500);
}

