<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!is_logged_in() && is_post()) {
    require_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        set_flash('danger', 'Username and password are required.');
        redirect(url('index.php'));
    }

    if (!login_user($username, $password)) {
        set_flash('danger', 'Invalid username or password.');
        redirect(url('index.php'));
    }

    set_flash('success', 'Login successful.');
    redirect(url('index.php?page=dashboard'));
}

if (!is_logged_in()) {
    $pageTitle = 'Login';
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="row justify-content-center align-items-center login-shell">
        <div class="col-lg-10 col-xl-9">
            <div class="card login-card border-0">
                <div class="row g-0">
                    <div class="col-lg-6 login-side text-white p-4 p-md-5">
                        <h1 class="h3 mb-3"><?= h(APP_NAME); ?></h1>
                        <p class="mb-4">
                            Secure employee attendance, leave tracking, and reporting in one clean management workspace.
                        </p>
                        <ul class="login-points mb-0">
                            <li>Fast biometric clock-in and clock-out flow</li>
                            <li>Clear dashboards with real-time attendance insights</li>
                            <li>Simple employee and leave request management</li>
                        </ul>
                    </div>
                    <div class="col-lg-6 p-4 p-md-5 bg-white">
                        <h2 class="h4 mb-4 login-form-title">Welcome Back</h2>
                        <form method="post" action="<?= h(url('index.php')); ?>">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()); ?>">
                            <div class="mb-3">
                                <label class="form-label" for="username">Username</label>
                                <input type="text" class="form-control" id="username" name="username" autocomplete="username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Sign In</button>
                        </form>
                        <div class="login-hint small mt-4">
                            Default admin: <code>admin</code> / <code>admin123</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$pages = array(
    'dashboard' => __DIR__ . '/pages/dashboard.php',
    'employees' => __DIR__ . '/pages/employees.php',
    'attendance' => __DIR__ . '/pages/attendance.php',
    'reports' => __DIR__ . '/pages/reports.php',
    'leaves' => __DIR__ . '/pages/leaves.php',
    'settings' => __DIR__ . '/pages/settings.php',
    'logout' => __DIR__ . '/pages/logout.php',
);

$currentPage = (string) ($_GET['page'] ?? 'dashboard');
if (!array_key_exists($currentPage, $pages)) {
    $currentPage = 'dashboard';
}

ob_start();
require $pages[$currentPage];
$content = ob_get_clean();

$pageTitle = $pageTitle ?? ucfirst($currentPage);
include __DIR__ . '/includes/header.php';
echo $content;
include __DIR__ . '/includes/footer.php';
