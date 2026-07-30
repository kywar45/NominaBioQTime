<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

api_headers();

try {
    ensure_employee_config_columns();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = db()->query(
            'SELECT
                e.id,
                e.nombre,
                e.fecha_ingreso,
                e.activo,
                config.id AS configuracion_id,
                config.departamento_id,
                d.nombre_departamento AS departamento,
                config.turno_id,
                t.nombre_turno AS turno,
                config.sueldo_base,
                config.tipo_sueldo,
                config.dia_libre,
                config.fecha_inicio AS configuracion_fecha_inicio
             FROM empleados e
             LEFT JOIN (
                SELECT c.*
                FROM empleado_configuracion_laboral c
                INNER JOIN (
                    SELECT empleado_id, MAX(id) AS id
                    FROM empleado_configuracion_laboral
                    WHERE activo = 1
                      AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
                    GROUP BY empleado_id
                ) latest ON latest.id = c.id
             ) config ON config.empleado_id = e.id
             LEFT JOIN departamentos d ON d.id_departamento = config.departamento_id
             LEFT JOIN turnos t ON t.id_turno = config.turno_id
             ORDER BY e.nombre ASC'
        );

        json_response([
            'ok' => true,
            'employees' => $stmt->fetchAll(),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $employeeId = employee_id();
        $data = employee_config_payload(json_input());
        $data['empleado_id'] = $employeeId;

        $pdo = db();
        $pdo->beginTransaction();

        $employeeStmt = $pdo->prepare(
            'UPDATE empleados
             SET fecha_ingreso = :fecha_ingreso,
                 activo = :activo
             WHERE id = :empleado_id'
        );
        $employeeStmt->execute([
            'fecha_ingreso' => $data['fecha_ingreso_empleado'],
            'activo' => $data['empleado_activo'],
            'empleado_id' => $employeeId,
        ]);

        $currentStmt = $pdo->prepare(
            'SELECT id
             FROM empleado_configuracion_laboral
             WHERE empleado_id = :empleado_id
               AND activo = 1
               AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
             ORDER BY id DESC
             LIMIT 1'
        );
        $currentStmt->execute(['empleado_id' => $employeeId]);
        $current = $currentStmt->fetch();

        if ($current) {
            $data['configuracion_id'] = (int) $current['id'];
            $configStmt = $pdo->prepare(
                'UPDATE empleado_configuracion_laboral
                 SET departamento_id = :departamento_id,
                     turno_id = :turno_id,
                     sueldo_base = :sueldo_base,
                     tipo_sueldo = :tipo_sueldo,
                     dia_libre = :dia_libre,
                     fecha_inicio = :fecha_inicio,
                     fecha_fin = NULL,
                     activo = 1
                 WHERE id = :configuracion_id'
            );
            $configStmt->execute(config_statement_payload($data, true));
        } else {
            $configStmt = $pdo->prepare(
                'INSERT INTO empleado_configuracion_laboral (
                    empleado_id,
                    departamento_id,
                    turno_id,
                    sueldo_base,
                    tipo_sueldo,
                    dia_libre,
                    fecha_inicio,
                    fecha_fin,
                    activo
                 ) VALUES (
                    :empleado_id,
                    :departamento_id,
                    :turno_id,
                    :sueldo_base,
                    :tipo_sueldo,
                    :dia_libre,
                    :fecha_inicio,
                    NULL,
                    1
                 )'
            );
            $configStmt->execute(config_statement_payload($data));
        }

        $pdo->commit();

        json_response([
            'ok' => true,
            'message' => 'Empleado actualizado.',
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $stmt = db()->prepare(
            'UPDATE empleados
             SET activo = 0
             WHERE id = :empleado_id'
        );
        $stmt->execute(['empleado_id' => employee_id()]);

        json_response([
            'ok' => true,
            'message' => 'Empleado desactivado.',
        ]);
    }

    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response([
        'ok' => false,
        'message' => 'No se pudo procesar el empleado.',
        'detail' => $error->getMessage(),
    ], 500);
}

function employee_id(): string
{
    $id = trim($_GET['id'] ?? '');

    if ($id === '') {
        json_response(['ok' => false, 'message' => 'Empleado no valido.'], 422);
    }

    return $id;
}

function employee_config_payload(array $input): array
{
    $departmentId = nullable_int($input['departamento_id'] ?? null);
    $shiftId = nullable_int($input['turno_id'] ?? null);
    $salary = nullable_float($input['sueldo_base'] ?? null);
    $salaryType = trim($input['tipo_sueldo'] ?? '');
    $restDay = nullable_int_zero($input['dia_libre'] ?? null);
    $startDate = trim($input['fecha_inicio'] ?? '');
    $employeeStartDate = nullable_date($input['fecha_ingreso'] ?? null);

    if ($salary !== null && $salary < 0) {
        json_response(['ok' => false, 'message' => 'El sueldo no puede ser negativo.'], 422);
    }

    if ($salaryType !== '' && !in_array($salaryType, ['diario', 'semanal', 'quincenal', 'mensual'], true)) {
        json_response(['ok' => false, 'message' => 'Tipo de sueldo no valido.'], 422);
    }

    if ($restDay !== null && ($restDay < 0 || $restDay > 6)) {
        json_response(['ok' => false, 'message' => 'Dia libre no valido.'], 422);
    }

    if ($startDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        json_response(['ok' => false, 'message' => 'La fecha de inicio no es valida.'], 422);
    }

    return [
        'departamento_id' => $departmentId,
        'turno_id' => $shiftId,
        'sueldo_base' => $salary,
        'tipo_sueldo' => $salaryType === '' ? null : $salaryType,
        'dia_libre' => $restDay,
        'fecha_inicio' => $startDate,
        'fecha_ingreso_empleado' => $employeeStartDate,
        'empleado_activo' => array_key_exists('activo', $input) ? (int) (bool) $input['activo'] : 1,
    ];
}

function config_statement_payload(array $data, bool $includeConfigId = false): array
{
    $payload = [
        'empleado_id' => $data['empleado_id'],
        'departamento_id' => $data['departamento_id'],
        'turno_id' => $data['turno_id'],
        'sueldo_base' => $data['sueldo_base'],
        'tipo_sueldo' => $data['tipo_sueldo'],
        'dia_libre' => $data['dia_libre'],
        'fecha_inicio' => $data['fecha_inicio'],
    ];

    if ($includeConfigId) {
        $payload['configuracion_id'] = $data['configuracion_id'];
        unset($payload['empleado_id']);
    }

    return $payload;
}

function nullable_int(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    $number = (int) $value;

    return $number > 0 ? $number : null;
}

function nullable_int_zero(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    return (int) $value;
}

function ensure_employee_config_columns(): void
{
    ensure_employee_config_column('dia_libre', 'TINYINT UNSIGNED NULL AFTER tipo_sueldo');
}

function ensure_employee_config_column(string $column, string $definition): void
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'empleado_configuracion_laboral'
           AND COLUMN_NAME = :column"
    );
    $stmt->execute(['column' => $column]);

    if ((int) $stmt->fetch()['total'] === 0) {
        db()->exec("ALTER TABLE empleado_configuracion_laboral ADD COLUMN $column $definition");
    }
}

function nullable_float(mixed $value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    return (float) $value;
}

function nullable_date(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $date = trim((string) $value);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok' => false, 'message' => 'La fecha de ingreso no es valida.'], 422);
    }

    return $date;
}
