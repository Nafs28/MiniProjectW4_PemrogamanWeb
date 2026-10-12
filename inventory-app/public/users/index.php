<?php
declare(strict_types=1);
require __DIR__ . '/../../helpers/auth.php';
require_role('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = v_in($_POST['action'] ?? null, ['add', 'delete']);
    if ($action === 'add') {
        $username = v_text($_POST['username'] ?? null, 3, 50);
        $pass = (string) ($_POST['password'] ?? '');
        $role = v_in($_POST['role'] ?? null, ['admin', 'staff']);
        $full = v_text($_POST['full_name'] ?? null, 2, 100);
        $phone = v_text($_POST['phone'] ?? '', 0, 20) ?? '';
        if ($username === null || !preg_match('/^[A-Za-z0-9_.-]+$/', $username) || strlen($pass) < 6 || $role === null || $full === null
            || ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $phone))) {
            flash('err', 'Input tidak valid (username alfanumerik, password min. 6 karakter, nama wajib).');
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (:u,:p,:r)')
                    ->execute([':u' => $username, ':p' => password_hash($pass, PASSWORD_DEFAULT), ':r' => $role]);
                $pdo->prepare('INSERT INTO user_profiles (user_id, full_name, phone) VALUES (:id,:f,:ph)')
                    ->execute([':id' => $pdo->lastInsertId(), ':f' => $full, ':ph' => $phone]);
                $pdo->commit();
                flash('ok', 'User ditambahkan.');
            } catch (PDOException $e) {
                $pdo->rollBack();
                flash('err', $e->getCode() === '23000' ? 'Username sudah dipakai.' : 'Terjadi kesalahan database.');
            }
        }
    } elseif ($action === 'delete') {
        $id = v_int_positive($_POST['id'] ?? null);
        if ($id === null || $id === current_user()['id']) {
            flash('err', 'User tidak dapat dihapus.');
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM user_profiles WHERE user_id=:id')->execute([':id' => $id]);
                $pdo->prepare('DELETE FROM users WHERE id=:id')->execute([':id' => $id]);
                $pdo->commit();
                flash('ok', 'User dihapus.');
            } catch (PDOException $e) {
                $pdo->rollBack();
                flash('err', 'User tidak dapat dihapus karena memiliki riwayat transaksi (ON DELETE RESTRICT).');
            }
        }
    }
    header('Location: /users/'); exit;
}
$rows = $pdo->query('SELECT u.id, u.username, u.role, p.full_name, p.phone FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id ORDER BY u.id')->fetchAll();
$title = 'User';
include __DIR__ . '/../../views/header.php';
?>
<h2>Manajemen User</h2>
<form method="post" class="card row"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <input name="username" placeholder="Username" required><input type="password" name="password" placeholder="Password" required>
  <input name="full_name" placeholder="Nama lengkap" required><input name="phone" placeholder="Telepon">
  <select name="role"><option value="staff">staff</option><option value="admin">admin</option></select><button class="btn">Tambah</button></form>
<div class="card"><table><tr><th>Username</th><th>Nama</th><th>Telepon</th><th>Role</th><th>Aksi</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= e($r['username']) ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['phone']) ?></td><td><?= e($r['role']) ?></td>
<td><?php if ((int) $r['id'] !== current_user()['id']): ?>
  <form method="post" style="display:inline" onsubmit="return confirm('Hapus user?')"><?= csrf_field() ?>
  <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="btn red">Hapus</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></table></div>
<?php include __DIR__ . '/../../views/footer.php'; ?>
