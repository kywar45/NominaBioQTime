<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

api_headers();

try {
    ensure_companies_table();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = db()->query(
            'SELECT id_empresa, nombre, activo
             FROM empresas
             ORDER BY nombre ASC'
        );

        json_response([
            'ok' => true,
            'companies' => $stmt->fetchAll(),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = company_payload(json_input());
        $stmt = db()->prepare(
            'INSERT INTO empresas (nombre, activo)
             VALUES (:nombre, 1)
             ON DUPLICATE KEY UPDATE
                activo = 1,
                nombre = VALUES(nombre)'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Empresa guardada.',
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $companyId = company_id();
        $data = company_payload(json_input());
        $data['id_empresa'] = $companyId;

        $stmt = db()->prepare(
            'UPDATE empresas
             SET nombre = :nombre
             WHERE id_empresa = :id_empresa'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Empresa actualizada.',
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $stmt = db()->prepare(
            'UPDATE empresas
             SET activo = 0
             WHERE id_empresa = :id_empresa'
        );
        $stmt->execute(['id_empresa' => company_id()]);

        json_response([
            'ok' => true,
            'message' => 'Empresa desactivada.',
        ]);
    }

    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
} catch (Throwable $error) {
    json_response([
        'ok' => false,
        'message' => 'No se pudo procesar la empresa.',
        'detail' => $error->getMessage(),
    ], 500);
}

function ensure_companies_table(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS empresas (
          id_empresa INT UNSIGNED NOT NULL AUTO_INCREMENT,
          nombre VARCHAR(140) NOT NULL,
          activo TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id_empresa),
          UNIQUE KEY empresas_nombre_unique (nombre),
          KEY empresas_activo_idx (activo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function company_id(): int
{
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Empresa no valida.'], 422);
    }

    return $id;
}

function company_payload(array $input): array
{
    $name = trim((string) ($input['nombre'] ?? ''));

    if ($name === '') {
        json_response(['ok' => false, 'message' => 'Captura el nombre de la empresa.'], 422);
    }

    return ['nombre' => $name];
}
