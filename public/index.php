<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/Event.php';
require_once __DIR__ . '/../classes/Booking.php';
require_once __DIR__ . '/../classes/TicketClass.php';

$db = app_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book') {
    app_require_role(['attendee', 'organizer']);
    require_once __DIR__ . '/../classes/User.php';

    $eventId = (int) ($_POST['event_id'] ?? 0);
    $ticketClassId = (int) ($_POST['ticket_class_id'] ?? 0);
    $tickets = (int) ($_POST['tickets'] ?? 0);
    $currentUser = app_current_user();

    // Organizers book on behalf of an attendee (by email); attendees book for themselves
    if ($currentUser['role'] === 'organizer') {
        $attendeeEmail = strtolower(trim((string) ($_POST['attendee_email'] ?? '')));
        $attendee = $attendeeEmail !== '' ? User::findByEmail($db, $attendeeEmail) : null;

        if ($attendee === null || $attendee->getRole() !== 'attendee') {
            app_set_flash('error', 'No attendee account found with that email.');
            $targetUserId = null;
        } else {
            $targetUserId = $attendee->getId();
        }
    } else {
        $targetUserId = (int) $currentUser['id'];
    }

    $ticketClass = TicketClass::find($db, $ticketClassId);

    if ($tickets < 1) {
        app_set_flash('error', 'Please select a valid number of tickets.');
    } elseif ($ticketClass === null || $ticketClass->getEventId() !== $eventId) {
        app_set_flash('error', 'Please choose a valid ticket class.');
    } elseif ($targetUserId !== null) {
        $event = Event::find($db, $eventId);
        if (!$event) {
            app_set_flash('error', 'Event not found.');
        } else {
            $booking = new Booking($db);
            $booking->setUserId($targetUserId);
            $booking->setEventId($eventId);
            $booking->setTicketClassId($ticketClassId);
            $booking->setTicketsBooked($tickets);
            $booking->setStatus('pending');

            if ($booking->save()) {
                app_set_flash('success', 'Tickets booked! Waiting for the organizer to confirm payment.');
                app_redirect('/dashboard.php');
            }
        }
    }
}

$query = trim((string) ($_GET['q'] ?? ''));
$events = Event::search($db, $query);

// Once an event is done, attendees (and organizers booking on their behalf)
// should no longer see it here and cannot book it. Admins still see
// everything for oversight purposes.
$browsingUser = app_current_user();
if ($browsingUser === null || $browsingUser['role'] !== 'admin') {
    $events = array_filter($events, function (Event $event) {
        return $event->getStatus() !== 'done';
    });
}

$pageTitle = 'Browse Events';
require __DIR__ . '/partials/header.php';
?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h1 class="h4 mb-3">Event Listings</h1>
        <form class="row g-2" method="get">
            <div class="col-md-8">
                <input type="search" name="q" class="form-control" placeholder="Search events, venues, or dates" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-4 d-grid">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($events)): ?>
    <div class="card shadow-sm"><div class="card-body text-center text-muted py-4">No events found matching your search.</div></div>
<?php else: ?>
    <?php foreach ($events as $event): ?>
        <?php $classes = TicketClass::findByEventId($db, (int) $event->getId()); ?>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h5 mb-1"><?php echo htmlspecialchars($event->getName(), ENT_QUOTES, 'UTF-8'); ?></h2>
                <div class="text-muted small mb-2">
                    <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($event->getDateTime())), ENT_QUOTES, 'UTF-8'); ?>
                    &middot; <?php echo htmlspecialchars($event->getLocation(), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <p class="mb-0 small"><?php echo htmlspecialchars($event->getDescription(), ENT_QUOTES, 'UTF-8'); ?></p>

                <div class="table-responsive mt-3">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Class</th>
                            <th>Price</th>
                            <th>Left</th>
                            <?php if ($currentUser !== null && in_array($currentUser['role'], ['attendee', 'organizer'], true)): ?>
                                <?php if ($currentUser['role'] === 'organizer'): ?><th>Attendee Email</th><?php endif; ?>
                                <th>Tickets</th>
                                <th></th>
                            <?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($classes)): ?>
                            <tr><td colspan="6" class="text-center text-muted">No ticket classes set up for this event.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($class->getClassName(), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>Tshs <?php echo number_format($class->getPrice(), 2); ?></td>
                                <td><?php echo $class->getTicketsAvailable(); ?></td>
                                <?php if ($currentUser === null): ?>
                                <?php elseif (in_array($currentUser['role'], ['attendee', 'organizer'], true)): ?>
                                    <?php if ($class->getTicketsAvailable() > 0): ?>
                                        <?php if ($currentUser['role'] === 'organizer'): ?>
                                            <td><input form="book-<?php echo $class->getId(); ?>" type="email" name="attendee_email" class="form-control form-control-sm" style="width: 150px;" placeholder="Attendee email" required></td>
                                        <?php endif; ?>
                                        <td><input form="book-<?php echo $class->getId(); ?>" type="number" name="tickets" class="form-control form-control-sm" style="width: 70px;" min="1" max="<?php echo $class->getTicketsAvailable(); ?>" value="1" required></td>
                                        <td>
                                            <form id="book-<?php echo $class->getId(); ?>" method="post" action="<?php echo app_url('/index.php'); ?>">
                                                <input type="hidden" name="event_id" value="<?php echo $event->getId(); ?>">
                                                <input type="hidden" name="ticket_class_id" value="<?php echo $class->getId(); ?>">
                                                <button type="submit" name="action" value="book" class="btn btn-sm btn-primary"><?php echo ($currentUser['role'] === 'organizer') ? 'Book for Attendee' : 'Book'; ?></button>
                                            </form>
                                        </td>
                                    <?php else: ?>
                                        <?php if ($currentUser['role'] === 'organizer'): ?><td></td><?php endif; ?>
                                        <td colspan="2"><span class="badge bg-danger">Sold Out</span></td>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($currentUser === null): ?>
                    <a class="btn btn-sm btn-outline-primary mt-2" href="<?php echo app_url('/login.php'); ?>">Login to Book</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
