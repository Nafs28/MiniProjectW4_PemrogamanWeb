<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/escape.php';
require_once __DIR__ . '/validation.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: /login.php');
        exit;
    }
}

/** Otorisasi berbasis peran, dicek di sisi server. */
function require_role(string ...$roles): void
{
    require_login();
    if (!in_array(current_user()['role'], $roles, true)) {
        forbidden();
    }
}

function forbidden(): void
{
    http_response_code(403);
    $title = '403 Forbidden';
    include __DIR__ . '/../views/header.php';
    echo '<div class="card"><h2>403 Forbidden</h2><p>Anda tidak memiliki hak akses ke halaman ini.</p>'
       . '<a href="/dashboard.php">Kembali ke dashboard</a></div>';
    include __DIR__ . '/../views/footer.php';
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Token CSRF tidak valid.');
    }
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function take_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}
