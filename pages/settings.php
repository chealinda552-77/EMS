<?php
declare(strict_types=1);

require_login();
require_admin();
$pageTitle = 'Settings';

$pdo = db();
$hasQrSettingsColumns = table_has_column('scanner_settings', 'qr_secret')
    && table_has_column('scanner_settings', 'allowed_network_prefix')
    && table_has_column('scanner_settings', 'office_latitude')
    && table_has_column('scanner_settings', 'office_longitude')
    && table_has_column('scanner_settings', 'qr_max_distance_meters');

if (is_post()) {
    require_csrf();

    $scannerMode = (string) ($_POST['scanner_mode'] ?? 'manual');
    $apiEndpoint = trim((string) ($_POST['api_endpoint'] ?? ''));
    $shiftStartTime = trim((string) ($_POST['shift_start_time'] ?? '09:00:00'));
    $qrSecret = trim((string) ($_POST['qr_secret'] ?? ''));
    $allowedNetworkPrefix = trim((string) ($_POST['allowed_network_prefix'] ?? ''));
    $officeLatitudeInput = trim((string) ($_POST['office_latitude'] ?? ''));
    $officeLongitudeInput = trim((string) ($_POST['office_longitude'] ?? ''));
    $qrMaxDistance = (int) ($_POST['qr_max_distance_meters'] ?? 20);

    if (!in_array($scannerMode, array('manual', 'api', 'webauthn', 'thumb'), true)) {
        $scannerMode = 'manual';
    }

    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $shiftStartTime)) {
        $shiftStartTime = '09:00:00';
    } elseif (strlen($shiftStartTime) === 5) {
        $shiftStartTime .= ':00';
    }

    if ($qrSecret === '') {
        $qrSecret = bin2hex(random_bytes(16));
    }

    $officeLatitude = null;
    $officeLongitude = null;
    if ($officeLatitudeInput !== '' && is_numeric($officeLatitudeInput)) {
        $officeLatitude = (float) $officeLatitudeInput;
    }
    if ($officeLongitudeInput !== '' && is_numeric($officeLongitudeInput)) {
        $officeLongitude = (float) $officeLongitudeInput;
    }

    if ($officeLatitude !== null && ($officeLatitude < -90 || $officeLatitude > 90)) {
        $officeLatitude = null;
    }
    if ($officeLongitude !== null && ($officeLongitude < -180 || $officeLongitude > 180)) {
        $officeLongitude = null;
    }

    if ($qrMaxDistance <= 0) {
        $qrMaxDistance = 20;
    }

    if ($hasQrSettingsColumns) {
        $statement = $pdo->prepare(
            "INSERT INTO scanner_settings (
                id, scanner_mode, api_endpoint, shift_start_time, qr_secret,
                allowed_network_prefix, office_latitude, office_longitude, qr_max_distance_meters, updated_at
            )
             VALUES (
                1, :scanner_mode, :api_endpoint, :shift_start_time, :qr_secret,
                :allowed_network_prefix, :office_latitude, :office_longitude, :qr_max_distance_meters, NOW()
             )
             ON DUPLICATE KEY UPDATE
                scanner_mode = VALUES(scanner_mode),
                api_endpoint = VALUES(api_endpoint),
                shift_start_time = VALUES(shift_start_time),
                qr_secret = VALUES(qr_secret),
                allowed_network_prefix = VALUES(allowed_network_prefix),
                office_latitude = VALUES(office_latitude),
                office_longitude = VALUES(office_longitude),
                qr_max_distance_meters = VALUES(qr_max_distance_meters),
                updated_at = NOW()"
        );
        $statement->execute(
            array(
                'scanner_mode' => $scannerMode,
                'api_endpoint' => $apiEndpoint !== '' ? $apiEndpoint : null,
                'shift_start_time' => $shiftStartTime,
                'qr_secret' => $qrSecret,
                'allowed_network_prefix' => $allowedNetworkPrefix !== '' ? $allowedNetworkPrefix : null,
                'office_latitude' => $officeLatitude,
                'office_longitude' => $officeLongitude,
                'qr_max_distance_meters' => $qrMaxDistance,
            )
        );
        set_flash('success', 'Settings updated.');
    } else {
        $statement = $pdo->prepare(
            "INSERT INTO scanner_settings (id, scanner_mode, api_endpoint, shift_start_time, updated_at)
             VALUES (1, :scanner_mode, :api_endpoint, :shift_start_time, NOW())
             ON DUPLICATE KEY UPDATE
                scanner_mode = VALUES(scanner_mode),
                api_endpoint = VALUES(api_endpoint),
                shift_start_time = VALUES(shift_start_time),
                updated_at = NOW()"
        );
        $statement->execute(
            array(
                'scanner_mode' => $scannerMode,
                'api_endpoint' => $apiEndpoint !== '' ? $apiEndpoint : null,
                'shift_start_time' => $shiftStartTime,
            )
        );
        set_flash('warning', 'Base settings updated. Run the latest database migration to enable QR policy settings.');
    }

    redirect(url('index.php?page=settings'));
}

