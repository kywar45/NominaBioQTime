<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

api_headers();

try {
    ensure_rules_table();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = db()->query(
            "SELECT
                r.id_regla,
                r.nombre,
                r.descripcion,
                r.tipo,
                r.alcance,
                r.target_id,
                CASE
                    WHEN r.alcance = 'departamento' THEN d.nombre_departamento
                    WHEN r.alcance = 'turno' THEN t.nombre_turno
                    WHEN r.alcance = 'empleado' THEN e.nombre
                    ELSE 'Todos'
                END AS target_nombre,
                r.condicion,
                r.operador,
                r.valor_condicion,
                r.tipo_valor,
                r.valor,
                r.frecuencia,
                r.activo
             FROM nomina_reglas r
             LEFT JOIN departamentos d
                ON r.alcance COLLATE utf8mb4_unicode_ci = 'departamento' COLLATE utf8mb4_unicode_ci
               AND d.id_departamento = CAST(r.target_id AS UNSIGNED)
             LEFT JOIN turnos t
                ON r.alcance COLLATE utf8mb4_unicode_ci = 'turno' COLLATE utf8mb4_unicode_ci
               AND t.id_turno = CAST(r.target_id AS UNSIGNED)
             LEFT JOIN empleados e
                ON r.alcance COLLATE utf8mb4_unicode_ci = 'empleado' COLLATE utf8mb4_unicode_ci
               AND e.id COLLATE utf8mb4_unicode_ci = r.target_id COLLATE utf8mb4_unicode_ci
             ORDER BY r.activo DESC, r.tipo ASC, r.nombre ASC"
        );

        json_response([
            'ok' => true,
            'rules' => $stmt->fetchAll(),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = rule_payload(json_input());
        $stmt = db()->prepare(
            'INSERT INTO nomina_reglas (
                nombre,
                descripcion,
                tipo,
                alcance,
                target_id,
                condicion,
                operador,
                valor_condicion,
                tipo_valor,
                valor,
                frecuencia,
                activo
             ) VALUES (
                :nombre,
                :descripcion,
                :tipo,
                :alcance,
                :target_id,
                :condicion,
                :operador,
                :valor_condicion,
                :tipo_valor,
                :valor,
                :frecuencia,
                :activo
             )'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Regla creada.',
            'id_regla' => (int) db()->lastInsertId(),
        ], 201);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = rule_payload(json_input());
        $data['id_regla'] = rule_id();
        $stmt = db()->prepare(
            'UPDATE nomina_reglas
             SET nombre = :nombre,
                 descripcion = :descripcion,
                 tipo = :tipo,
                 alcance = :alcance,
                 target_id = :target_id,
                 condicion = :condicion,
                 operador = :operador,
                 valor_condicion = :valor_condicion,
                 tipo_valor = :tipo_valor,
                 valor = :valor,
                 frecuencia = :frecuencia,
                 activo = :activo
             WHERE id_regla = :id_regla'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Regla actualizada.',
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $stmt = db()->prepare(
            'UPDATE nomina_reglas
             SET activo = 0
             WHERE id_regla = :id_regla'
        );
        $stmt->execute(['id_regla' => rule_id()]);

        json_response([
            'ok' => true,
            'message' => 'Regla desactivada.',
        ]);
    }

    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
} catch (Throwable $error) {
    json_response([
        'ok' => false,
        'message' => 'No se pudo procesar la regla.',
        'detail' => $error->getMessage(),
    ], 500);
}

function ensure_rules_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS nomina_reglas (
          id_regla INT UNSIGNED NOT NULL AUTO_INCREMENT,
          nombre VARCHAR(120) NOT NULL,
          descripcion TEXT NULL,
          tipo ENUM('sancion', 'bonificacion') NOT NULL,
          alcance ENUM('todos', 'departamento', 'turno', 'empleado') NOT NULL DEFAULT 'todos',
          target_id VARCHAR(60) NULL,
          condicion ENUM('retardo_minutos', 'falta', 'hora_extra_minutos', 'asistencia_perfecta', 'manual') NOT NULL,
          operador ENUM('=', '>=', '<=') NULL,
          valor_condicion DECIMAL(10,2) NULL,
          tipo_valor ENUM('monto', 'porcentaje', 'dias', 'minutos') NOT NULL DEFAULT 'monto',
          valor DECIMAL(12,2) NOT NULL DEFAULT 0,
          frecuencia ENUM('por_evento', 'por_dia', 'por_periodo') NOT NULL DEFAULT 'por_evento',
          activo TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id_regla),
          KEY nomina_reglas_tipo_idx (tipo),
          KEY nomina_reglas_alcance_idx (alcance, target_id),
          KEY nomina_reglas_activo_idx (activo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function rule_id(): int
{
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Regla no valida.'], 422);
    }

    return $id;
}

