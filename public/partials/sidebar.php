<?php
declare(strict_types=1);
$currentUser = app_current_user();
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<div class="list-group mb-4 shadow-sm">
    <a class="list-group-item list-group-item-action <?php echo ($currentScript === 'dashboard.php') ? 'active' : ''; ?>" href="/dashboard.php">Dashboard</a>
    <a class="list-group-item list-group-item-action <?php echo ($currentScript === 'index.php') ? 'active' : ''; ?>" href="/index.php">Browse Events</a>
    <?php if ($currentUser !== null && $currentUser['role'] === 'organizer'): ?>
        <a class="list-group-item list-group-item-action <?php echo ($currentScript === 'create-event.php') ? 'active' : ''; ?>" href="/create-event.php">Create Event</a>
    <?php endif; ?>
    <a class="list-group-item list-group-item-action" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</a>
</div>
