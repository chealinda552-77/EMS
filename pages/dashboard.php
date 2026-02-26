<?php
declare(strict_types=1);

require_login();
$pageTitle = 'Dashboard';

$pdo = db();

$totalEmployees = (int) $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
$todayClockIns = (int) $pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE DATE(clock_in_at) = CURDATE()")->fetchColumn();
$currentlyInside = (int) $pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE clock_out_at IS NULL")->fetchColumn();
$pendingLeaves = (int) $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();

$shiftStart = '09:00:00';
$shiftRow = $pdo->query('SELECT shift_start_time FROM scanner_settings WHERE id = 1')->fetch();
if ($shiftRow && !empty($shiftRow['shift_start_time'])) {
    $shiftStart = (string) $shiftRow['shift_start_time'];
}

$lateStmt = $pdo->prepare('SELECT COUNT(*) FROM attendance_logs WHERE DATE(clock_in_at) = CURDATE() AND TIME(clock_in_at) > :shift_start');
$lateStmt->execute(array('shift_start' => $shiftStart));
$lateToday = (int) $lateStmt->fetchColumn();

$recentLogs = $pdo->query(
    "SELECT a.id, e.employee_code, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, a.clock_in_at, a.clock_out_at, a.is_late
     FROM attendance_logs a
     INNER JOIN employees e ON e.id = a.employee_id
     ORDER BY a.clock_in_at DESC
     LIMIT 10"
)->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center page-header">
    <h1 class="h3 mb-0">Dashboard</h1>
    <span class="text-muted">Shift start: <?= h($shiftStart); ?></span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card metric-card metric-card--ocean border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="metric-title">Active Employees</div>
                <div class="metric-value"><?= h((string) $totalEmployees); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card metric-card metric-card--teal border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="metric-title">Today Clock-ins</div>
                <div class="metric-value"><?= h((string) $todayClockIns); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card metric-card metric-card--amber border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="metric-title">Inside Office</div>
                <div class="metric-value"><?= h((string) $currentlyInside); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card metric-card metric-card--rose border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="metric-title">Late Today</div>
                <div class="metric-value"><?= h((string) $lateToday); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Recent Attendance</h2>
                <a class="btn btn-sm btn-outline-primary" href="<?= h(url('index.php?page=reports')); ?>">Open Reports</a>
            </div>
            <div class="table-responsive">
                <table class="table app-table table-striped mb-0">
                    <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$recentLogs): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No attendance records yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= h($log['employee_name']); ?></div>
                                    <div class="small text-muted"><?= h($log['employee_code']); ?></div>
                                </td>
                                <td><?= h(format_datetime($log['clock_in_at'])); ?></td>
                                <td><?= h(format_datetime($log['clock_out_at'])); ?></td>
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
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">Quick Stats</h2>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Pending Leave Requests</span>
                    <span class="badge text-bg-secondary"><?= h((string) $pendingLeaves); ?></span>
                </div>
                <div class="d-grid gap-2">
                    <a class="btn btn-outline-success" href="<?= h(url('index.php?page=attendance')); ?>">Open Attendance</a>
                    <a class="btn btn-outline-secondary" href="<?= h(url('index.php?page=employees')); ?>">Manage Employees</a>
                    <a class="btn btn-outline-dark" href="<?= h(url('index.php?page=settings')); ?>">Scanner Settings</a>
                </div>
            </div>
        </div>
    </div>
</div>
