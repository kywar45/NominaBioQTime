<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

api_headers();

try {
    ensure_holidays_table();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = db()->query(
            'SELECT id_festivo, nombre, fecha, no_laborable, notas
             FROM dias_festivos
             ORDER BY fecha DESC, nombre ASC'
        );

        json_response([
            'ok' => true,
            'holidays' => $stmt->fetchAll(),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = holiday_payload(json_input());
        $stmt = db()->prepare(
            'INSERT INTO dias_festivos (nombre, fecha, no_laborable, notas)
             VALUES (:nombre, :fecha, :no_laborable, :notas)'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Dia festivo creado.',
            'id_festivo' => (int) db()->lastInsertId(),
        ], 201);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = holiday_payload(json_input());
        $data['id_festivo'] = holiday_id();
        $stmt = db()->prepare(
            'UPDATE dias_festivos
             SET nombre = :nombre,
                 fecha = :fecha,
                 no_laborable = :no_laborable,
                 notas = :notas
             WHERE id_festivo = :id_festivo'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Dia festivo actualizado.',
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $stmt = db()->prepare('DELETE FROM dias_festivos WHERE id_festivo = :id_festivo');
        $stmt->execute(['id_festivo' => holiday_id()]);

        json_response([
            'ok' => true,
            'message' => 'Dia festivo eliminado.',
        ]);
    }

    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
} catch (Throwable $error) {
    json_response([
        'ok' => false,
        'message' => 'No se pudo procesar el dia festivo.',
        'detail' => $error->getMessage(),
    ], 500);
}

function ensure_holidays_table(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS dias_festivos (
          id_festivo INT UNSIGNED NOT NULL AUTO_INCREMENT,
          nombre VARCHAR(120) NOT NULL,
          fecha DATE NOT NULL,
          no_laborable TINYINT(1) NOT NULL DEFAULT 1,
          notas TEXT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id_festivo),
          UNIQUE KEY dias_festivos_fecha_unique (fecha),
          KEY dias_festivos_no_laborable_idx (no_laborable)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function holiday_id(): int
{
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Dia festivo no valido.'], 422);
    }

    return $id;
}

function holiday_payload(array $input): array
{
    $name = trim($input['nombre'] ?? '');
    $date = normalize_holiday_date($input['fecha'] ?? null);
    $notes = trim($input['notas'] ?? '');

    if ($name === '') {
        json_response(['ok' => false, 'message' => 'El nombre del dia festivo es obligatorio.'], 422);
    }

    return [
        'nombre' => $name,
        'fecha' => $date,
        'no_laborable' => array_key_exists('no_laborable', $input) ? (int) (bool) $input['no_laborable'] : 1,
        'notas' => $notes === '' ? null : $notes,
    ];
}

function normalize_holiday_date(mixed $value): string
{
    $date = trim((string) ($value ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok' => false, 'message' => 'La fecha del dia festivo no es valida.'], 422);
    }

    return $date;
}
