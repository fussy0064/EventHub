<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';

$pageTitle = $pageTitle ?? 'EventHub';
$currentUser = app_current_user();
$successMessage = app_get_flash('success');
$errorMessage = app_get_flash('error');
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo app_url('/assets/css/style.css'); ?>" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom">
    <div class="container-fluid">
        <?php if ($currentUser !== null): ?>
            <button class="btn btn-outline-light me-2 sidebar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="Toggle navigation menu">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/></svg>
            </button>
        <?php endif; ?>
        <a class="navbar-brand fw-semibold" href="<?php echo app_url('/index.php'); ?>">EventHub</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="<?php echo app_url('/index.php'); ?>">Browse</a></li>
                <?php if ($currentUser === null): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo app_url('/login.php'); ?>">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo app_url('/register.php'); ?>">Register</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo app_url('/dashboard.php'); ?>">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo app_url('/logout.php'); ?>">Logout</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if ($currentUser !== null): ?>
<!-- Offcanvas Sidebar Navigation -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="list-group list-group-flush">
            <a class="list-group-item list-group-item-action <?php echo ($currentScript === 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo app_url('/dashboard.php'); ?>">Dashboard</a>
            <a class="list-group-item list-group-item-action <?php echo ($currentScript === 'index.php') ? 'active' : ''; ?>" href="<?php echo app_url('/index.php'); ?>">Browse Events</a>
            <?php if ($currentUser['role'] === 'organizer'): ?>
                <a class="list-group-item list-group-item-action <?php echo ($currentScript === 'create-event.php') ? 'active' : ''; ?>" href="<?php echo app_url('/create-event.php'); ?>">Create Event</a>
            <?php endif; ?>
            <a class="list-group-item list-group-item-action" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal" onclick="document.getElementById('sidebarOffcanvas').classList.remove('show');">Change Password</a>
        </div>
    </div>
</div>
<?php endif; ?>

<main class="container py-4">
    <?php if ($successMessage !== null): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($errorMessage !== null): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
