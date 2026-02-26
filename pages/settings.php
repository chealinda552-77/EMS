<?php
declare(strict_types=1);

require_login();
$pageTitle = 'Settings';

$pdo = db();

if (is_post()) {
    require_csrf();

    $scannerMode = (string) ($_POST['scanner_mode'] ?? 'manual');
    $apiEndpoint = trim((string) ($_POST['api_endpoint'] ?? ''));
    $shiftStartTime = trim((string) ($_POST['shift_start_time'] ?? '09:00:00'));

    if (!in_array($scannerMode, array('manual', 'api', 'webauthn', 'thumb'), true)) {
        $scannerMode = 'manual';
    }

    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $shiftStartTime)) {
        $shiftStartTime = '09:00:00';
    } elseif (strlen($shiftStartTime) === 5) {
        $shiftStartTime .= ':00';
    }

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

    set_flash('success', 'Settings updated.');
    redirect(url('index.php?page=settings'));
}

$settings = $pdo->query('SELECT scanner_mode, api_endpoint, shift_start_time FROM scanner_settings WHERE id = 1')->fetch();
if (!$settings) {
    $settings = array(
        'scanner_mode' => 'manual',
        'api_endpoint' => '',
        'shift_start_time' => '09:00:00',
    );
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
        </ul>
    </div>
</div>
