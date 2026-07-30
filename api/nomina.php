<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

api_headers();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
    }

    $range = payroll_range();
    $employees = payroll_employees();
    $attendance = payroll_attendance($range['start'], $range['end']);
    $days = payroll_days($range['start'], $range['end']);

    $groups = [
        'semanal' => [],
        'quincenal' => [],
    ];

    foreach ($employees as $employee) {
        $employee['checadas'] = $attendance[$employee['id']] ?? [];
        $employee['calculo'] = calculate_employee_payroll($employee, $days);
        $groups[$employee['tipo_sueldo']][] = $employee;
    }

    json_response([
        'ok' => true,
        'period' => $range,
        'days' => $days,
        'groups' => array_filter($groups, fn ($rows) => count($rows) > 0),
    ]);
} catch (Throwable $error) {
    json_response([
        'ok' => false,
        'message' => 'No se pudo procesar nomina.',
        'detail' => $error->getMessage(),
    ], 500);
}

function payroll_range(): array
{
    $start = nullable_date($_GET['inicio'] ?? null);
    $end = nullable_date($_GET['fin'] ?? null);

    if ($start === null || $end === null) {
        $stmt = db()->query('SELECT MAX(fecha) AS fecha FROM registros_asistencia');
        $latest = $stmt->fetch()['fecha'] ?? date('Y-m-d');
        $end = $end ?? $latest;
        $start = $start ?? date('Y-m-d', strtotime($end . ' -14 days'));
    }

    if ($start > $end) {
        json_response(['ok' => false, 'message' => 'El rango de fechas no es valido.'], 422);
    }

    return [
        'start' => $start,
        'end' => $end,
    ];
}

function payroll_employees(): array
{
    $stmt = db()->query(
        "SELECT
            e.id,
            e.nombre,
            config.tipo_sueldo,
            config.sueldo_base,
            config.dia_libre,
            d.nombre_departamento AS departamento,
            t.nombre_turno AS turno,
            t.hora_inicio,
            t.horas_trabajo,
            t.min_comida,
            t.min_excepcion,
            t.min_horas_extras,
            t.turno_nocturno
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
         LEFT JOIN departamentos d ON d.id_departamento = config.departamento_id
         LEFT JOIN turnos t ON t.id_turno = config.turno_id
         WHERE e.activo = 1
           AND config.tipo_sueldo IN ('semanal', 'quincenal')
         ORDER BY config.tipo_sueldo ASC, e.nombre ASC"
    );

    return $stmt->fetchAll();
}

function payroll_attendance(string $start, string $end): array
{
    $stmt = db()->prepare(
        "SELECT
            id_empleado,
            fecha,
            MIN(CASE WHEN tipo = 1 THEN hora END) AS entrada,
            MIN(CASE WHEN tipo = 2 THEN hora END) AS comida,
            MAX(CASE WHEN tipo = 3 THEN hora END) AS regreso,
            MAX(CASE WHEN tipo = 4 THEN hora END) AS salida
         FROM registros_asistencia
         WHERE fecha BETWEEN :start_date AND :end_date
           AND tipo IN (1, 2, 3, 4)
         GROUP BY id_empleado, fecha"
    );
    $stmt->execute([
        'start_date' => $start,
        'end_date' => $end,
    ]);

    $attendance = [];

    foreach ($stmt->fetchAll() as $row) {
        $attendance[$row['id_empleado']][$row['fecha']] = [
            'entrada' => short_time($row['entrada'] ?? null),
            'comida' => short_time($row['comida'] ?? null),
            'regreso' => short_time($row['regreso'] ?? null),
            'salida' => short_time($row['salida'] ?? null),
        ];
    }

    return $attendance;
}

function payroll_days(string $start, string $end): array
{
    $days = [];
    $cursor = new DateTimeImmutable($start);
    $last = new DateTimeImmutable($end);
    $names = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];

    while ($cursor <= $last) {
        $days[] = [
            'date' => $cursor->format('Y-m-d'),
            'day' => $cursor->format('d'),
            'weekday_index' => (int) $cursor->format('w'),
            'weekday' => $names[(int) $cursor->format('w')],
        ];
        $cursor = $cursor->modify('+1 day');
    }

    return $days;
}

