<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/Event.php';
require_once __DIR__ . '/../classes/TicketClass.php';

app_require_role(['organizer', 'admin']);

$db = app_db();
$currentUser = app_current_user();

$eventId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$event = Event::find($db, $eventId);

if ($event === null) {
    app_set_flash('error', 'Event not found.');
    app_redirect('/dashboard.php');
}

// Organizers may only edit their own events; admins may edit any event.
if ($currentUser['role'] === 'organizer' && $event->getOrganizerId() !== (int) $currentUser['id']) {
    app_set_flash('error', 'Unauthorized action.');
    app_redirect('/dashboard.php');
}

$formError = null;
$existingClasses = TicketClass::findByEventId($db, $eventId);
$classesByName = [];
foreach ($existingClasses as $tc) {
    $classesByName[$tc->getClassName()] = $tc;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $dateTime = (string) ($_POST['date_time'] ?? '');
    $description = trim((string) ($_POST['description'] ?? ''));

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

    if ($name === '' || $location === '' || $dateTime === '' || $description === '') {
        $formError = 'Complete all event fields before saving.';
    } elseif ($totalTickets < 1) {
        $formError = 'Add at least one ticket in one of the classes (VVIP, VIP, or Regular).';
    } else {
        $event->setName($name);
        $event->setDescription($description);
        $event->setDateTime($dateTime);
        $event->setLocation($location);

        if ($event->save()) {
            foreach ($classInput as $className => $data) {
                $existing = $classesByName[$className] ?? null;

                if ($data['tickets'] < 1) {
                    // Ticket count cleared: remove the class if it previously existed.
                    if ($existing !== null) {
                        $existing->delete();
                    }
                    continue;
                }

                $tc = $existing ?? new TicketClass($db);
                $tc->setEventId($eventId);
                $tc->setClassName($className);
                $tc->setPrice($data['price']);
                $tc->setTicketsAvailable($data['tickets']);
                $tc->save();
            }
            TicketClass::syncEventTotals($db, $eventId);

            app_set_flash('success', 'Event updated successfully.');
            app_redirect('/dashboard.php');
        } else {
            $formError = 'Failed to save event.';
        }
    }
}

// Re-read ticket classes in case the form re-renders after a validation error,
// so fields reflect what the user just typed rather than stale DB values.
$priceFor = static function (string $key) use ($classesByName) {
    foreach ($classesByName as $name => $tc) {
        if (strtolower($name) === $key) {
            return $tc->getPrice();
        }
    }
    return 0.0;
};
$ticketsFor = static function (string $key) use ($classesByName) {
    foreach ($classesByName as $name => $tc) {
        if (strtolower($name) === $key) {
            return $tc->getTicketsAvailable();
        }
    }
    return 0;
};

$pageTitle = 'Edit Event';
require __DIR__ . '/partials/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Edit Event</h1>
                <?php if ($formError !== null): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <form method="post" action="<?php echo app_url('/edit-event.php?id=' . $eventId); ?>">
                    <input type="hidden" name="id" value="<?php echo $eventId; ?>">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Event Name</label><input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars((string) ($_POST['name'] ?? $event->getName()), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Location</label><input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars((string) ($_POST['location'] ?? $event->getLocation()), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Date & Time</label><input type="datetime-local" class="form-control" name="date_time" value="<?php echo htmlspecialchars((string) ($_POST['date_time'] ?? date('Y-m-d\TH:i', strtotime($event->getDateTime()))), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4" required><?php echo htmlspecialchars((string) ($_POST['description'] ?? $event->getDescription()), ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                    </div>

                    <hr class="my-4">
                    <h2 class="h6 mb-3">Ticket Classes (Tshs) — leave a class at 0 tickets to remove it</h2>
                    <div class="row g-3">
                        <?php foreach (TicketClass::CLASSES as $className): $key = strtolower($className); ?>
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h3 class="h6"><?php echo $className; ?></h3>
                                        <label class="form-label small">Price</label>
                                        <input type="number" step="0.01" min="0" class="form-control mb-2" name="price_<?php echo $key; ?>" value="<?php echo htmlspecialchars((string) ($_POST['price_' . $key] ?? $priceFor($key)), ENT_QUOTES, 'UTF-8'); ?>">
                                        <label class="form-label small">Tickets</label>
                                        <input type="number" min="0" class="form-control" name="tickets_<?php echo $key; ?>" value="<?php echo htmlspecialchars((string) ($_POST['tickets_' . $key] ?? $ticketsFor($key)), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="<?php echo app_url('/dashboard.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
