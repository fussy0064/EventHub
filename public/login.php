<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

if (app_current_user() !== null) {
    app_redirect('/dashboard.php');
}

$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $formError = 'Enter a valid email and password.';
    } else {
        require_once __DIR__ . '/../classes/User.php';
        $user = User::findByEmail(app_db(), $email);

        if ($user !== null && password_verify($password, $user->getPasswordHash())) {
            app_login_user([
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'role' => $user->getRole()
            ]);
            app_set_flash('success', 'Signed in successfully.');
            app_redirect('/dashboard.php');
        }

        $formError = 'Invalid email or password.';
    }
}

$pageTitle = 'Login';
require __DIR__ . '/partials/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Login</h1>
                <?php if ($formError !== null): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <form method="post" action="">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Sign in</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
