<?php
declare(strict_types=1);

require_login();
require_admin();
$pageTitle = 'Reports';

$pdo = db();
$from = (string) ($_GET['from'] ?? date('Y-m-01'));
$to = (string) ($_GET['to'] ?? date('Y-m-d'));

$fromDateTime = DateTime::createFromFormat('Y-m-d', $from);
$toDateTime = DateTime::createFromFormat('Y-m-d', $to);
if (!$fromDateTime || !$toDateTime) {
    $from = date('Y-m-01');
    $to = date('Y-m-d');
}

if ($from > $to) {
    $temp = $from;
    $from = $to;
    $to = $temp;
}

$statement = $pdo->prepare(
    "SELECT a.id, e.employee_code, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            a.clock_in_at, a.clock_out_at, a.is_late,
            CASE
                WHEN a.clock_out_at IS NULL THEN NULL
                ELSE TIMESTAMPDIFF(MINUTE, a.clock_in_at, a.clock_out_at)
            END AS worked_minutes
     FROM attendance_logs a
     INNER JOIN employees e ON e.id = a.employee_id
     WHERE DATE(a.clock_in_at) BETWEEN :from_date AND :to_date
     ORDER BY a.clock_in_at DESC"
);
$statement->execute(
    array(
        'from_date' => $from,
        'to_date' => $to,
    )
);
$records = $statement->fetchAll();

$totalMinutes = 0;
$completeShifts = 0;
$lateCount = 0;
foreach ($records as $record) {
    if ($record['worked_minutes'] !== null) {
        $totalMinutes += (int) $record['worked_minutes'];
        $completeShifts++;
    }
    if ((int) $record['is_late'] === 1) {
        $lateCount++;
    }
}

$totalHours = $totalMinutes / 60;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 page-header">
    <h1 class="h3 mb-0">Attendance Reports</h1>
    <a class="btn btn-outline-success" href="<?= h(url('api/export_csv.php?from=' . urlencode($from) . '&to=' . urlencode($to))); ?>">Export CSV</a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="<?= h(url('index.php')); ?>" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="reports">
            <div class="col-md-4">
                <label class="form-label">From</label>
                <input class="form-control" type="date" name="from" value="<?= h($from); ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">To</label>
                <input class="form-control" type="date" name="to" value="<?= h($to); ?>" required>
            </div>
            <div class="col-md-4 d-grid">
                <button class="btn btn-primary" type="submit">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Records</div>
                <div class="h4 mb-0"><?= h((string) count($records)); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Completed Shifts</div>
                <div class="h4 mb-0"><?= h((string) $completeShifts); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Worked Hours</div>
                <div class="h4 mb-0"><?= h(number_format($totalHours, 2)); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0">Report Detail</h2>
        <span class="badge text-bg-danger">Late count: <?= h((string) $lateCount); ?></span>
    </div>
    <div class="table-responsive">
        <table class="table app-table table-striped mb-0 align-middle">
            <thead>
            <tr>
                <th>Employee</th>
                <th>Clock In</th>
                <th>Clock Out</th>
                <th>Worked Hours</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$records): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No records for selected dates.</td></tr>
            <?php else: ?>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= h($record['employee_name']); ?></div>
                            <div class="small text-muted"><?= h($record['employee_code']); ?></div>
                        </td>
                        <td><?= h(format_datetime($record['clock_in_at'])); ?></td>
                        <td><?= h(format_datetime($record['clock_out_at'])); ?></td>
                        <td>
                            <?php if ($record['worked_minutes'] === null): ?>
                                <span class="text-muted">Open Shift</span>
                            <?php else: ?>
                                <?= h(number_format(((int) $record['worked_minutes']) / 60, 2)); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $record['is_late'] === 1): ?>
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
