<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

// Load environment configuration file if it exists (not used in production on AWS)
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    if ($env !== false) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Determine application environment
$appEnv = getenv('APP_ENV') ?: 'development';

// Configure error reporting based on environment
if ($appEnv === 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', '/var/log/app-error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Configure session for production
if (session_status() === PHP_SESSION_NONE) {
    if ($appEnv === 'production') {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_samesite', 'Lax');
    }
    session_start();
}

function app_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $database = new Database();
    $pdo = $database->connection();

    return $pdo;
}

function app_set_flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function app_get_flash(string $type): ?string
{
    if (!isset($_SESSION['flash'][$type])) {
        return null;
    }

    $message = (string) $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);

    return $message;
}

function app_url(string $path): string
{
    static $basePath = null;
    if ($basePath === null) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = dirname($scriptName);
        $basePath = ($dir === DIRECTORY_SEPARATOR) ? '' : $dir;
    }
    $path = '/' . ltrim($path, '/');
    return $basePath . $path;
}

function app_redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function app_current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function app_login_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];
}

function app_logout_user(): void
{
    unset($_SESSION['user']);
}

function app_require_login(): void
{
    if (app_current_user() === null) {
        app_set_flash('error', 'Please sign in to continue.');
        app_redirect('/login.php');
    }
}

function app_require_role(array $roles): void
{
    app_require_login();

    $currentUser = app_current_user();
    if ($currentUser === null || !in_array($currentUser['role'], $roles, true)) {
        app_set_flash('error', 'You do not have permission to access that page.');
        app_redirect('/dashboard.php');
    }
}

function app_get_encryption_key(): string
{
    $keyHex = getenv('ENCRYPTION_KEY') ?: '';
    if (strlen($keyHex) === 64) {
        return hex2bin($keyHex);
    }
    return hash('sha256', 'default-secret-key-eventhub-1234567890', true);
}

function app_encrypt(string $plaintext): string
{
    if ($plaintext === '') {
        return '';
    }
    $key = app_get_encryption_key();
    $iv = openssl_random_pseudo_bytes(16);
    $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $iv . $ciphertext;
}

function app_decrypt(string $ciphertext): string
{
    if ($ciphertext === '') {
        return '';
    }
    if (strlen($ciphertext) < 17) {
        return $ciphertext;
    }
    $key = app_get_encryption_key();
    $iv = substr($ciphertext, 0, 16);
    $data = substr($ciphertext, 16);
    $decrypted = openssl_decrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted !== false ? $decrypted : '';
}

function app_ticket_code(int $eventId, int $bookingId): string
{
    return 'EHI-' . $eventId . '-' . $bookingId;
}

