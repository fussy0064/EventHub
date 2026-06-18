<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/Event.php';
require_once __DIR__ . '/../classes/Booking.php';

$db = app_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book') {
    app_require_role(['attendee']);

    $eventId = (int) ($_POST['event_id'] ?? 0);
    $tickets = (int) ($_POST['tickets'] ?? 0);

    if ($tickets < 1) {
        app_set_flash('error', 'Please select a valid number of tickets.');
    } else {
        $event = Event::find($db, $eventId);
        if (!$event) {
            app_set_flash('error', 'Event not found.');
        } else {
            $booking = new Booking($db);
            $booking->setUserId((int) app_current_user()['id']);
            $booking->setEventId($eventId);
            $booking->setTicketsBooked($tickets);
            $booking->setStatus('confirmed');

            if ($booking->save()) {
                app_set_flash('success', 'Tickets booked successfully!');
                app_redirect('/dashboard.php');
            }
        }
    }
}

$query = trim((string) ($_GET['q'] ?? ''));
$events = Event::search($db, $query);

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

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead>
            <tr>
                <th>Event</th>
                <th>Date & Time</th>
                <th>Location</th>
                <th>Price</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($events)): ?>
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">No events found matching your search.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td class="fw-semibold"><?php echo htmlspecialchars($event->getName(), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($event->getDateTime())), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($event->getLocation(), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(sprintf('$%.2f', $event->getPrice()), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end">
                            <?php if ($currentUser === null): ?>
                                <a class="btn btn-sm btn-outline-primary" href="/login.php">Login to Book</a>
                            <?php elseif ($currentUser['role'] === 'attendee'): ?>
                                <?php if ($event->getTicketsAvailable() > 0): ?>
                                    <form method="post" action="/index.php" class="d-inline-flex gap-1 align-items-center">
                                        <input type="hidden" name="event_id" value="<?php echo $event->getId(); ?>">
                                        <input type="number" name="tickets" class="form-control form-control-sm" style="width: 70px;" min="1" max="<?php echo $event->getTicketsAvailable(); ?>" value="1" required>
                                        <button type="submit" name="action" value="book" class="btn btn-sm btn-primary">Book</button>
                                        <span class="text-muted small ms-1">(<?php echo $event->getTicketsAvailable(); ?> left)</span>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-danger">Sold Out</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary">Organizer View</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
