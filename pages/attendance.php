<?php
declare(strict_types=1);

require_login();
$pageTitle = 'Attendance';

$pdo = db();
$settings = $pdo->query('SELECT scanner_mode, api_endpoint, shift_start_time FROM scanner_settings WHERE id = 1')->fetch();
$scannerMode = $settings['scanner_mode'] ?? 'manual';
$scannerEndpoint = $settings['api_endpoint'] ?? '';
$shiftStart = $settings['shift_start_time'] ?? '09:00:00';

$todayLogs = $pdo->query(
    "SELECT a.id, e.employee_code, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            a.clock_in_at, a.clock_out_at, a.clock_in_method, a.clock_out_method, a.is_late
     FROM attendance_logs a
     INNER JOIN employees e ON e.id = a.employee_id
     WHERE DATE(a.clock_in_at) = CURDATE()
     ORDER BY a.clock_in_at DESC"
)->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center page-header">
    <h1 class="h3 mb-0">Attendance Terminal</h1>
    <span class="text-muted">Mode: <?= h(strtoupper($scannerMode)); ?> | Shift: <?= h($shiftStart); ?></span>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">Biometric Clock-in / Clock-out</h2>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Configure scanner mode in Settings. In API mode, this page requests fingerprint data from your local scanner service endpoint. In thumb mode, enter/capture thumb ID.
                </p>
                <div class="mb-3">
                    <label class="form-label">Biometric ID</label>
                    <input id="fingerprintInput" class="form-control" placeholder="Scan or type fingerprint/thumb ID">
                </div>
                <div class="mb-3">
                    <label class="form-label">Detection Method</label>
                    <input id="fingerprintMethod" class="form-control" value="<?= h($scannerMode); ?>" readonly>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button id="scanBtn" class="btn btn-outline-primary">Scan Fingerprint</button>
                    <button id="clockInBtn" class="btn btn-success">Clock In</button>
                    <button id="clockOutBtn" class="btn btn-danger">Clock Out</button>
                </div>
                <div id="attendanceStatus" class="alert alert-secondary py-2 mb-0">Waiting for scan...</div>
            </div>
        </div>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h2 class="h6">Scanner Endpoint</h2>
                <code class="small"><?= h($scannerEndpoint ?: 'Not set'); ?></code>
                <p class="small text-muted mt-2 mb-0">
                    Expected API response format: <code>{"fingerprint_id":"FP-1001"}</code>
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">Today Attendance Logs</h2>
            </div>
            <div class="table-responsive">
                <table class="table app-table table-striped mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Methods</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$todayLogs): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No records today.</td></tr>
                    <?php else: ?>
                        <?php foreach ($todayLogs as $log): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= h($log['employee_name']); ?></div>
                                    <div class="small text-muted"><?= h($log['employee_code']); ?></div>
                                </td>
                                <td><?= h(format_datetime($log['clock_in_at'])); ?></td>
                                <td><?= h(format_datetime($log['clock_out_at'])); ?></td>
                                <td>
                                    <div class="small">In: <?= h($log['clock_in_method'] ?: '-'); ?></div>
                                    <div class="small">Out: <?= h($log['clock_out_method'] ?: '-'); ?></div>
                                </td>
                                <td>
                                    <?php if ((int) $log['is_late'] === 1): ?>
                                        <span class="badge text-bg-danger">Late</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-success">On Time</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
window.attendanceConfig = {
    mode: "<?= h($scannerMode); ?>",
    clockUrl: "<?= h(url('api/clock.php')); ?>",
    scanProxyUrl: "<?= h(url('api/scan_proxy.php')); ?>",
    csrfToken: "<?= h(csrf_token()); ?>"
};
</script>
