<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

api_headers();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = db()->query(
            'SELECT id_departamento, nombre_departamento, descripcion, activo
             FROM departamentos
             ORDER BY activo DESC, nombre_departamento ASC'
        );

        json_response([
            'ok' => true,
            'departments' => $stmt->fetchAll(),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = department_payload(json_input());
        $stmt = db()->prepare(
            'INSERT INTO departamentos (nombre_departamento, descripcion, activo)
             VALUES (:nombre_departamento, :descripcion, :activo)'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Departamento creado.',
            'id_departamento' => (int) db()->lastInsertId(),
        ], 201);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = department_payload(json_input());
        $data['id_departamento'] = department_id();
        $stmt = db()->prepare(
            'UPDATE departamentos
             SET nombre_departamento = :nombre_departamento,
                 descripcion = :descripcion,
                 activo = :activo
             WHERE id_departamento = :id_departamento'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Departamento actualizado.',
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $stmt = db()->prepare(
            'UPDATE departamentos
             SET activo = 0
             WHERE id_departamento = :id_departamento'
        );
        $stmt->execute(['id_departamento' => department_id()]);

        json_response([
            'ok' => true,
            'message' => 'Departamento desactivado.',
        ]);
    }

    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
} catch (Throwable $error) {
    json_response([
        'ok' => false,
        'message' => 'No se pudo procesar el departamento.',
        'detail' => $error->getMessage(),
    ], 500);
}

function department_id(): int
{
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Departamento no valido.'], 422);
    }

    return $id;
}

function department_payload(array $input): array
{
    $name = trim($input['nombre_departamento'] ?? '');
    $description = trim($input['descripcion'] ?? '');

    if ($name === '') {
        json_response(['ok' => false, 'message' => 'El nombre del departamento es obligatorio.'], 422);
    }

    return [
        'nombre_departamento' => $name,
        'descripcion' => $description === '' ? null : $description,
        'activo' => array_key_exists('activo', $input) ? (int) (bool) $input['activo'] : 1,
    ];
}
