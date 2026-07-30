<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

api_headers();

try {
    ensure_loan_payments_table();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = payment_payload(json_input());
        $stmt = db()->prepare(
            'INSERT INTO prestamos_pagos (prestamo_id, fecha_pago, monto, notas)
             VALUES (:prestamo_id, :fecha_pago, :monto, :notas)'
        );
        $stmt->execute($data);

        json_response([
            'ok' => true,
            'message' => 'Pago registrado.',
            'id_pago' => (int) db()->lastInsertId(),
        ], 201);
    }

    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
} catch (Throwable $error) {
    json_response([
        'ok' => false,
        'message' => 'No se pudo procesar el pago.',
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

function payment_payload(array $input): array
{
    $loanId = (int) ($input['prestamo_id'] ?? 0);
    $paymentDate = normalize_payment_date($input['fecha_pago'] ?? null);
    $amount = (float) ($input['monto'] ?? 0);
    $notes = trim($input['notas'] ?? '');

    if ($loanId <= 0) {
        json_response(['ok' => false, 'message' => 'Prestamo no valido.'], 422);
    }

    if ($amount <= 0) {
        json_response(['ok' => false, 'message' => 'El pago debe ser mayor a cero.'], 422);
    }

    $loanStmt = db()->prepare(
        'SELECT p.monto, COALESCE(SUM(pp.monto), 0) AS pagado
         FROM prestamos p
         LEFT JOIN prestamos_pagos pp ON pp.prestamo_id = p.id_prestamo
         WHERE p.id_prestamo = :prestamo_id
         GROUP BY p.id_prestamo, p.monto'
    );
    $loanStmt->execute(['prestamo_id' => $loanId]);
    $loan = $loanStmt->fetch();

    if (!$loan) {
        json_response(['ok' => false, 'message' => 'Prestamo no encontrado.'], 404);
    }

    $balance = round((float) $loan['monto'] - (float) $loan['pagado'], 2);

    if ($balance <= 0) {
        json_response(['ok' => false, 'message' => 'El prestamo ya esta liquidado.'], 422);
    }

    if ($amount > $balance) {
        json_response(['ok' => false, 'message' => 'El pago no puede superar el saldo pendiente.'], 422);
    }

    return [
        'prestamo_id' => $loanId,
        'fecha_pago' => $paymentDate,
        'monto' => $amount,
        'notas' => $notes === '' ? null : $notes,
    ];
}

function normalize_payment_date(mixed $value): string
{
    $date = trim((string) ($value ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_response(['ok' => false, 'message' => 'La fecha de pago no es valida.'], 422);
    }

    return $date;
}
