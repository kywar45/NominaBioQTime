<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

api_headers();

try {
    ensure_banks_table();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = db()->query(
            'SELECT id_banco, nombre, activo
             FROM bancos
             ORDER BY nombre ASC'
        );

        json_response([
            'ok' => true,
            'banks' => $stmt->fetchAll(),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = bank_payload(json_input());
        $stmt = db()->prepare(
            'INSERT INTO bancos (nombre, activo)
             VALUES (:nombre, 1)
             ON DUPLICATE KEY UPDATE
                activo = 1,
                nombre = VALUES(nombre)'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Banco guardado.',
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $bankId = bank_id();
        $data = bank_payload(json_input());
        $data['id_banco'] = $bankId;

        $stmt = db()->prepare(
            'UPDATE bancos
             SET nombre = :nombre
             WHERE id_banco = :id_banco'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Banco actualizado.',
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $stmt = db()->prepare(
            'UPDATE bancos
             SET activo = 0
             WHERE id_banco = :id_banco'
        );
        $stmt->execute(['id_banco' => bank_id()]);

        json_response([
            'ok' => true,
            'message' => 'Banco desactivado.',
        ]);
    }

    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
} catch (Throwable $error) {
    json_response([
        'ok' => false,
        'message' => 'No se pudo procesar el banco.',
        'detail' => $error->getMessage(),
    ], 500);
}

function ensure_banks_table(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS bancos (
          id_banco INT UNSIGNED NOT NULL AUTO_INCREMENT,
          nombre VARCHAR(120) NOT NULL,
          activo TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id_banco),
          UNIQUE KEY bancos_nombre_unique (nombre),
          KEY bancos_activo_idx (activo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function bank_id(): int
{
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Banco no valido.'], 422);
    }

    return $id;
}

function bank_payload(array $input): array
{
    $name = trim((string) ($input['nombre'] ?? ''));

    if ($name === '') {
        json_response(['ok' => false, 'message' => 'Captura el nombre del banco.'], 422);
    }

    return ['nombre' => $name];
}
