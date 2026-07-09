<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/User.php';

app_require_login();

$db = app_db();
$currentUser = app_current_user();

$user = User::findById($db, $currentUser['id']);
if ($user === null) {
    app_logout_user();
    app_redirect('/login.php');
}

// Admins can navigate into a specific organizer's dashboard and perform the
// same actions that organizer could (create/edit/delete their events, confirm
// payments, etc.) without changing which account is actually logged in.
$actingOrganizer = null;
$organizerIdParam = (int) ($_POST['organizer_id'] ?? $_GET['organizer_id'] ?? 0);
if ($user->getRole() === 'admin' && $organizerIdParam > 0) {
    require_once __DIR__ . '/../classes/Organizer.php';
    $candidateOrganizer = User::findById($db, $organizerIdParam);
    if ($candidateOrganizer !== null && $candidateOrganizer->getRole() === 'organizer') {
        $actingOrganizer = $candidateOrganizer;
    }
}
$backSuffix = $actingOrganizer !== null ? ('?organizer_id=' . $actingOrganizer->getId()) : '';

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_booking') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    require_once __DIR__ . '/../classes/Booking.php';
    $booking = Booking::find($db, $bookingId);

    if ($booking !== null) {
        // Attendees can only cancel their own bookings
        if ($user->getRole() === 'attendee' && $booking->getUserId() !== $user->getId()) {
            app_set_flash('error', 'Unauthorized action.');
        } elseif ($booking->getStatus() === 'confirmed' && $user->getRole() !== 'admin') {
            // Once a booking is confirmed, only an admin may edit/cancel it —
            // the attendee (or organizer) can no longer change it themselves.
            app_set_flash('error', 'This ticket is already confirmed. Only an admin can cancel or edit a confirmed booking.');
        } else {
            $booking->setStatus('cancelled');
            if ($booking->save()) {
                app_set_flash('success', 'Booking cancelled successfully.');
            } else {
                app_set_flash('error', 'Failed to cancel booking.');
            }
        }
    } else {
        app_set_flash('error', 'Booking not found.');
    }
    app_redirect('/dashboard.php' . $backSuffix);
}

// Handle organizer (or admin) confirming a booking's payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'organizer_confirm_payment' && in_array($user->getRole(), ['organizer', 'admin'], true)) {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    require_once __DIR__ . '/../classes/Booking.php';
    require_once __DIR__ . '/../classes/Event.php';
    $booking = Booking::find($db, $bookingId);

    if ($booking === null) {
        app_set_flash('error', 'Booking not found.');
    } else {
        $event = Event::find($db, $booking->getEventId());
        if ($event === null || ($user->getRole() === 'organizer' && $event->getOrganizerId() !== $user->getId())) {
            app_set_flash('error', 'Unauthorized action.');
        } else {
            $booking->setStatus('confirmed');
            if ($booking->save()) {
                app_set_flash('success', 'Payment confirmed. Ticket is now valid.');
            } else {
                app_set_flash('error', 'Failed to confirm payment.');
            }
        }
    }
    app_redirect('/dashboard.php' . $backSuffix);
}

// Handle Event Deletion (organizer can delete own events, admin can delete any event)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_event' && in_array($user->getRole(), ['organizer', 'admin'], true)) {
    require_once __DIR__ . '/../classes/Event.php';
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $eventToDelete = Event::find($db, $eventId);

    if ($eventToDelete === null) {
        app_set_flash('error', 'Event not found.');
    } elseif ($user->getRole() === 'organizer' && $eventToDelete->getOrganizerId() !== $user->getId()) {
        app_set_flash('error', 'Unauthorized action.');
    } else {
        if (Event::deleteWithRelations($db, $eventId)) {
            app_set_flash('success', 'Event deleted successfully.');
        } else {
            app_set_flash('error', 'Failed to delete event.');
        }
    }
    app_redirect('/dashboard.php' . $backSuffix);
}

