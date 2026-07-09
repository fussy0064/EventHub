<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

app_require_login();

require_once __DIR__ . '/../classes/Booking.php';
require_once __DIR__ . '/../classes/Event.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/TicketClass.php';

$db = app_db();
$currentUser = app_current_user();

$bookingId = (int) ($_GET['id'] ?? 0);
$booking = Booking::find($db, $bookingId);

if ($booking === null || $booking->getStatus() !== 'confirmed') {
    app_set_flash('error', 'Ticket is not available yet — waiting for organizer to confirm payment.');
    app_redirect('/dashboard.php');
}

$event = Event::find($db, $booking->getEventId());
$ticketOwner = User::findById($db, $booking->getUserId());
$ticketClass = $booking->getTicketClassId() !== null ? TicketClass::find($db, $booking->getTicketClassId()) : null;

// Only the ticket owner, the event's organizer, or an admin may view/print it
$isOwner = $currentUser['id'] === $booking->getUserId();
$isOrganizerOfEvent = $currentUser['role'] === 'organizer' && $event !== null && $event->getOrganizerId() === (int) $currentUser['id'];
$isAdmin = $currentUser['role'] === 'admin';

if (!$isOwner && !$isOrganizerOfEvent && !$isAdmin) {
    app_set_flash('error', 'You do not have permission to view that ticket.');
    app_redirect('/dashboard.php');
}

if ($event === null || $ticketOwner === null || $ticketClass === null) {
    app_set_flash('error', 'Ticket data is incomplete.');
    app_redirect('/dashboard.php');
}

$pageTitle = 'Ticket #' . $booking->getId();
require __DIR__ . '/partials/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-end mb-3 no-print">
            <button class="btn btn-primary" onclick="window.print()">Print Ticket</button>
        </div>
        <div class="card shadow-sm ticket-card">
            <div class="card-body">
                <div class="text-center mb-4">
                    <h1 class="h4 mb-0">EventHub Ticket</h1>
                    <span class="text-muted small"><?php echo htmlspecialchars(app_ticket_code($event->getId(), $booking->getId()), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <table class="table table-borderless mb-0">
                    <tr>
                        <th style="width: 40%;">Event</th>
                        <td class="fw-semibold"><?php echo htmlspecialchars($event->getName(), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Date & Time</th>
                        <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($event->getDateTime())), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Location</th>
                        <td><?php echo htmlspecialchars($event->getLocation(), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Attendee</th>
                        <td><?php echo htmlspecialchars($ticketOwner->getName(), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <tr>
                        <th>Class</th>
                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($ticketClass->getClassName(), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    </tr>
                    <tr>
                        <th>Tickets</th>
                        <td><?php echo $booking->getTicketsBooked(); ?></td>
                    </tr>
                    <tr>
                        <th>Total Paid</th>
                        <td class="fw-semibold">Tshs <?php echo number_format($ticketClass->getPrice() * $booking->getTicketsBooked(), 2); ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><span class="badge bg-success">Confirmed</span></td>
                    </tr>
                </table>
                <p class="text-center text-muted small mt-4 mb-0">Present this ticket at the entrance.</p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
