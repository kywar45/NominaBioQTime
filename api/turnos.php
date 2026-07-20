<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

api_headers();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = db()->query(
            'SELECT
                id_turno,
                nombre_turno,
                hora_inicio,
                horas_trabajo,
                min_comida,
                min_excepcion,
                min_horas_extras,
                color,
                turno_nocturno,
                activo
             FROM turnos
             ORDER BY activo DESC, nombre_turno ASC'
        );

        json_response([
            'ok' => true,
            'shifts' => $stmt->fetchAll(),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = shift_payload(json_input());
        $stmt = db()->prepare(
            'INSERT INTO turnos (
                nombre_turno,
                hora_inicio,
                horas_trabajo,
                min_comida,
                min_excepcion,
                min_horas_extras,
                color,
                turno_nocturno,
                activo
             ) VALUES (
                :nombre_turno,
                :hora_inicio,
                :horas_trabajo,
                :min_comida,
                :min_excepcion,
                :min_horas_extras,
                :color,
                :turno_nocturno,
                :activo
             )'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Turno creado.',
            'id_turno' => (int) db()->lastInsertId(),
        ], 201);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $id = shift_id();
        $data = shift_payload(json_input());
        $data['id_turno'] = $id;

        $stmt = db()->prepare(
            'UPDATE turnos
             SET nombre_turno = :nombre_turno,
                 hora_inicio = :hora_inicio,
                 horas_trabajo = :horas_trabajo,
                 min_comida = :min_comida,
                 min_excepcion = :min_excepcion,
                 min_horas_extras = :min_horas_extras,
                 color = :color,
                 turno_nocturno = :turno_nocturno,
                 activo = :activo
             WHERE id_turno = :id_turno'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Turno actualizado.',
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $stmt = db()->prepare(
            'UPDATE turnos
             SET activo = 0
             WHERE id_turno = :id_turno'
        );
        $stmt->execute(['id_turno' => shift_id()]);

        json_response([
            'ok' => true,
            'message' => 'Turno desactivado.',
        ]);
    }

    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
} catch (Throwable $error) {
    json_response([
        'ok' => false,
        'message' => 'No se pudo procesar el turno.',
        'detail' => $error->getMessage(),
    ], 500);
}

function shift_id(): int
{
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Turno no valido.'], 422);
    }

    return $id;
}

function shift_payload(array $input): array
{
    $name = trim($input['nombre_turno'] ?? '');
    $startTime = normalize_time($input['hora_inicio'] ?? '');
    $hours = (float) ($input['horas_trabajo'] ?? 0);
    $lunchMinutes = (int) ($input['min_comida'] ?? 0);
    $exceptionMinutes = (int) ($input['min_excepcion'] ?? 0);
    $overtimeMinutes = (int) ($input['min_horas_extras'] ?? 0);
    $color = trim($input['color'] ?? '#1976D2');

    if ($name === '') {
        json_response(['ok' => false, 'message' => 'El nombre del turno es obligatorio.'], 422);
    }

    if ($startTime === null) {
        json_response(['ok' => false, 'message' => 'La hora de inicio no es valida.'], 422);
    }

    if ($hours <= 0) {
        json_response(['ok' => false, 'message' => 'Las horas de trabajo deben ser mayores a cero.'], 422);
    }

    if ($lunchMinutes < 0 || $exceptionMinutes < 0 || $overtimeMinutes < 0) {
        json_response(['ok' => false, 'message' => 'Los minutos no pueden ser negativos.'], 422);
    }

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        json_response(['ok' => false, 'message' => 'El color debe tener formato hexadecimal.'], 422);
    }

    return [
        'nombre_turno' => $name,
        'hora_inicio' => $startTime,
        'horas_trabajo' => $hours,
        'min_comida' => $lunchMinutes,
        'min_excepcion' => $exceptionMinutes,
        'min_horas_extras' => $overtimeMinutes,
        'color' => strtoupper($color),
        'turno_nocturno' => !empty($input['turno_nocturno']) ? 1 : 0,
        'activo' => array_key_exists('activo', $input) ? (int) (bool) $input['activo'] : 1,
    ];
}

function normalize_time(string $value): ?string
{
    $value = trim($value);

    if (preg_match('/^\d{2}:\d{2}$/', $value)) {
        return $value . ':00';
    }

    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
        return $value;
    }

    return null;
}