if ($actingOrganizer !== null) {
    // Show the exact same dashboard the organizer would see, so an admin can
    // do the same work as that organizer (create/edit/delete events, confirm
    // payments, etc.) without switching accounts.
    $dashboardData = $actingOrganizer->getDashboard();
    $dashboardData['title'] = 'Managing Organizer: ' . $actingOrganizer->getName();
} else {
    $dashboardData = $user->getDashboard();
}
$bookings = $dashboardData['bookings'] ?? [];
$users = $dashboardData['users'] ?? [];

// Handle Changing Own Password (available to all logged in users)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_own_password') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmNewPassword = (string) ($_POST['confirm_new_password'] ?? '');

    if (trim($newPassword) === '') {
        app_set_flash('error', 'New password cannot be empty.');
    } elseif ($newPassword !== $confirmNewPassword) {
        app_set_flash('error', 'New passwords do not match.');
    } elseif (!password_verify($currentPassword, $user->getPasswordHash())) {
        app_set_flash('error', 'Incorrect current password.');
    } else {
        $user->setPassword($newPassword);
        if ($user->save()) {
            app_set_flash('success', 'Your password has been changed successfully.');
        } else {
            app_set_flash('error', 'Failed to update your password.');
        }
    }
    app_redirect('/dashboard.php' . $backSuffix);
}

// Handle Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user->getRole() === 'admin') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'admin_delete_user') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === $user->getId()) {
            app_set_flash('error', 'You cannot delete yourself.');
        } else {
            $targetUser = User::findById($db, $targetUserId);
            if ($targetUser !== null) {
                if ($targetUser->delete()) {
                    app_set_flash('success', 'User deleted successfully.');
                } else {
                    app_set_flash('error', 'Failed to delete user.');
                }
            } else {
                app_set_flash('error', 'User not found.');
            }
        }
        app_redirect('/dashboard.php' . $backSuffix);
    }

    if ($action === 'admin_change_password') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $newPassword = (string) ($_POST['new_password'] ?? '');
        
        if (trim($newPassword) === '') {
            app_set_flash('error', 'Password cannot be empty.');
        } else {
            $targetUser = User::findById($db, $targetUserId);
            if ($targetUser !== null) {
                $targetUser->setPassword($newPassword);
                if ($targetUser->save()) {
                    app_set_flash('success', 'Password updated successfully.');
                } else {
                    app_set_flash('error', 'Failed to update password.');
                }
            } else {
                app_set_flash('error', 'User not found.');
            }
        }
        app_redirect('/dashboard.php' . $backSuffix);
    }

    if ($action === 'admin_change_role') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $newRole = (string) ($_POST['new_role'] ?? '');
        
        if ($targetUserId === $user->getId()) {
            app_set_flash('error', 'You cannot change your own role.');
        } else {
            $targetUser = User::findById($db, $targetUserId);
            if ($targetUser !== null) {
                $targetUser->setRole($newRole);
                if ($targetUser->save()) {
                    app_set_flash('success', 'User role updated successfully.');
                } else {
                    app_set_flash('error', 'Failed to update user role.');
                }
            } else {
                app_set_flash('error', 'User not found.');
            }
        }
        app_redirect('/dashboard.php' . $backSuffix);
    }

    if ($action === 'admin_approve_organizer') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $approveAction = (string) ($_POST['approve_action'] ?? '');
        
        $targetUser = User::findById($db, $targetUserId);
        if ($targetUser !== null && $targetUser->getRole() === 'organizer') {
            if ($approveAction === 'approve') {
                $targetUser->setApproved(true);
                if ($targetUser->save()) {
                    app_set_flash('success', 'Organizer approved successfully.');
                } else {
                    app_set_flash('error', 'Failed to approve organizer.');
                }
            } elseif ($approveAction === 'reject') {
                // Rejecting keeps the account on file (marked rejected) instead of
                // deleting it, so the organizer is notified rather than vanishing.
                $targetUser->setRejected();
                if ($targetUser->save()) {
                    app_set_flash('success', 'Organizer rejected. They will be notified if they try to log in.');
                } else {
                    app_set_flash('error', 'Failed to reject organizer.');
                }
            }
        } else {
            app_set_flash('error', 'User not found or is not an organizer.');
        }
        app_redirect('/dashboard.php' . $backSuffix);
    }
}

