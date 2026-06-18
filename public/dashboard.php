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

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_booking') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    require_once __DIR__ . '/../classes/Booking.php';
    $booking = Booking::find($db, $bookingId);

    if ($booking !== null) {
        // Attendees can only cancel their own bookings
        if ($user->getRole() === 'attendee' && $booking->getUserId() !== $user->getId()) {
            app_set_flash('error', 'Unauthorized action.');
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
    app_redirect('/dashboard.php');
}

$dashboardData = $user->getDashboard();
$bookings = $dashboardData['bookings'] ?? [];

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

$pageTitle = $dashboardData['title'];
require __DIR__ . '/partials/header.php';
?>
<div class="row g-4">
    <div class="col-lg-3">
        <aside><?php require __DIR__ . '/partials/sidebar.php'; ?></aside>
    </div>
    <div class="col-lg-9">
        <!-- Welcome banner -->
        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h1 class="h4 mb-1">Welcome, <?php echo htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8'); ?></h1>
                    <div class="text-muted">Account Email: <?php echo htmlspecialchars($user->getEmail(), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <?php if ($user->getRole() === 'organizer'): ?>
                    <a class="btn btn-primary" href="/create-event.php">Create Event</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($dashboardData['type'] === 'organizer'): ?>
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
                            <div class="fs-2 fw-bold text-dark">$<?php echo number_format($dashboardData['stats']['revenue'], 2); ?></div>
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
                            <th>Tickets Remaining</th>
                            <th>Price</th>
                            <th>Tickets Sold</th>
                            <th>Revenue</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($dashboardData['events'])): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">You have not created any events yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($dashboardData['events'] as $e): ?>
                                <?php $eventObj = $e['object']; ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($eventObj->getName(), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($eventObj->getDateTime())), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($eventObj->getLocation(), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if ($eventObj->getTicketsAvailable() > 0): ?>
                                            <span class="badge bg-success"><?php echo $eventObj->getTicketsAvailable(); ?> left</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Sold Out</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(sprintf('$%.2f', $eventObj->getPrice()), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo $e['tickets_sold']; ?></td>
                                    <td class="fw-semibold">$<?php echo number_format($e['tickets_sold'] * $eventObj->getPrice(), 2); ?></td>
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
                                <th>Attendee</th>
                                <th>Email</th>
                                <th>Event</th>
                                <th>Tickets Booked</th>
                                <th>Revenue</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No bookings on file.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($b['user_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($b['user_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($b['event_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo $b['tickets_booked']; ?></td>
                                        <td>$<?php echo number_format($b['tickets_booked'] * (float) $b['event_price'], 2); ?></td>
                                        <td>
                                            <?php if ($b['status'] === 'confirmed'): ?>
                                                <span class="badge bg-success">Confirmed</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Cancelled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($b['status'] === 'confirmed'): ?>
                                                <form method="post" action="" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                    <button type="submit" name="action" value="cancel_booking" class="btn btn-sm btn-danger">Cancel Booking</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
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
                                <th>Event</th>
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
                                    <td colspan="8" class="text-center text-muted py-4">You have not booked any events yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($b['event_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($b['event_date_time'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($b['event_location'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo $b['tickets_booked']; ?></td>
                                        <td>$<?php echo number_format((float) $b['event_price'], 2); ?></td>
                                        <td class="fw-semibold">$<?php echo number_format($b['tickets_booked'] * (float) $b['event_price'], 2); ?></td>
                                        <td>
                                            <?php if ($b['status'] === 'confirmed'): ?>
                                                <span class="badge bg-success">Confirmed</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Cancelled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($b['status'] === 'confirmed'): ?>
                                                <form method="post" action="" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                    <button type="submit" name="action" value="cancel_booking" class="btn btn-sm btn-danger">Cancel</button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-secondary" disabled>Cancelled</button>
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
