<?php
declare(strict_types=1);

require_login();
$pageTitle = 'Employees';

$pdo = db();

if (is_post()) {
    require_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $employeeCode = trim((string) ($_POST['employee_code'] ?? ''));
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $position = trim((string) ($_POST['position'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? 'active'));
    $fingerprintId = trim((string) ($_POST['fingerprint_id'] ?? ''));
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $departmentId = $departmentId > 0 ? $departmentId : null;

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            set_flash('danger', 'Invalid employee ID.');
            redirect(url('index.php?page=employees'));
        }

        $statement = $pdo->prepare('DELETE FROM employees WHERE id = :id');
        $statement->execute(array('id' => $id));
        set_flash('success', 'Employee deleted.');
        redirect(url('index.php?page=employees'));
    }

    if (!in_array($status, array('active', 'inactive'), true)) {
        $status = 'active';
    }

    if ($employeeCode === '' || $firstName === '' || $lastName === '' || $fingerprintId === '') {
        set_flash('danger', 'Employee code, first name, last name, and fingerprint ID are required.');
        redirect(url('index.php?page=employees'));
    }

    try {
        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                set_flash('danger', 'Invalid employee ID for update.');
                redirect(url('index.php?page=employees'));
            }

            $statement = $pdo->prepare(
                'UPDATE employees
                 SET employee_code = :employee_code, first_name = :first_name, last_name = :last_name,
                     email = :email, phone = :phone, department_id = :department_id, position = :position,
                     status = :status, fingerprint_id = :fingerprint_id, updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute(array(
                'employee_code' => $employeeCode,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'department_id' => $departmentId,
                'position' => $position !== '' ? $position : null,
                'status' => $status,
                'fingerprint_id' => $fingerprintId,
                'id' => $id,
            ));
            set_flash('success', 'Employee updated.');
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO employees
                (employee_code, first_name, last_name, email, phone, department_id, position, status, fingerprint_id, created_at, updated_at)
                 VALUES
                (:employee_code, :first_name, :last_name, :email, :phone, :department_id, :position, :status, :fingerprint_id, NOW(), NOW())'
            );
            $statement->execute(array(
                'employee_code' => $employeeCode,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'department_id' => $departmentId,
                'position' => $position !== '' ? $position : null,
                'status' => $status,
                'fingerprint_id' => $fingerprintId,
            ));
            set_flash('success', 'Employee created.');
        }
    } catch (PDOException $exception) {
        $message = $exception->getCode() === '23000'
            ? 'Duplicate employee code or fingerprint ID.'
            : 'Database error while saving employee.';
        set_flash('danger', $message);
    }

    redirect(url('index.php?page=employees'));
}

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name ASC')->fetchAll();
$employees = $pdo->query(
    "SELECT e.*, d.name AS department_name
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     ORDER BY e.created_at DESC"
)->fetchAll();

$editEmployee = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
if ($editId > 0) {
    $statement = $pdo->prepare('SELECT * FROM employees WHERE id = :id LIMIT 1');
    $statement->execute(array('id' => $editId));
    $editEmployee = $statement->fetch();
}

$isEditing = is_array($editEmployee);
?>
<div class="d-flex justify-content-between align-items-center page-header">
    <h1 class="h3 mb-0">Employees</h1>
    <span class="text-muted"><?= h((string) count($employees)); ?> total records</span>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h2 class="h6 mb-0"><?= $isEditing ? 'Edit Employee' : 'Add Employee'; ?></h2>
    </div>
    <div class="card-body">
        <form method="post" action="<?= h(url('index.php?page=employees')); ?>">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
            <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create'; ?>">
            <?php if ($isEditing): ?>
                <input type="hidden" name="id" value="<?= h((string) $editEmployee['id']); ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Employee Code</label>
                    <input class="form-control" name="employee_code" required value="<?= h($editEmployee['employee_code'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">First Name</label>
                    <input class="form-control" name="first_name" required value="<?= h($editEmployee['first_name'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Last Name</label>
                    <input class="form-control" name="last_name" required value="<?= h($editEmployee['last_name'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fingerprint ID</label>
                    <input class="form-control" name="fingerprint_id" required value="<?= h($editEmployee['fingerprint_id'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input class="form-control" name="email" type="email" value="<?= h($editEmployee['email'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Phone</label>
                    <input class="form-control" name="phone" value="<?= h($editEmployee['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Department</label>
                    <select class="form-select" name="department_id">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $department): ?>
                            <option
                                value="<?= h((string) $department['id']); ?>"
                                <?= isset($editEmployee['department_id']) && (int) $editEmployee['department_id'] === (int) $department['id'] ? 'selected' : ''; ?>
                            >
                                <?= h($department['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active" <?= ($editEmployee['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= ($editEmployee['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Position</label>
                    <input class="form-control" name="position" value="<?= h($editEmployee['position'] ?? ''); ?>">
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= $isEditing ? 'Save Changes' : 'Create Employee'; ?></button>
                <?php if ($isEditing): ?>
                    <a class="btn btn-outline-secondary" href="<?= h(url('index.php?page=employees')); ?>">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h2 class="h6 mb-0">Employee List</h2>
    </div>
    <div class="table-responsive">
        <table class="table app-table table-striped mb-0 align-middle">
            <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Department</th>
                <th>Position</th>
                <th>Fingerprint ID</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$employees): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No employees found.</td></tr>
            <?php else: ?>
                <?php foreach ($employees as $employee): ?>
                    <tr>
                        <td><?= h($employee['employee_code']); ?></td>
                        <td>
                            <div class="fw-semibold"><?= h($employee['first_name'] . ' ' . $employee['last_name']); ?></div>
                            <div class="small text-muted"><?= h($employee['email'] ?: '-'); ?></div>
                        </td>
                        <td><?= h($employee['department_name'] ?: '-'); ?></td>
                        <td><?= h($employee['position'] ?: '-'); ?></td>
                        <td><code><?= h($employee['fingerprint_id']); ?></code></td>
                        <td>
                            <?php if ($employee['status'] === 'active'): ?>
                                <span class="badge text-bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?= h(url('index.php?page=employees&edit=' . (int) $employee['id'])); ?>">Edit</a>
                            <form method="post" action="<?= h(url('index.php?page=employees')); ?>" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= h((string) $employee['id']); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this employee?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
