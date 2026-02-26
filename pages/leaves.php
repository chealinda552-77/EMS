<?php
declare(strict_types=1);

require_login();
$pageTitle = 'Leaves';

$pdo = db();

if (is_post()) {
    require_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $leaveType = trim((string) ($_POST['leave_type'] ?? 'Annual'));
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($employeeId <= 0 || $startDate === '' || $endDate === '') {
            set_flash('danger', 'Employee, start date, and end date are required.');
            redirect(url('index.php?page=leaves'));
        }

        $statement = $pdo->prepare(
            'INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status, created_at)
             VALUES (:employee_id, :leave_type, :start_date, :end_date, :reason, :status, NOW())'
        );
        $statement->execute(
            array(
                'employee_id' => $employeeId,
                'leave_type' => $leaveType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => $reason !== '' ? $reason : null,
                'status' => 'pending',
            )
        );
        set_flash('success', 'Leave request created.');
        redirect(url('index.php?page=leaves'));
    }

    if ($action === 'status') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if ($id <= 0 || !in_array($status, array('approved', 'rejected'), true)) {
            set_flash('danger', 'Invalid leave status update.');
            redirect(url('index.php?page=leaves'));
        }

        $statement = $pdo->prepare('UPDATE leave_requests SET status = :status WHERE id = :id');
        $statement->execute(array('status' => $status, 'id' => $id));
        set_flash('success', 'Leave request updated.');
        redirect(url('index.php?page=leaves'));
    }
}

$employees = $pdo->query("SELECT id, employee_code, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name ASC")->fetchAll();
$leaves = $pdo->query(
    "SELECT l.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, e.employee_code
     FROM leave_requests l
     INNER JOIN employees e ON e.id = l.employee_id
     ORDER BY l.created_at DESC"
)->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center page-header">
    <h1 class="h3 mb-0">Leave Management</h1>
    <span class="text-muted"><?= h((string) count($leaves)); ?> requests</span>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h2 class="h6 mb-0">Create Leave Request</h2>
    </div>
    <div class="card-body">
        <form method="post" action="<?= h(url('index.php?page=leaves')); ?>">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <input type="hidden" name="action" value="create">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Employee</label>
                    <select class="form-select" name="employee_id" required>
                        <option value="">Select employee</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= h((string) $employee['id']); ?>">
                                <?= h($employee['employee_code'] . ' - ' . $employee['first_name'] . ' ' . $employee['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="leave_type">
                        <option value="Annual">Annual</option>
                        <option value="Sick">Sick</option>
                        <option value="Emergency">Emergency</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input class="form-control" type="date" name="start_date" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input class="form-control" type="date" name="end_date" required>
                </div>
                <div class="col-md-2 d-grid">
                    <label class="form-label invisible">Action</label>
                    <button class="btn btn-primary" type="submit">Save</button>
                </div>
                <div class="col-12">
                    <label class="form-label">Reason</label>
                    <textarea class="form-control" name="reason" rows="2"></textarea>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h2 class="h6 mb-0">Leave Requests</h2>
    </div>
    <div class="table-responsive">
        <table class="table app-table table-striped mb-0 align-middle">
            <thead>
            <tr>
                <th>Employee</th>
                <th>Type</th>
                <th>Dates</th>
                <th>Reason</th>
                <th>Status</th>
                <th class="text-end">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$leaves): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No leave requests.</td></tr>
            <?php else: ?>
                <?php foreach ($leaves as $leave): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= h($leave['employee_name']); ?></div>
                            <div class="small text-muted"><?= h($leave['employee_code']); ?></div>
                        </td>
                        <td><?= h($leave['leave_type']); ?></td>
                        <td><?= h($leave['start_date'] . ' to ' . $leave['end_date']); ?></td>
                        <td><?= h($leave['reason'] ?: '-'); ?></td>
                        <td>
                            <?php if ($leave['status'] === 'approved'): ?>
                                <span class="badge text-bg-success">Approved</span>
                            <?php elseif ($leave['status'] === 'rejected'): ?>
                                <span class="badge text-bg-danger">Rejected</span>
                            <?php else: ?>
                                <span class="badge text-bg-warning">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($leave['status'] === 'pending'): ?>
                                <form method="post" action="<?= h(url('index.php?page=leaves')); ?>" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= h((string) $leave['id']); ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button class="btn btn-sm btn-outline-success">Approve</button>
                                </form>
                                <form method="post" action="<?= h(url('index.php?page=leaves')); ?>" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= h((string) $leave['id']); ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button class="btn btn-sm btn-outline-danger">Reject</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small">No action</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
