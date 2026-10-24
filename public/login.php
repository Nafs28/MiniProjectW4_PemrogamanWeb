<?php
// public/login.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Prepared Statement + LEFT JOIN ke user_profiles (Relasi One-to-One Slide 6 & 18)
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.password_hash, u.role, up.full_name 
        FROM users u
        LEFT JOIN user_profiles up ON up.user_id = u.id
        WHERE u.username = :username
    ");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    // Verifikasi Password Hashing (Slide 23)
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'] ?? $user['username'],
            'role' => $user['role']
        ];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Inventory Management System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container" style="max-width: 420px; margin-top: 60px;">
    <div class="card">
        <h2 style="margin-top: 0;">🔐 Login Inventory</h2>
        <p style="font-size: 13px; color: #64748b;">
            Sistem Manajemen Gudang Toko Kopi Nusantara (Pertemuan 4)
        </p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input id="username" name="username" required autofocus placeholder="admin atau staff">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Masuk</button>
        </form>

        <div style="margin-top: 16px; padding: 12px; background: #f8fafc; border-radius: 8px; font-size: 12px; color: #475569;">
            <strong>Akun Demo Siap Pakai:</strong><br>
            • Role Admin: <code>admin</code> / Password: <code>admin123</code><br>
            • Role Staff: <code>staff</code> / Password: <code>staff123</code>
        </div>
    </div>
</div>
</body>
</html>