function calculate_employee_payroll(array $employee, array $days): array
{
    $baseSalary = (float) ($employee['sueldo_base'] ?? 0);
    $workDays = array_values(array_filter($days, fn ($day) => !is_rest_day($employee, $day)));
    $workDayCount = max(count($workDays), 1);
    $dailySalary = $baseSalary / $workDayCount;
    $workHours = max((float) ($employee['horas_trabajo'] ?? 0), 1);
    $hourlyRate = $dailySalary / $workHours;
    $minuteRate = $hourlyRate / 60;
    $summary = [
        'sueldo_base' => round($baseSalary, 2),
        'dias_laborables' => count($workDays),
        'dias_pagados' => 0,
        'faltas' => 0,
        'minutos_retardo' => 0,
        'minutos_extra' => 0,
        'descuento_faltas' => 0,
        'descuento_retardos' => 0,
        'pago_horas_extra' => 0,
        'total' => 0,
        'dias' => [],
    ];

    foreach ($days as $day) {
        $date = $day['date'];
        $checks = $employee['checadas'][$date] ?? null;
        $freeDay = is_rest_day($employee, $day);
        $dayResult = [
            'dia_libre' => $freeDay,
            'falta' => false,
            'minutos_retardo' => 0,
            'minutos_extra' => 0,
            'descuento' => 0,
            'extra' => 0,
            'total' => $freeDay ? 0 : $dailySalary,
        ];

        if ($freeDay) {
            $summary['dias'][$date] = $dayResult;
            continue;
        }

        if (!$checks || (!$checks['entrada'] && !$checks['salida'])) {
            $dayResult['falta'] = true;
            $dayResult['descuento'] = $dailySalary;
            $dayResult['total'] = 0;
            $summary['faltas']++;
            $summary['descuento_faltas'] += $dailySalary;
            $summary['dias'][$date] = $dayResult;
            continue;
        }

        $entryLate = late_minutes(
            $checks['entrada'] ?? null,
            short_time($employee['hora_inicio'] ?? null),
            (int) ($employee['min_excepcion'] ?? 0),
        );
        $lunchLate = lunch_late_minutes(
            $checks['comida'] ?? null,
            $checks['regreso'] ?? null,
            (int) ($employee['min_comida'] ?? 0),
            (int) ($employee['min_excepcion'] ?? 0),
        );
        $overtime = overtime_minutes($employee, $checks['salida'] ?? null);
        $lateMinutes = $entryLate + $lunchLate;
        $lateDiscount = $lateMinutes * $minuteRate;
        $overtimePay = $overtime * $minuteRate * 2;

        $dayResult['minutos_retardo'] = $lateMinutes;
        $dayResult['minutos_extra'] = $overtime;
        $dayResult['descuento'] = round($lateDiscount, 2);
        $dayResult['extra'] = round($overtimePay, 2);
        $dayResult['total'] = round(max($dailySalary - $lateDiscount, 0) + $overtimePay, 2);

        $summary['dias_pagados']++;
        $summary['minutos_retardo'] += $lateMinutes;
        $summary['minutos_extra'] += $overtime;
        $summary['descuento_retardos'] += $lateDiscount;
        $summary['pago_horas_extra'] += $overtimePay;
        $summary['dias'][$date] = $dayResult;
    }

    $summary['descuento_faltas'] = round($summary['descuento_faltas'], 2);
    $summary['descuento_retardos'] = round($summary['descuento_retardos'], 2);
    $summary['pago_horas_extra'] = round($summary['pago_horas_extra'], 2);
    $summary['total'] = round(
        max($baseSalary - $summary['descuento_faltas'] - $summary['descuento_retardos'], 0)
            + $summary['pago_horas_extra'],
        2
    );

    return $summary;
}

function is_rest_day(array $employee, array $day): bool
{
    if ($employee['dia_libre'] === null || $employee['dia_libre'] === '') {
        return false;
    }

    return (int) $employee['dia_libre'] === (int) $day['weekday_index'];
}

function late_minutes(?string $actual, ?string $expected, int $tolerance): int
{
    if (!$actual || !$expected) {
        return 0;
    }

    return max(0, minutes_from_time($actual) - minutes_from_time($expected) - $tolerance);
}

function lunch_late_minutes(?string $lunchOut, ?string $lunchBack, int $lunchMinutes, int $tolerance): int
{
    if (!$lunchOut || !$lunchBack || $lunchMinutes <= 0) {
        return 0;
    }

    return max(0, minutes_from_time($lunchBack) - minutes_from_time($lunchOut) - $lunchMinutes - $tolerance);
}

function overtime_minutes(array $employee, ?string $exit): int
{
    if (!$exit || empty($employee['hora_inicio']) || empty($employee['horas_trabajo'])) {
        return 0;
    }

    $start = minutes_from_time($employee['hora_inicio']);
    $expected = $start
        + ((float) $employee['horas_trabajo'] * 60)
        + (int) ($employee['min_comida'] ?? 0);
    $actual = minutes_from_time($exit);

    if ((int) ($employee['turno_nocturno'] ?? 0) === 1 && $actual < $start) {
        $actual += 1440;
    }

    return max(0, $actual - $expected - (int) ($employee['min_horas_extras'] ?? 0));
}

function minutes_from_time(string $time): int
{
    [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));

    return ($hours * 60) + $minutes;
}

function nullable_date(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $date = trim((string) $value);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok' => false, 'message' => 'La fecha no es valida.'], 422);
    }

    return $date;
}

function short_time(?string $value): ?string
{
    if (!$value) {
        return null;
    }

    return substr($value, 0, 5);
}