if ($hasQrSettingsColumns) {
    $settings = $pdo->query(
        'SELECT scanner_mode, api_endpoint, shift_start_time, qr_secret, allowed_network_prefix, office_latitude, office_longitude, qr_max_distance_meters
         FROM scanner_settings
         WHERE id = 1'
    )->fetch();
} else {
    $settings = $pdo->query(
        'SELECT scanner_mode, api_endpoint, shift_start_time
         FROM scanner_settings
         WHERE id = 1'
    )->fetch();
}
if (!$settings) {
    $settings = array(
        'scanner_mode' => 'manual',
        'api_endpoint' => '',
        'shift_start_time' => '09:00:00',
    );
}

if (!$hasQrSettingsColumns) {
    $settings['qr_secret'] = 'TEAM_PROJECT_QR';
    $settings['allowed_network_prefix'] = '';
    $settings['office_latitude'] = '';
    $settings['office_longitude'] = '';
    $settings['qr_max_distance_meters'] = 20;
}

if (empty($settings['qr_secret'])) {
    $settings['qr_secret'] = 'TEAM_PROJECT_QR';
}
if (empty($settings['qr_max_distance_meters']) || (int) $settings['qr_max_distance_meters'] <= 0) {
    $settings['qr_max_distance_meters'] = 20;
}
?>
<div class="d-flex justify-content-between align-items-center page-header">
    <h1 class="h3 mb-0">System Settings</h1>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h2 class="h6 mb-0">Fingerprint Scanner Configuration</h2>
    </div>
    <div class="card-body">
        <form method="post" action="<?= h(url('index.php?page=settings')); ?>">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Scanner Mode</label>
                    <select class="form-select" name="scanner_mode">
                        <option value="manual" <?= $settings['scanner_mode'] === 'manual' ? 'selected' : ''; ?>>Manual (Testing)</option>
                        <option value="api" <?= $settings['scanner_mode'] === 'api' ? 'selected' : ''; ?>>API (Hardware Service)</option>
                        <option value="webauthn" <?= $settings['scanner_mode'] === 'webauthn' ? 'selected' : ''; ?>>WebAuthn (Experimental)</option>
                        <option value="thumb" <?= $settings['scanner_mode'] === 'thumb' ? 'selected' : ''; ?>>Thumb Scan (Manual Capture)</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Scanner API Endpoint</label>
                    <input class="form-control" name="api_endpoint" value="<?= h($settings['api_endpoint']); ?>" placeholder="http://127.0.0.1:5000/scan">
                    <div class="form-text">Used only when mode is API.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Shift Start Time</label>
                    <input class="form-control" type="time" step="1" name="shift_start_time" value="<?= h($settings['shift_start_time']); ?>">
                    <div class="form-text">Used for late detection.</div>
                </div>
                <?php if ($hasQrSettingsColumns): ?>
                    <div class="col-md-4">
                        <label class="form-label">Shared QR Secret</label>
                        <input class="form-control" name="qr_secret" value="<?= h($settings['qr_secret']); ?>">
                        <div class="form-text">Single QR token used by all users.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Allowed Wi-Fi Prefix</label>
                        <input class="form-control" name="allowed_network_prefix" value="<?= h($settings['allowed_network_prefix']); ?>" placeholder="192.168.1.">
                        <div class="form-text">Same Wi-Fi check via client IP prefix.</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Office Latitude</label>
                        <input class="form-control" type="number" step="0.0000001" name="office_latitude" value="<?= h((string) $settings['office_latitude']); ?>" placeholder="13.7563309">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Office Longitude</label>
                        <input class="form-control" type="number" step="0.0000001" name="office_longitude" value="<?= h((string) $settings['office_longitude']); ?>" placeholder="100.5017651">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">QR Range (m)</label>
                        <input class="form-control" type="number" min="1" name="qr_max_distance_meters" value="<?= h((string) $settings['qr_max_distance_meters']); ?>">
                    </div>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-warning mb-0">Run the latest database migration to enable QR network and distance policy settings.</div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary" type="submit">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <h2 class="h6">Integration Notes</h2>
        <ul class="mb-0">
            <li>API mode expects JSON with <code>fingerprint_id</code> key from your scanner service.</li>
            <li>Manual mode is useful during development and demos.</li>
            <li>WebAuthn mode attempts local biometric verification before manual ID entry.</li>
            <li>Thumb mode records attendance using thumb scan/manual thumb ID capture.</li>
            <li>Attendance terminal detection methods include fingerprint, card, face recognition, and QR code.</li>
            <li>QR attendance requires shared QR token, same Wi-Fi subnet prefix, and office geolocation radius check.</li>
        </ul>
    </div>
</div>
