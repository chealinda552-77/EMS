<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_login();

$payload = request_json();
$token = $payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!verify_csrf_token($token)) {
    json_response(419, array('success' => false, 'message' => 'Invalid CSRF token.'));
}

$user = current_user();
$employeeId = (int) ($user['employee_id'] ?? 0);
if ($employeeId <= 0) {
    json_response(403, array('success' => false, 'message' => 'Your account is not linked to an employee profile.'));
}

$action = strtolower(trim((string) ($payload['action'] ?? '')));
$method = strtolower(trim((string) ($payload['method'] ?? 'qr')));
$qrCode = trim((string) ($payload['qr_code'] ?? ''));
$latitude = filter_var($payload['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($payload['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

if (!in_array($action, array('in', 'out'), true)) {
    json_response(422, array('success' => false, 'message' => 'Action must be "in" or "out".'));
}

if ($method !== 'qr') {
    json_response(422, array('success' => false, 'message' => 'Mobile attendance only supports QR method.'));
}

if ($latitude === false || $longitude === false) {
    json_response(422, array('success' => false, 'message' => 'Location is required for QR attendance.'));
}

$pdo = db();
$hasQrSettingsColumns = table_has_column('scanner_settings', 'qr_secret')
    && table_has_column('scanner_settings', 'allowed_network_prefix')
    && table_has_column('scanner_settings', 'office_latitude')
    && table_has_column('scanner_settings', 'office_longitude')
    && table_has_column('scanner_settings', 'qr_max_distance_meters');

if ($hasQrSettingsColumns) {
    $settings = $pdo->query(
        'SELECT shift_start_time, qr_secret, allowed_network_prefix, office_latitude, office_longitude, qr_max_distance_meters
         FROM scanner_settings
         WHERE id = 1'
    )->fetch();
} else {
    $settings = $pdo->query('SELECT shift_start_time FROM scanner_settings WHERE id = 1')->fetch();
}

if (!$settings) {
    $settings = array(
        'shift_start_time' => '09:00:00',
        'qr_secret' => 'TEAM_PROJECT_QR',
        'allowed_network_prefix' => '',
        'office_latitude' => null,
        'office_longitude' => null,
        'qr_max_distance_meters' => 20,
    );
}

if (!$hasQrSettingsColumns) {
    json_response(500, array(
        'success' => false,
        'message' => 'Database migration for QR attendance settings is required. Contact Admin.',
    ));
}

$qrSecret = (string) ($settings['qr_secret'] ?? 'TEAM_PROJECT_QR');
if ($qrCode === '' || !hash_equals($qrSecret, $qrCode)) {
    json_response(403, array('success' => false, 'message' => 'Invalid QR token. Scan the official system QR code again.'));
}

$allowedPrefix = trim((string) ($settings['allowed_network_prefix'] ?? ''));
if ($allowedPrefix !== '') {
    $clientIp = '';

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwardedParts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $clientIp = trim((string) ($forwardedParts[0] ?? ''));
    }
    if ($clientIp === '') {
        $clientIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }
    if (strpos($clientIp, '::ffff:') === 0) {
        $clientIp = substr($clientIp, 7);
    }

    if ($clientIp === '' || strpos($clientIp, $allowedPrefix) !== 0) {
        json_response(403, array('success' => false, 'message' => 'You must use the same office Wi-Fi network.'));
    }
}

$officeLatitude = $settings['office_latitude'] !== null ? (float) $settings['office_latitude'] : null;
$officeLongitude = $settings['office_longitude'] !== null ? (float) $settings['office_longitude'] : null;
$maxDistance = (int) ($settings['qr_max_distance_meters'] ?? 20);
if ($maxDistance <= 0) {
    $maxDistance = 20;
}

if ($officeLatitude === null || $officeLongitude === null) {
    json_response(500, array('success' => false, 'message' => 'Office location is not configured. Contact Admin.'));
}

$earthRadius = 6371000;
$lat1 = deg2rad($officeLatitude);
$lat2 = deg2rad((float) $latitude);
$deltaLat = deg2rad((float) $latitude - $officeLatitude);
$deltaLon = deg2rad((float) $longitude - $officeLongitude);
$a = sin($deltaLat / 2) * sin($deltaLat / 2)
    + cos($lat1) * cos($lat2) * sin($deltaLon / 2) * sin($deltaLon / 2);
$c = 2 * atan2(sqrt($a), sqrt(1 - $a));
$distanceMeters = $earthRadius * $c;

if ($distanceMeters > $maxDistance) {
    json_response(403, array(
        'success' => false,
        'message' => 'You are outside the allowed range. Current distance is ' . round($distanceMeters, 1) . 'm.',
    ));
}

$employeeStatement = $pdo->prepare(
    "SELECT id, first_name, last_name, status
     FROM employees
     WHERE id = :employee_id
     LIMIT 1"
);
$employeeStatement->execute(array('employee_id' => $employeeId));
$employee = $employeeStatement->fetch();

if (!$employee) {
    json_response(404, array('success' => false, 'message' => 'Employee profile not found.'));
}

if ((string) ($employee['status'] ?? 'inactive') !== 'active') {
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
            json_response(409, array('success' => false, 'message' => 'You are already clocked in.'));
        }

        $shiftStart = (string) ($settings['shift_start_time'] ?? '09:00:00');
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
        $insertStatement->execute(array(
            'employee_id' => $employee['id'],
            'clock_in_method' => 'qr',
            'is_late' => $isLate,
        ));

        $pdo->commit();

        json_response(200, array(
            'success' => true,
            'message' => 'Clock-in recorded successfully via QR.',
            'employee' => trim((string) $employee['first_name'] . ' ' . (string) $employee['last_name']),
            'action' => 'in',
            'distance_meters' => round($distanceMeters, 1),
            'is_late' => $isLate,
            'time' => $now->format('Y-m-d H:i:s'),
        ));
    }

    $openStatement = $pdo->prepare(
        'SELECT id FROM attendance_logs
         WHERE employee_id = :employee_id AND clock_out_at IS NULL
         ORDER BY id DESC LIMIT 1'
    );
    $openStatement->execute(array('employee_id' => $employee['id']));
    $openRecord = $openStatement->fetch();

    if (!$openRecord) {
        $pdo->rollBack();
        json_response(409, array('success' => false, 'message' => 'No open shift found for clock-out.'));
    }

    $updateStatement = $pdo->prepare(
        'UPDATE attendance_logs
         SET clock_out_at = NOW(), clock_out_method = :clock_out_method
         WHERE id = :id'
    );
    $updateStatement->execute(array(
        'clock_out_method' => 'qr',
        'id' => $openRecord['id'],
    ));

    $pdo->commit();
    $now = new DateTimeImmutable('now');

    json_response(200, array(
        'success' => true,
        'message' => 'Clock-out recorded successfully via QR.',
        'employee' => trim((string) $employee['first_name'] . ' ' . (string) $employee['last_name']),
        'action' => 'out',
        'distance_meters' => round($distanceMeters, 1),
        'time' => $now->format('Y-m-d H:i:s'),
    ));
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(500, array('success' => false, 'message' => 'Failed to save attendance record.'));
}
