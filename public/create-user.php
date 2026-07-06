<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

app_require_role(['admin']);

$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $role = (string) ($_POST['role'] ?? 'attendee');

    $allowedRoles = ['attendee', 'organizer'];
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $formError = 'Complete all required fields.';
    } elseif (strlen($password) < 6) {
        $formError = 'Password must be at least 6 characters.';
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
            $user->setApproved(true); // admin-created accounts skip the approval queue

            if ($user->save()) {
                app_set_flash('success', 'User "' . $name . '" created successfully.');
                app_redirect('/dashboard.php');
            } else {
                $formError = 'Failed to create user. Please try again.';
            }
        }
    }
}

$pageTitle = 'Create User';
require __DIR__ . '/partials/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Create User</h1>
                <p class="text-muted small">Accounts created here are auto-approved — no approval step needed.</p>
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
                        <div class="col-12">
                            <label class="form-label">Password</label>
                            <div class="password-toggle-wrapper">
                                <input type="password" class="form-control" name="password" required>
                                <span class="password-toggle-icon" onclick="togglePassword(this)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="eye-icon"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/></svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="eye-slash-icon d-none"><path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/><path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/><path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12-.708.708z"/></svg>
                                </span>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success mt-3">Create User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
