<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

api_headers();

try {
    ensure_loans_table();
    ensure_loan_payments_table();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = db()->query(
            'SELECT
                p.id_prestamo,
                p.empleado_id,
                e.nombre AS empleado,
                p.monto,
                p.fecha_prestamo,
                p.numero_pagos,
                p.frecuencia_pago,
                p.primer_pago,
                p.plan_json,
                p.notas,
                COALESCE(payments.pagado, 0) AS pagado,
                (p.monto - COALESCE(payments.pagado, 0)) AS saldo
             FROM prestamos p
             LEFT JOIN (
                SELECT prestamo_id, SUM(monto) AS pagado
                FROM prestamos_pagos
                GROUP BY prestamo_id
             ) payments ON payments.prestamo_id = p.id_prestamo
             LEFT JOIN empleados e
               ON e.id COLLATE utf8mb4_unicode_ci = p.empleado_id COLLATE utf8mb4_unicode_ci
             ORDER BY p.fecha_prestamo DESC, e.nombre ASC'
        );
        $paymentStmt = db()->query(
            'SELECT id_pago, prestamo_id, fecha_pago, monto, notas
             FROM prestamos_pagos
             ORDER BY fecha_pago ASC, id_pago ASC'
        );
        $paymentsByLoan = [];

        foreach ($paymentStmt->fetchAll() as $payment) {
            $paymentsByLoan[(int) $payment['prestamo_id']][] = $payment;
        }

        $loans = array_map(static function (array $loan) use ($paymentsByLoan): array {
            $loanId = (int) $loan['id_prestamo'];
            $balance = max((float) $loan['saldo'], 0);
            $loan['plan'] = json_decode($loan['plan_json'] ?: '[]', true) ?: [];
            $loan['pagos'] = $paymentsByLoan[$loanId] ?? [];
            $loan['estatus'] = $balance <= 0.009 ? 'liquidado' : 'activo';
            $loan['saldo'] = $balance;
            unset($loan['plan_json']);

            return $loan;
        }, $stmt->fetchAll());

        json_response([
            'ok' => true,
            'loans' => $loans,
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = loan_payload(json_input());
        $stmt = db()->prepare(
            'INSERT INTO prestamos (
                empleado_id,
                monto,
                fecha_prestamo,
                numero_pagos,
                frecuencia_pago,
                primer_pago,
                plan_json,
                notas
             ) VALUES (
                :empleado_id,
                :monto,
                :fecha_prestamo,
                :numero_pagos,
                :frecuencia_pago,
                :primer_pago,
                :plan_json,
                :notas
             )'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Prestamo creado.',
            'id_prestamo' => (int) db()->lastInsertId(),
        ], 201);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = loan_payload(json_input());
        $data['id_prestamo'] = loan_id();
        $stmt = db()->prepare(
            'UPDATE prestamos
             SET empleado_id = :empleado_id,
                 monto = :monto,
                 fecha_prestamo = :fecha_prestamo,
                 numero_pagos = :numero_pagos,
                 frecuencia_pago = :frecuencia_pago,
                 primer_pago = :primer_pago,
                 plan_json = :plan_json,
                 notas = :notas
             WHERE id_prestamo = :id_prestamo'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Prestamo actualizado.',
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        loan_id();
        json_response(['ok' => false, 'message' => 'No se puede eliminar un prestamo activo.'], 409);
    }

    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
} catch (Throwable $error) {
    json_response([
        'ok' => false,
        'message' => 'No se pudo procesar el prestamo.',
        'detail' => $error->getMessage(),
    ], 500);
}