// Filter bookings if search is submitted
$bookingQuery = trim((string) ($_GET['bq'] ?? ''));
if ($bookingQuery !== '') {
    $bqLower = strtolower($bookingQuery);
    $bookings = array_filter($bookings, function (array $b) use ($bqLower) {
        return str_contains(strtolower($b['event_name'] ?? ''), $bqLower) ||
               str_contains(strtolower($b['user_name'] ?? $b['event_location'] ?? ''), $bqLower) ||
               str_contains(strtolower($b['user_email'] ?? ''), $bqLower) ||
               str_contains(strtolower($b['status'] ?? ''), $bqLower);
    });
}

// Filter users if search is submitted
$userQuery = trim((string) ($_GET['uq'] ?? ''));
if ($userQuery !== '') {
    $uqLower = strtolower($userQuery);
    $users = array_filter($users, function (User $u) use ($uqLower) {
        return str_contains(strtolower($u->getName()), $uqLower) ||
               str_contains(strtolower($u->getEmail()), $uqLower) ||
               str_contains(strtolower($u->getRole()), $uqLower);
    });
}

$pageTitle = $dashboardData['title'];
require __DIR__ . '/partials/header.php';
?>
<div class="row g-4">
    <div class="col-12">
        <?php if ($actingOrganizer !== null): ?>
            <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <strong>Managing as Organizer:</strong>
                    <?php echo htmlspecialchars($actingOrganizer->getName(), ENT_QUOTES, 'UTF-8'); ?>
                    <span class="text-muted">(<?php echo htmlspecialchars($actingOrganizer->getEmail(), ENT_QUOTES, 'UTF-8'); ?>)</span>
                    — you're seeing and can do everything this organizer can.
                </div>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo app_url('/dashboard.php'); ?>">Back to Admin Dashboard</a>
            </div>
        <?php endif; ?>
        <!-- Welcome banner -->
        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h1 class="h4 mb-1">Welcome, <?php echo htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8'); ?></h1>
                    <div class="text-muted">Account Email: <?php echo htmlspecialchars($user->getEmail(), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="d-flex gap-2">
                <?php if ($user->getRole() === 'organizer' || $user->getRole() === 'admin'): ?>
                    <a class="btn btn-primary" href="<?php echo app_url('/create-event.php' . $backSuffix); ?>">Create Event</a>
                <?php endif; ?>
                <?php if ($user->getRole() === 'admin'): ?>
                    <a class="btn btn-outline-primary" href="<?php echo app_url('/create-user.php'); ?>">Create User</a>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($dashboardData['type'] === 'admin'): ?>
            <!-- Pending Organizers Alert -->
            <div id="pending-organizers-banner" class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert" style="<?php echo (($dashboardData['pending_organizers'] ?? 0) > 0) ? '' : 'display:none;'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
                <strong id="pending-organizers-count"><?php echo (int) ($dashboardData['pending_organizers'] ?? 0); ?> organizer(s)</strong> awaiting your approval.
            </div>

            <!-- Admin Dashboard -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <h2 class="h5 mb-0">System Users</h2>
                        <form class="d-flex gap-2" method="get">
                            <input type="search" name="uq" class="form-control form-control-sm" placeholder="Search users" value="<?php echo htmlspecialchars($userQuery, ENT_QUOTES, 'UTF-8'); ?>">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No users found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                    <?php
                                        $userRowClass = '';
                                        if ($u->getRole() === 'organizer') {
                                            if ($u->isRejected()) {
                                                $userRowClass = 'table-danger';
                                            } elseif ($u->isPendingApproval()) {
                                                $userRowClass = 'table-warning';
                                            }
                                        }
                                    ?>
                                    <tr id="user-row-<?php echo $u->getId(); ?>" class="<?php echo $userRowClass; ?>">
                                        <td><?php echo $u->getId(); ?></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($u->getName(), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($u->getEmail(), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if ($u->getId() !== $user->getId()): ?>
                                                <form method="post" action="" class="d-inline-flex align-items-center gap-1">
                                                    <input type="hidden" name="user_id" value="<?php echo $u->getId(); ?>">
                                                    <select name="new_role" class="form-select form-select-sm" onchange="this.form.submit()">
                                                        <option value="attendee" <?php echo ($u->getRole() === 'attendee') ? 'selected' : ''; ?>>Attendee</option>
                                                        <option value="organizer" <?php echo ($u->getRole() === 'organizer') ? 'selected' : ''; ?>>Organizer</option>
                                                        <option value="admin" <?php echo ($u->getRole() === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                                    </select>
                                                    <input type="hidden" name="action" value="admin_change_role">
                                                </form>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Admin</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($u->getRole() === 'organizer' && $u->isRejected()): ?>
                                                <span class="badge bg-danger js-status-badge">Rejected</span>
                                                <div class="d-inline-flex gap-1 mt-1">
                                                    <button type="button" class="btn btn-sm btn-success js-admin-action"
                                                        data-action="admin_approve_organizer" data-approve-action="approve"
                                                        data-user-id="<?php echo $u->getId(); ?>"
                                                        data-confirm="Approve this organizer? This reverses the rejection.">Approve</button>
                                                </div>
                                            <?php elseif ($u->getRole() === 'organizer' && $u->isPendingApproval()): ?>
                                                <span class="badge bg-warning text-dark js-status-badge">Pending Approval</span>
                                                <div class="d-inline-flex gap-1 mt-1">
                                                    <button type="button" class="btn btn-sm btn-success js-admin-action"
                                                        data-action="admin_approve_organizer" data-approve-action="approve"
                                                        data-user-id="<?php echo $u->getId(); ?>"
                                                        data-confirm="Approve this organizer?">Approve</button>
                                                    <button type="button" class="btn btn-sm btn-danger js-admin-action"
                                                        data-action="admin_approve_organizer" data-approve-action="reject"
                                                        data-user-id="<?php echo $u->getId(); ?>"
                                                        data-confirm="Reject this organizer account? Their account is kept but they will not be able to log in, and will be notified if they try.">Reject</button>
                                                </div>
                                            <?php elseif ($u->isApproved()): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-inline-flex gap-2 align-items-center">
                                                <?php if ($u->getRole() === 'organizer' && $u->isApproved()): ?>
                                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo app_url('/dashboard.php?organizer_id=' . $u->getId()); ?>">Manage</a>
                                                <?php endif; ?>
                                                <!-- Change Password Inline Form -->
                                                <form method="post" action="" class="d-inline-flex align-items-center gap-1" onsubmit="return confirm('Are you sure you want to change this user\'s password?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $u->getId(); ?>">
                                                    <div class="password-toggle-wrapper" style="width: 130px;">
                                                        <input type="password" name="new_password" class="form-control form-control-sm" placeholder="New password" required>
                                                        <span class="password-toggle-icon" onclick="togglePassword(this)">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="eye-icon"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/></svg>
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="eye-slash-icon d-none"><path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/><path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/><path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12-.708.708z"/></svg>
                                                        </span>
                                                    </div>
                                                    <button type="submit" name="action" value="admin_change_password" class="btn btn-sm btn-outline-secondary">Update Pass</button>
                                                </form>

                                                <?php if ($u->getId() !== $user->getId()): ?>
                                                    <button type="button" class="btn btn-sm btn-danger js-admin-action"
                                                        data-action="admin_delete_user"
                                                        data-user-id="<?php echo $u->getId(); ?>"
                                                        data-confirm="Are you sure you want to delete this user? This will also clean up all their booking history/events.">Delete</button>
                                                <?php else: ?>
                                                    <span class="text-muted small italic">(You)</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- All Events (Admin view) -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">All Events</h2>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Event</th>
                                <th>Created By</th>
                                <th>Date & Time</th>
                                <th>Location</th>
                                <th>From Price</th>
                                <th>Total Tickets Left</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($dashboardData['events'])): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No events created yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dashboardData['events'] as $ev): ?>
                                    <?php $evObj = $ev['object']; $statusBadge = $evObj->getStatusBadge(); ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($evObj->getName(), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($ev['organizer_name'], ENT_QUOTES, 'UTF-8'); ?> <span class="text-muted small">(<?php echo htmlspecialchars($ev['organizer_email'], ENT_QUOTES, 'UTF-8'); ?>)</span></td>
                                        <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($evObj->getDateTime())), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($evObj->getLocation(), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>Tshs <?php echo number_format($evObj->getPrice(), 2); ?></td>
                                        <td><?php echo $evObj->getTicketsAvailable(); ?></td>
                                        <td><span class="badge <?php echo $statusBadge['badge']; ?>"><?php echo htmlspecialchars($statusBadge['label'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <?php if ($evObj->getStatus() !== 'done'): ?>
                                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo app_url('/edit-event.php?id=' . $evObj->getId()); ?>">Edit</a>
                                                <?php endif; ?>
                                                <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete this event? This will also remove its ticket classes and bookings.');">
                                                    <input type="hidden" name="event_id" value="<?php echo $evObj->getId(); ?>">
                                                    <button type="submit" name="action" value="delete_event" class="btn btn-sm btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($dashboardData['type'] === 'organizer'): ?>
            <!-- Organizer Dashboard Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small uppercase fw-semibold">Total Events</div>
                            <div class="fs-2 fw-bold text-primary"><?php echo $dashboardData['stats']['total_events']; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small uppercase fw-semibold">Tickets Sold</div>
                            <div class="fs-2 fw-bold text-success"><?php echo $dashboardData['stats']['tickets_sold']; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small uppercase fw-semibold">Total Revenue</div>
                            <div class="fs-2 fw-bold text-dark">Tshs <?php echo number_format($dashboardData['stats']['revenue'], 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Organizer Events List -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Your Events & Allocations</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Date & Time</th>
                            <th>Location</th>
                            <th>Classes (Price / Left)</th>
                            <th>Tickets Sold</th>
                            <th>Revenue</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($dashboardData['events'])): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">You have not created any events yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($dashboardData['events'] as $e): ?>
                                <?php $eventObj = $e['object']; $statusBadge = $eventObj->getStatusBadge(); ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($eventObj->getName(), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($eventObj->getDateTime())), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($eventObj->getLocation(), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php foreach ($e['classes'] as $class): ?>
                                            <div class="small">
                                                <span class="fw-semibold"><?php echo htmlspecialchars($class->getClassName(), ENT_QUOTES, 'UTF-8'); ?>:</span>
                                                Tshs <?php echo number_format($class->getPrice(), 2); ?>
                                                &middot;
                                                <?php if ($class->getTicketsAvailable() > 0): ?>
                                                    <?php echo $class->getTicketsAvailable(); ?> left
                                                <?php else: ?>
                                                    <span class="text-danger">Sold Out</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo $e['tickets_sold']; ?></td>
                                    <td class="fw-semibold">Tshs <?php echo number_format($e['revenue'], 2); ?></td>
                                    <td><span class="badge <?php echo $statusBadge['badge']; ?>"><?php echo htmlspecialchars($statusBadge['label'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <?php if ($eventObj->getStatus() !== 'done'): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="<?php echo app_url('/edit-event.php?id=' . $eventObj->getId()); ?>">Edit</a>
                                            <?php endif; ?>
                                            <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete this event? This will also remove its ticket classes and bookings.');">
                                                <input type="hidden" name="event_id" value="<?php echo $eventObj->getId(); ?>">
                                                <button type="submit" name="action" value="delete_event" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Organizer Attendee Manifests -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <h2 class="h5 mb-0">Attendee Booking Manifest</h2>
                        <form class="d-flex gap-2" method="get">
                            <input type="search" name="bq" class="form-control form-control-sm" placeholder="Search manifest" value="<?php echo htmlspecialchars($bookingQuery, ENT_QUOTES, 'UTF-8'); ?>">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Ticket ID</th>
                                <th>Attendee</th>
                                <th>Email</th>
                                <th>Event</th>
                                <th>Class</th>
                                <th>Tickets Booked</th>
                                <th>Revenue</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No bookings on file.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <td class="small text-muted"><?php echo htmlspecialchars(app_ticket_code((int) $b['event_id'], (int) $b['id']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($b['user_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($b['user_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($b['event_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($b['ticket_class'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo $b['tickets_booked']; ?></td>
                                        <td>Tshs <?php echo number_format($b['tickets_booked'] * (float) $b['event_price'], 2); ?></td>
                                        <td>
                                            <?php if ($b['status'] === 'confirmed'): ?>
                                                <span class="badge bg-success">Confirmed</span>
                                            <?php elseif ($b['status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark">Pending Payment</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Cancelled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                            <?php if ($b['status'] === 'pending'): ?>
                                                <form method="post" action="" onsubmit="return confirm('Confirm that payment was received for this booking?');">
                                                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                    <button type="submit" name="action" value="organizer_confirm_payment" class="btn btn-sm btn-success">Confirm Payment</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($b['status'] === 'cancelled'): ?>
                                                <span class="text-muted small">-</span>
                                            <?php elseif ($b['status'] === 'confirmed' && $user->getRole() !== 'admin'): ?>
                                                <span class="text-muted small" title="Only an admin can cancel a confirmed booking.">Locked</span>
                                            <?php else: ?>
                                                <form method="post" action="" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                    <button type="submit" name="action" value="cancel_booking" class="btn btn-sm btn-danger">Cancel</button>
                                                </form>
                                            <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Attendee Booking History -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <h2 class="h5 mb-0">Your Booking History</h2>
                        <form class="d-flex gap-2" method="get">
                            <input type="search" name="bq" class="form-control form-control-sm" placeholder="Search bookings" value="<?php echo htmlspecialchars($bookingQuery, ENT_QUOTES, 'UTF-8'); ?>">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Ticket ID</th>
                                <th>Event</th>
                                <th>Class</th>
                                <th>Date & Time</th>
                                <th>Location</th>
                                <th>Tickets Booked</th>
                                <th>Price</th>
                                <th>Total Cost</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">You have not booked any events yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <td class="small text-muted"><?php echo htmlspecialchars(app_ticket_code((int) $b['event_id'], (int) $b['id']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($b['event_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($b['ticket_class'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($b['event_date_time'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($b['event_location'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo $b['tickets_booked']; ?></td>
                                        <td>Tshs <?php echo number_format((float) $b['event_price'], 2); ?></td>
                                        <td class="fw-semibold">Tshs <?php echo number_format($b['tickets_booked'] * (float) $b['event_price'], 2); ?></td>
                                        <td>
                                            <?php if ($b['status'] === 'confirmed'): ?>
                                                <span class="badge bg-success">Confirmed</span>
                                            <?php elseif ($b['status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark">Pending Payment</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Cancelled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($b['status'] === 'cancelled'): ?>
                                                <button class="btn btn-sm btn-outline-secondary" disabled>Cancelled</button>
                                            <?php elseif ($b['status'] === 'confirmed'): ?>
                                                <div class="d-flex gap-1">
                                                    <a href="<?php echo app_url('/ticket.php?id=' . $b['id']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">Print Ticket</a>
                                                    <button class="btn btn-sm btn-outline-secondary" disabled title="Confirmed bookings can only be changed by an admin.">Locked</button>
                                                </div>
                                            <?php else: ?>
                                                <div class="d-flex gap-1">
                                                    <form method="post" action="" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                        <button type="submit" name="action" value="cancel_booking" class="btn btn-sm btn-danger">Cancel</button>
                                                    </form>
                                                </div>
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
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
