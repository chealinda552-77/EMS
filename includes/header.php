<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$currentPage = $currentPage ?? '';
$flash = get_flash();
$user = current_user();
$isAdminUser = is_admin();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= h(csrf_token()); ?>">
    <title><?= h($pageTitle); ?> | <?= h(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= h(url('assets/css/style.css')); ?>" rel="stylesheet">
</head>
<body class="app-bg">
<?php if ($user): ?>
<nav class="navbar navbar-expand-lg navbar-dark app-nav shadow-sm">
    <div class="container-xl">
        <a class="navbar-brand app-brand" href="<?= h(url('index.php?page=dashboard')); ?>">
            <span class="brand-mark">EF</span>
            <span class="brand-text"><?= h(APP_NAME); ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 app-nav-links">
                <?php if ($isAdminUser): ?>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : ''; ?>" href="<?= h(url('index.php?page=dashboard')); ?>">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'employees' ? 'active' : ''; ?>" href="<?= h(url('index.php?page=employees')); ?>">Employees</a></li>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'attendance' ? 'active' : ''; ?>" href="<?= h(url('index.php?page=attendance')); ?>">Attendance</a></li>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'reports' ? 'active' : ''; ?>" href="<?= h(url('index.php?page=reports')); ?>">Reports</a></li>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'settings' ? 'active' : ''; ?>" href="<?= h(url('index.php?page=settings')); ?>">Settings</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'mobile-attendance' ? 'active' : ''; ?>" href="<?= h(url('index.php?page=mobile-attendance')); ?>">Mobile Attendance</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link <?= $currentPage === 'leaves' ? 'active' : ''; ?>" href="<?= h(url('index.php?page=leaves')); ?>">Leaves</a></li>
            </ul>
            <span class="navbar-text app-user me-3">Signed in as <?= h($user['employee_name'] ?? $user['username']); ?></span>
            <a class="btn btn-outline-light btn-sm app-logout" href="<?= h(url('index.php?page=logout')); ?>">Logout</a>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container-xl py-4 app-main">
    <?php if ($flash): ?>
        <div class="alert app-alert alert-<?= h($flash['type']); ?> alert-dismissible fade show" role="alert">
            <?= h($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
