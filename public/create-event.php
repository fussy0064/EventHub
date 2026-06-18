<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

app_require_role(['organizer']);

$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $dateTime = (string) ($_POST['date_time'] ?? '');
    $ticketsAvailable = (int) ($_POST['tickets_available'] ?? 0);
    $price = (float) ($_POST['price'] ?? 0);
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($name === '' || $location === '' || $dateTime === '' || $description === '' || $ticketsAvailable < 1) {
        $formError = 'Complete all event fields before saving.';
    } else {
        require_once __DIR__ . '/../classes/Event.php';

        $db = app_db();
        $event = new Event($db);
        $event->setOrganizerId((int) app_current_user()['id']);
        $event->setName($name);
        $event->setDescription($description);
        $event->setDateTime($dateTime);
        $event->setLocation($location);
        $event->setTicketsAvailable($ticketsAvailable);
        $event->setPrice($price);

        if ($event->save()) {
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
                        <div class="col-md-6"><label class="form-label">Event Name</label><input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars((string) ($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Location</label><input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars((string) ($_POST['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Date & Time</label><input type="datetime-local" class="form-control" name="date_time" value="<?php echo htmlspecialchars((string) ($_POST['date_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                        <div class="col-md-3"><label class="form-label">Tickets</label><input type="number" class="form-control" name="tickets_available" min="1" value="<?php echo htmlspecialchars((string) ($_POST['tickets_available'] ?? '1'), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                        <div class="col-md-3"><label class="form-label">Price</label><input type="number" step="0.01" class="form-control" name="price" min="0" value="<?php echo htmlspecialchars((string) ($_POST['price'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="5" required><?php echo htmlspecialchars((string) ($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">Save Event</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