function rule_payload(array $input): array
{
    $name = trim($input['nombre'] ?? '');
    $description = trim($input['descripcion'] ?? '');
    $type = enum_value($input['tipo'] ?? '', ['sancion', 'bonificacion']);
    $scope = enum_value($input['alcance'] ?? 'todos', ['todos', 'departamento', 'turno', 'empleado']);
    $condition = enum_value($input['condicion'] ?? '', [
        'retardo_minutos',
        'falta',
        'hora_extra_minutos',
        'asistencia_perfecta',
        'manual',
    ]);
    $operator = nullable_enum($input['operador'] ?? null, ['=', '>=', '<=']);
    $conditionValue = nullable_decimal($input['valor_condicion'] ?? null);
    $valueType = enum_value($input['tipo_valor'] ?? 'monto', ['monto', 'porcentaje', 'dias', 'minutos']);
    $value = (float) ($input['valor'] ?? 0);
    $frequency = enum_value($input['frecuencia'] ?? 'por_evento', ['por_evento', 'por_dia', 'por_periodo']);
    $targetId = trim((string) ($input['target_id'] ?? ''));

    if ($name === '') {
        json_response(['ok' => false, 'message' => 'El nombre de la regla es obligatorio.'], 422);
    }

    if ($type === null) {
        json_response(['ok' => false, 'message' => 'Tipo de regla no valido.'], 422);
    }

    if ($scope === null) {
        json_response(['ok' => false, 'message' => 'Alcance de regla no valido.'], 422);
    }

    if ($scope !== 'todos' && $targetId === '') {
        json_response(['ok' => false, 'message' => 'Selecciona a quien aplica la regla.'], 422);
    }

    if ($condition === null) {
        json_response(['ok' => false, 'message' => 'Condicion de regla no valida.'], 422);
    }

    if (!in_array($condition, ['falta', 'asistencia_perfecta', 'manual'], true) && $operator === null) {
        json_response(['ok' => false, 'message' => 'Selecciona el operador de la condicion.'], 422);
    }

    if (!in_array($condition, ['falta', 'asistencia_perfecta', 'manual'], true) && $conditionValue === null) {
        json_response(['ok' => false, 'message' => 'Captura el valor de la condicion.'], 422);
    }

    if ($valueType === null) {
        json_response(['ok' => false, 'message' => 'Tipo de impacto no valido.'], 422);
    }

    if ($value < 0) {
        json_response(['ok' => false, 'message' => 'El valor de la regla no puede ser negativo.'], 422);
    }

    if ($frequency === null) {
        json_response(['ok' => false, 'message' => 'Frecuencia no valida.'], 422);
    }

    return [
        'nombre' => $name,
        'descripcion' => $description === '' ? null : $description,
        'tipo' => $type,
        'alcance' => $scope,
        'target_id' => $scope === 'todos' ? null : $targetId,
        'condicion' => $condition,
        'operador' => in_array($condition, ['falta', 'asistencia_perfecta', 'manual'], true) ? null : $operator,
        'valor_condicion' => in_array($condition, ['falta', 'asistencia_perfecta', 'manual'], true) ? null : $conditionValue,
        'tipo_valor' => $valueType,
        'valor' => $value,
        'frecuencia' => $frequency,
        'activo' => array_key_exists('activo', $input) ? (int) (bool) $input['activo'] : 1,
    ];
}

function enum_value(mixed $value, array $allowed): ?string
{
    $value = trim((string) $value);

    return in_array($value, $allowed, true) ? $value : null;
}

function nullable_enum(mixed $value, array $allowed): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return enum_value($value, $allowed);
}

function nullable_decimal(mixed $value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    return (float) $value;
}
