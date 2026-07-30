<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

api_headers();

try {
    ensure_companies_table();
    ensure_banks_table();
    ensure_imss_table();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = db()->query(
            "SELECT
                e.id AS empleado_id,
                e.nombre AS empleado,
                e.activo AS empleado_activo,
                i.id_imss,
                COALESCE(i.posee_imss, 0) AS posee_imss,
                i.fecha_alta,
                i.numero_seguro_social,
                i.banco_id,
                COALESCE(b.nombre, i.banco) AS banco,
                i.cuenta_deposito,
                i.empresa_id,
                emp.nombre AS empresa,
                COALESCE(i.tipo_sueldo, config.tipo_sueldo) AS tipo_sueldo,
                config.tipo_sueldo AS tipo_sueldo_empleado,
                i.notas,
                CASE WHEN i.id_imss IS NULL THEN 0 ELSE 1 END AS configurado
             FROM empleados e
             INNER JOIN (
                SELECT c.*
                FROM empleado_configuracion_laboral c
                INNER JOIN (
                    SELECT empleado_id, MAX(id) AS id
                    FROM empleado_configuracion_laboral
                    WHERE activo = 1
                      AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
                    GROUP BY empleado_id
                ) latest ON latest.id = c.id
             ) config ON config.empleado_id COLLATE utf8mb4_unicode_ci = e.id COLLATE utf8mb4_unicode_ci
             LEFT JOIN empleado_imss_configuracion i
                ON i.empleado_id COLLATE utf8mb4_unicode_ci = e.id COLLATE utf8mb4_unicode_ci
             LEFT JOIN bancos b ON b.id_banco = i.banco_id
             LEFT JOIN empresas emp ON emp.id_empresa = i.empresa_id
             WHERE e.activo = 1
             ORDER BY e.nombre ASC"
        );

        json_response([
            'ok' => true,
            'imss' => $stmt->fetchAll(),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
        $employeeId = employee_id();
        $data = imss_payload(json_input());
        $data['empleado_id'] = $employeeId;

        $configStmt = db()->prepare(
            'SELECT e.id, config.tipo_sueldo
             FROM empleados e
             INNER JOIN (
                SELECT c.*
                FROM empleado_configuracion_laboral c
                INNER JOIN (
                    SELECT empleado_id, MAX(id) AS id
                    FROM empleado_configuracion_laboral
                    WHERE activo = 1
                      AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
                    GROUP BY empleado_id
                ) latest ON latest.id = c.id
             ) config ON config.empleado_id COLLATE utf8mb4_unicode_ci = e.id COLLATE utf8mb4_unicode_ci
             WHERE e.id = :empleado_id
               AND e.activo = 1
             LIMIT 1'
        );
        $configStmt->execute(['empleado_id' => $employeeId]);
        $employeeConfig = $configStmt->fetch();

        if (!$employeeConfig) {
            json_response([
                'ok' => false,
                'message' => 'Este empleado no tiene configuracion laboral activa.',
            ], 422);
        }

        $data['tipo_sueldo'] = $employeeConfig['tipo_sueldo'];

        $stmt = db()->prepare(
            'INSERT INTO empleado_imss_configuracion (
                empleado_id,
                posee_imss,
                fecha_alta,
                numero_seguro_social,
                banco_id,
                cuenta_deposito,
                empresa_id,
                tipo_sueldo,
                notas
             ) VALUES (
                :empleado_id,
                :posee_imss,
                :fecha_alta,
                :numero_seguro_social,
                :banco_id,
                :cuenta_deposito,
                :empresa_id,
                :tipo_sueldo,
                :notas
             )
             ON DUPLICATE KEY UPDATE
                posee_imss = VALUES(posee_imss),
                fecha_alta = VALUES(fecha_alta),
                numero_seguro_social = VALUES(numero_seguro_social),
                banco_id = VALUES(banco_id),
                cuenta_deposito = VALUES(cuenta_deposito),
                empresa_id = VALUES(empresa_id),
                tipo_sueldo = VALUES(tipo_sueldo),
                notas = VALUES(notas)'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Configuracion IMSS guardada.',
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $stmt = db()->prepare(
            'DELETE FROM empleado_imss_configuracion
             WHERE empleado_id = :empleado_id'
        );
        $stmt->execute(['empleado_id' => employee_id()]);

        json_response([
            'ok' => true,
            'message' => 'Configuracion IMSS eliminada.',
        ]);
    }

    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
} catch (Throwable $error) {
    json_response([
        'ok' => false,
        'message' => 'No se pudo procesar IMSS.',
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

function ensure_imss_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS empleado_imss_configuracion (
          id_imss INT UNSIGNED NOT NULL AUTO_INCREMENT,
          empleado_id VARCHAR(60) NOT NULL,
          posee_imss TINYINT(1) NOT NULL DEFAULT 0,
          fecha_alta DATE NULL,
          numero_seguro_social VARCHAR(30) NULL,
          banco VARCHAR(80) NULL,
          banco_id INT UNSIGNED NULL,
          cuenta_deposito VARCHAR(40) NULL,
          empresa_id INT UNSIGNED NULL,
          tipo_sueldo ENUM('diario', 'semanal', 'quincenal', 'mensual') NULL,
          notas TEXT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id_imss),
          UNIQUE KEY empleado_imss_empleado_unique (empleado_id),
          KEY empleado_imss_posee_idx (posee_imss),
          KEY empleado_imss_banco_idx (banco_id),
          KEY empleado_imss_empresa_idx (empresa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    ensure_imss_column('banco', 'VARCHAR(80) NULL AFTER numero_seguro_social');
    ensure_imss_column('banco_id', 'INT UNSIGNED NULL AFTER banco');
    ensure_imss_column('empresa_id', 'INT UNSIGNED NULL AFTER cuenta_deposito');
    ensure_imss_index('empleado_imss_banco_idx', 'banco_id');
    ensure_imss_index('empleado_imss_empresa_idx', 'empresa_id');
}

function ensure_imss_column(string $column, string $definition): void
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'empleado_imss_configuracion'
           AND COLUMN_NAME = :column"
    );
    $stmt->execute(['column' => $column]);

    if ((int) $stmt->fetch()['total'] === 0) {
        db()->exec("ALTER TABLE empleado_imss_configuracion ADD COLUMN $column $definition");
    }
}

function ensure_imss_index(string $index, string $column): void
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'empleado_imss_configuracion'
           AND INDEX_NAME = :index"
    );
    $stmt->execute(['index' => $index]);

    if ((int) $stmt->fetch()['total'] === 0) {
        db()->exec("ALTER TABLE empleado_imss_configuracion ADD KEY $index ($column)");
    }
}

