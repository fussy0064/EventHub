<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

if (app_current_user() !== null) {
    app_redirect('/dashboard.php');
}

$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    $role = (string) ($_POST['role'] ?? 'attendee');

    $allowedRoles = ['attendee', 'organizer'];
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $formError = 'Complete all required fields.';
    } elseif ($password !== $passwordConfirm) {
        $formError = 'Passwords do not match.';
    } elseif (!in_array($role, $allowedRoles, true)) {
        $formError = 'Invalid role selected.';
    } else {
        require_once __DIR__ . '/../classes/User.php';
        require_once __DIR__ . '/../classes/Attendee.php';
        require_once __DIR__ . '/../classes/Organizer.php';

        $db = app_db();
        if (User::findByEmail($db, $email) !== null) {
            $formError = 'That email is already registered.';
        } else {
            $user = ($role === 'organizer') ? new Organizer($db) : new Attendee($db);
            $user->setName($name);
            $user->setEmail($email);
            $user->setPassword($password);

            if ($user->save()) {
                app_set_flash('success', 'Account created successfully. Please sign in.');
                app_redirect('/login.php');
            } else {
                $formError = 'Failed to create account. Please try again.';
            }
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/partials/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Create Account</h1>
                <?php if ($formError !== null): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <form method="post" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars((string) ($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="attendee" <?php echo (($_POST['role'] ?? 'attendee') === 'attendee') ? 'selected' : ''; ?>>Attendee</option>
                                <option value="organizer" <?php echo (($_POST['role'] ?? '') === 'organizer') ? 'selected' : ''; ?>>Organizer</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="password_confirm" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success mt-3 w-100">Create account</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
