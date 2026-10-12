<?php
declare(strict_types=1);
require __DIR__ . '/../helpers/auth.php';

if (current_user()) { header('Location: /dashboard.php'); exit; }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = v_text($_POST['username'] ?? null, 3, 50);
    $password = (string) ($_POST['password'] ?? '');

    if ($username === null || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $st = db()->prepare('SELECT id, username, password_hash, role FROM users WHERE username = :u');
        $st->execute([':u' => $username]);
        $user = $st->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $user['id'], 'username' => $user['username'], 'role' => $user['role']];
            header('Location: /dashboard.php');
            exit;
        }
        $error = 'Username atau password salah.';
    }
}
$title = 'Login';
include __DIR__ . '/../views/header.php';
?>
<div class="card" style="max-width:380px;margin:60px auto">
  <h2>Login</h2>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Username</label><input name="username" required style="width:100%">
    <label>Password</label><input type="password" name="password" required style="width:100%">
    <p><button class="btn">Masuk</button></p>
  </form>
</div>
<?php include __DIR__ . '/../views/footer.php'; ?>
