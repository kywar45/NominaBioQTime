<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

api_headers();

try {
    ensure_vacation_assignments_table();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = db()->query(
            'SELECT
                v.id_vacacion,
                v.empleado_id,
                e.nombre AS empleado,
                v.dias_vacaciones,
                v.fecha_inicio,
                v.fecha_fin,
                v.notas
             FROM vacaciones_asignaciones v
             LEFT JOIN empleados e
               ON e.id COLLATE utf8mb4_unicode_ci = v.empleado_id COLLATE utf8mb4_unicode_ci
             ORDER BY v.fecha_inicio DESC, e.nombre ASC'
        );

        json_response([
            'ok' => true,
            'vacations' => $stmt->fetchAll(),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = vacation_assignment_payload(json_input());
        $stmt = db()->prepare(
            'INSERT INTO vacaciones_asignaciones (
                empleado_id,
                dias_vacaciones,
                fecha_inicio,
                fecha_fin,
                notas
             ) VALUES (
                :empleado_id,
                :dias_vacaciones,
                :fecha_inicio,
                :fecha_fin,
                :notas
             )'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Vacaciones asignadas.',
            'id_vacacion' => (int) db()->lastInsertId(),
        ], 201);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = vacation_assignment_payload(json_input());
        $data['id_vacacion'] = vacation_assignment_id();
        $stmt = db()->prepare(
            'UPDATE vacaciones_asignaciones
             SET empleado_id = :empleado_id,
                 dias_vacaciones = :dias_vacaciones,
                 fecha_inicio = :fecha_inicio,
                 fecha_fin = :fecha_fin,
                 notas = :notas
             WHERE id_vacacion = :id_vacacion'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Vacaciones actualizadas.',
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $stmt = db()->prepare('DELETE FROM vacaciones_asignaciones WHERE id_vacacion = :id_vacacion');
        $stmt->execute(['id_vacacion' => vacation_assignment_id()]);

        json_response([
            'ok' => true,
            'message' => 'Vacaciones eliminadas.',
        ]);
    }

    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
} catch (Throwable $error) {
    json_response([
        'ok' => false,
        'message' => 'No se pudo procesar vacaciones.',
        'detail' => $error->getMessage(),
    ], 500);
}

function ensure_vacation_assignments_table(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS vacaciones_asignaciones (
          id_vacacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
          empleado_id VARCHAR(60) NOT NULL,
          dias_vacaciones DECIMAL(8,2) NOT NULL DEFAULT 0,
          fecha_inicio DATE NOT NULL,
          fecha_fin DATE NOT NULL,
          notas TEXT NULL,
          activo TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id_vacacion),
          KEY vacaciones_asignaciones_empleado_idx (empleado_id),
          KEY vacaciones_asignaciones_fechas_idx (fecha_inicio, fecha_fin),
          KEY vacaciones_asignaciones_activo_idx (activo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function vacation_assignment_id(): int
{
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Registro de vacaciones no valido.'], 422);
    }

    return $id;
}

function vacation_assignment_payload(array $input): array
{
    $employeeId = trim((string) ($input['empleado_id'] ?? ''));
    $days = (float) ($input['dias_vacaciones'] ?? 0);
    $startDate = normalize_date($input['fecha_inicio'] ?? null, 'La fecha de inicio no es valida.');
    $endDate = normalize_date($input['fecha_fin'] ?? null, 'La fecha final no es valida.');
    $notes = trim($input['notas'] ?? '');

    if ($employeeId === '') {
        json_response(['ok' => false, 'message' => 'Selecciona un empleado.'], 422);
    }

    if ($days <= 0) {
        json_response(['ok' => false, 'message' => 'Los dias de vacaciones deben ser mayores a cero.'], 422);
    }

    if ($endDate < $startDate) {
        json_response(['ok' => false, 'message' => 'La fecha final no puede ser menor a la inicial.'], 422);
    }

    return [
        'empleado_id' => $employeeId,
        'dias_vacaciones' => $days,
        'fecha_inicio' => $startDate,
        'fecha_fin' => $endDate,
        'notas' => $notes === '' ? null : $notes,
    ];
}

function normalize_date(mixed $value, string $message): string
{
    $date = trim((string) ($value ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok' => false, 'message' => $message], 422);
    }

    return $date;
}