function ensure_loan_payments_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS prestamos_pagos (
          id_pago INT UNSIGNED NOT NULL AUTO_INCREMENT,
          prestamo_id INT UNSIGNED NOT NULL,
          fecha_pago DATE NOT NULL,
          monto DECIMAL(12,2) NOT NULL DEFAULT 0,
          notas TEXT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id_pago),
          KEY prestamos_pagos_prestamo_idx (prestamo_id),
          KEY prestamos_pagos_fecha_idx (fecha_pago),
          CONSTRAINT prestamos_pagos_prestamo_fk FOREIGN KEY (prestamo_id) REFERENCES prestamos (id_prestamo) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function ensure_loans_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS prestamos (
          id_prestamo INT UNSIGNED NOT NULL AUTO_INCREMENT,
          empleado_id VARCHAR(60) NOT NULL,
          monto DECIMAL(12,2) NOT NULL DEFAULT 0,
          fecha_prestamo DATE NOT NULL,
          numero_pagos INT UNSIGNED NOT NULL DEFAULT 1,
          frecuencia_pago ENUM('semanal', 'quincenal', 'mensual') NOT NULL DEFAULT 'mensual',
          primer_pago DATE NOT NULL,
          plan_json JSON NOT NULL,
          notas TEXT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id_prestamo),
          KEY prestamos_empleado_idx (empleado_id),
          KEY prestamos_fecha_idx (fecha_prestamo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function loan_id(): int
{
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        json_response(['ok' => false, 'message' => 'Prestamo no valido.'], 422);
    }

    return $id;
}

function loan_payload(array $input): array
{
    $employeeId = trim((string) ($input['empleado_id'] ?? ''));
    $amount = (float) ($input['monto'] ?? 0);
    $loanDate = normalize_loan_date($input['fecha_prestamo'] ?? null, 'La fecha del prestamo no es valida.');
    $paymentCount = (int) ($input['numero_pagos'] ?? 1);
    $frequency = normalize_frequency($input['frecuencia_pago'] ?? 'mensual');
    $firstPayment = normalize_loan_date($input['primer_pago'] ?? null, 'La fecha del primer pago no es valida.');
    $notes = trim($input['notas'] ?? '');

    if ($employeeId === '') {
        json_response(['ok' => false, 'message' => 'Selecciona un empleado.'], 422);
    }

    if ($amount <= 0) {
        json_response(['ok' => false, 'message' => 'El monto del prestamo debe ser mayor a cero.'], 422);
    }

    if ($paymentCount <= 0 || $paymentCount > 120) {
        json_response(['ok' => false, 'message' => 'El numero de pagos debe estar entre 1 y 120.'], 422);
    }

    $plan = build_payment_plan($amount, $paymentCount, $frequency, $firstPayment);

    return [
        'empleado_id' => $employeeId,
        'monto' => $amount,
        'fecha_prestamo' => $loanDate,
        'numero_pagos' => $paymentCount,
        'frecuencia_pago' => $frequency,
        'primer_pago' => $firstPayment,
        'plan_json' => json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'notas' => $notes === '' ? null : $notes,
    ];
}

function build_payment_plan(float $amount, int $paymentCount, string $frequency, string $firstPayment): array
{
    $basePayment = floor(($amount / $paymentCount) * 100) / 100;
    $remaining = $amount;
    $date = new DateTimeImmutable($firstPayment);
    $plan = [];

    for ($i = 1; $i <= $paymentCount; $i++) {
        $paymentAmount = $i === $paymentCount ? round($remaining, 2) : $basePayment;
        $remaining = round($remaining - $paymentAmount, 2);

        $plan[] = [
            'numero' => $i,
            'fecha' => $date->format('Y-m-d'),
            'monto' => $paymentAmount,
            'saldo' => max($remaining, 0),
        ];

        $date = next_payment_date($date, $frequency);
    }

    return $plan;
}

function next_payment_date(DateTimeImmutable $date, string $frequency): DateTimeImmutable
{
    if ($frequency === 'semanal') {
        return $date->modify('+1 week');
    }

    if ($frequency === 'quincenal') {
        return $date->modify('+15 days');
    }

    return $date->modify('+1 month');
}

function normalize_frequency(mixed $value): string
{
    $frequency = trim((string) $value);

    if (!in_array($frequency, ['semanal', 'quincenal', 'mensual'], true)) {
        json_response(['ok' => false, 'message' => 'La frecuencia de pago no es valida.'], 422);
    }

    return $frequency;
}

function normalize_loan_date(mixed $value, string $message): string
{
    $date = trim((string) ($value ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok' => false, 'message' => $message], 422);
    }

    return $date;
}
