<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_login();
require_admin();

$payload = request_json();
$token = $payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!verify_csrf_token($token)) {
    json_response(419, array('success' => false, 'message' => 'Invalid CSRF token.'));
}

$action = strtolower(trim((string) ($payload['action'] ?? '')));
$fingerprintId = trim((string) ($payload['fingerprint_id'] ?? ''));
$method = strtolower(trim((string) ($payload['method'] ?? 'manual')));
$allowedMethods = array('manual', 'api', 'webauthn', 'thumb', 'fingerprint', 'card', 'face', 'qr');

if (!in_array($action, array('in', 'out'), true)) {
    json_response(422, array('success' => false, 'message' => 'Action must be "in" or "out".'));
}

if ($fingerprintId === '') {
    json_response(422, array('success' => false, 'message' => 'Fingerprint ID is required.'));
}

if (!in_array($method, $allowedMethods, true)) {
    $method = 'manual';
}

$pdo = db();
$employeeStatement = $pdo->prepare(
    "SELECT id, first_name, last_name, status
     FROM employees
     WHERE fingerprint_id = :fingerprint_id
     LIMIT 1"
);
$employeeStatement->execute(array('fingerprint_id' => $fingerprintId));
$employee = $employeeStatement->fetch();

if (!$employee) {
    json_response(404, array('success' => false, 'message' => 'No employee mapped to this fingerprint.'));
}

if ($employee['status'] !== 'active') {
    json_response(403, array('success' => false, 'message' => 'Employee is inactive.'));
}

try {
    $pdo->beginTransaction();

    if ($action === 'in') {
        $openStatement = $pdo->prepare(
            'SELECT id FROM attendance_logs WHERE employee_id = :employee_id AND clock_out_at IS NULL ORDER BY id DESC LIMIT 1'
        );
        $openStatement->execute(array('employee_id' => $employee['id']));
        $openRecord = $openStatement->fetch();

        if ($openRecord) {
            $pdo->rollBack();
            json_response(409, array('success' => false, 'message' => 'Employee is already clocked in.'));
        }

        $shiftStart = '09:00:00';
        $shiftRow = $pdo->query('SELECT shift_start_time FROM scanner_settings WHERE id = 1')->fetch();
        if ($shiftRow && !empty($shiftRow['shift_start_time'])) {
            $shiftStart = (string) $shiftRow['shift_start_time'];
        }

        $now = new DateTimeImmutable('now');
        $todayShift = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $now->format('Y-m-d') . ' ' . $shiftStart);
        $isLate = 0;
        if ($todayShift instanceof DateTimeImmutable && $now > $todayShift) {
            $isLate = 1;
        }

        $insertStatement = $pdo->prepare(
            'INSERT INTO attendance_logs (employee_id, clock_in_at, clock_in_method, is_late)
             VALUES (:employee_id, NOW(), :clock_in_method, :is_late)'
        );
        $insertStatement->execute(
            array(
                'employee_id' => $employee['id'],
                'clock_in_method' => $method,
                'is_late' => $isLate,
            )
        );

        $pdo->commit();

        json_response(200, array(
            'success' => true,
            'message' => 'Clock-in recorded.',
            'employee' => trim($employee['first_name'] . ' ' . $employee['last_name']),
            'action' => 'in',
            'is_late' => $isLate,
            'time' => $now->format('Y-m-d H:i:s'),
        ));
    }

    $openStatement = $pdo->prepare(
        'SELECT id, clock_in_at FROM attendance_logs
         WHERE employee_id = :employee_id AND clock_out_at IS NULL
         ORDER BY id DESC LIMIT 1'
    );
    $openStatement->execute(array('employee_id' => $employee['id']));
    $openRecord = $openStatement->fetch();

    if (!$openRecord) {
        $pdo->rollBack();
        json_response(409, array('success' => false, 'message' => 'No open shift found for this employee.'));
    }

    $updateStatement = $pdo->prepare(
        'UPDATE attendance_logs
         SET clock_out_at = NOW(), clock_out_method = :clock_out_method
         WHERE id = :id'
    );
    $updateStatement->execute(
        array(
            'clock_out_method' => $method,
            'id' => $openRecord['id'],
        )
    );

    $pdo->commit();

    $now = new DateTimeImmutable('now');
    json_response(200, array(
        'success' => true,
        'message' => 'Clock-out recorded.',
        'employee' => trim($employee['first_name'] . ' ' . $employee['last_name']),
        'action' => 'out',
        'time' => $now->format('Y-m-d H:i:s'),
    ));
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(500, array('success' => false, 'message' => 'Failed to save attendance record.'));
}
