<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/Event.php';
require_once __DIR__ . '/../classes/TicketClass.php';

app_require_role(['organizer', 'admin']);

$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $eventDate = (string) ($_POST['event_date'] ?? '');
    $startTime = (string) ($_POST['start_time'] ?? '');
    $endTime = (string) ($_POST['end_time'] ?? '');
    $description = trim((string) ($_POST['description'] ?? ''));

    // Combine Date and Start Time into a single string for storage
    $dateTime = trim($eventDate . ' ' . $startTime);

    // One price + ticket count per class: VVIP, VIP, Regular
    $classInput = [];
    $totalTickets = 0;
    foreach (TicketClass::CLASSES as $className) {
        $key = strtolower($className);
        $classPrice = (float) ($_POST['price_' . $key] ?? 0);
        $classTickets = (int) ($_POST['tickets_' . $key] ?? 0);
        $classInput[$className] = ['price' => $classPrice, 'tickets' => $classTickets];
        $totalTickets += $classTickets;
    }

    if ($name === '' || $location === '' || $eventDate === '' || $startTime === '' || $endTime === '' || $description === '') {
        $formError = 'Complete all event fields before saving.';
    } elseif ($totalTickets < 1) {
        $formError = 'Add at least one ticket in one of the classes (VVIP, VIP, or Regular).';
    } else {
        $db = app_db();
        $event = new Event($db);
        $event->setOrganizerId((int) app_current_user()['id']);
        $event->setName($name);
        $event->setDescription($description);
        $event->setDateTime($dateTime);
        $event->setLocation($location);
        $event->setTicketsAvailable($totalTickets); // will be re-synced right after
        $event->setPrice(0);

        if ($event->save()) {
            foreach ($classInput as $className => $data) {
                if ($data['tickets'] < 1) {
                    continue; // skip classes the organizer left empty
                }
                $tc = new TicketClass($db);
                $tc->setEventId((int) $event->getId());
                $tc->setClassName($className);
                $tc->setPrice($data['price']);
                $tc->setTicketsAvailable($data['tickets']);
                $tc->save();
            }
            TicketClass::syncEventTotals($db, (int) $event->getId());

            app_set_flash('success', 'Event created successfully.');
            app_redirect('/dashboard.php');
        } else {
            $formError = 'Failed to save event.';
        }
    }
}

$pageTitle = 'Create Event';
require __DIR__ . '/partials/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Create Event</h1>
                <?php if ($formError !== null): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <form method="post" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Event Name</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars((string) ($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars((string) ($_POST['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Event Date</label>
                            <input type="date" class="form-control" name="event_date" value="<?php echo htmlspecialchars((string) ($_POST['event_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control" name="start_time" value="<?php echo htmlspecialchars((string) ($_POST['start_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="end_time" value="<?php echo htmlspecialchars((string) ($_POST['end_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4" required><?php echo htmlspecialchars((string) ($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h2 class="h6 mb-3">Ticket Classes (Tshs) — leave a class at 0 tickets to skip it</h2>
                    <div class="row g-3">
                        <?php foreach (TicketClass::CLASSES as $className): $key = strtolower($className); ?>
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h3 class="h6"><?php echo $className; ?></h3>
                                        <label class="form-label small">Price</label>
                                        <input type="number" step="0.01" min="0" class="form-control mb-2" name="price_<?php echo $key; ?>" value="<?php echo htmlspecialchars((string) ($_POST['price_' . $key] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
                                        <label class="form-label small">Tickets</label>
                                        <input type="number" min="0" class="form-control" name="tickets_<?php echo $key; ?>" value="<?php echo htmlspecialchars((string) ($_POST['tickets_' . $key] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn btn-primary mt-4">Save Event</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