function employee_id(): string
{
    $id = trim($_GET['id'] ?? '');

    if ($id === '') {
        json_response(['ok' => false, 'message' => 'Empleado no valido.'], 422);
    }

    return $id;
}

function imss_payload(array $input): array
{
    $hasImss = (int) (bool) ($input['posee_imss'] ?? false);
    $startDate = nullable_date($input['fecha_alta'] ?? null, 'La fecha de alta no es valida.');
    $nss = nullable_text($input['numero_seguro_social'] ?? null);
    $bankId = nullable_int($input['banco_id'] ?? null);
    $account = nullable_text($input['cuenta_deposito'] ?? null);
    $companyId = nullable_int($input['empresa_id'] ?? null);
    $salaryType = nullable_text($input['tipo_sueldo'] ?? null);
    $notes = nullable_text($input['notas'] ?? null);

    if ($hasImss === 1 && ($startDate === null || $nss === null)) {
        json_response(['ok' => false, 'message' => 'Fecha de alta y NSS son obligatorios si posee IMSS.'], 422);
    }

    if ($salaryType !== null && !in_array($salaryType, ['diario', 'semanal', 'quincenal', 'mensual'], true)) {
        json_response(['ok' => false, 'message' => 'Tipo de sueldo no valido.'], 422);
    }

    return [
        'posee_imss' => $hasImss,
        'fecha_alta' => $hasImss === 1 ? $startDate : null,
        'numero_seguro_social' => $hasImss === 1 ? $nss : null,
        'banco_id' => $bankId,
        'cuenta_deposito' => $account,
        'empresa_id' => $companyId,
        'tipo_sueldo' => $salaryType,
        'notas' => $notes,
    ];
}

function nullable_int(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    $number = (int) $value;

    return $number > 0 ? $number : null;
}

function nullable_text(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $text = trim((string) $value);

    return $text === '' ? null : $text;
}

function nullable_date(mixed $value, string $message): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $date = trim((string) $value);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok' => false, 'message' => $message], 422);
    }

    return $date;
}
