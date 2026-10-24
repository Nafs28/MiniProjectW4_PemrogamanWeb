<?php
// public/users.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/validation.php';

requireAdmin(); // Staff akan otomatis ditolak dengan 403 Forbidden (Slide 24 & 33)

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'staff';
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '-');

    if (mb_strlen($username) < 3 || strlen($password) < 6 || mb_strlen($fullName) < 3) {
        $err = 'Username min 3 karakter, password min 6 karakter, dan nama lengkap wajib diisi.';
    } elseif (!in_array($role, ['admin', 'staff'], true)) {
        $err = 'Role tidak valid.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmtU = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (:u, :p, :r)");
            $stmtU->execute([
                'u' => $username,
                'p' => password_hash($password, PASSWORD_DEFAULT),
                'r' => $role
            ]);
            $newUid = (int)$pdo->lastInsertId();

            // Simpan ke tabel user_profiles (Relasi One-to-One Slide 6)
            $stmtP = $pdo->prepare("INSERT INTO user_profiles (user_id, full_name, phone) VALUES (:uid, :fn, :ph)");
            $stmtP->execute(['uid' => $newUid, 'fn' => $fullName, 'ph' => $phone]);

            $pdo->commit();
            $msg = "User '$username' beserta profil (One-to-One) berhasil dibuat.";
        } catch (Throwable $e) {
            $pdo->rollBack();
            $err = 'Gagal menambah user: ' . $e->getMessage();
        }
    }
}

$users = $pdo->query("
    SELECT u.id, u.username, u.role, u.created_at, up.full_name, up.phone
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.id
    ORDER BY u.id ASC
")->fetchAll();

$pageTitle = 'Administrasi User (Khusus Admin)';
require_once __DIR__ . '/../views/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

<div style="display:flex; flex-wrap:wrap; gap:20px;">
    <div class="card" style="flex: 1 1 320px;">
        <h3 style="margin-top:0;">👤 Tambah Pengguna Baru</h3>
        <form method="POST" action="users.php">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <div class="form-group">
                <label>Username</label>
                <input name="username" required minlength="3">
            </div>
            <div class="form-group">
                <label>Password (Hashing)</label>
                <input name="password" type="password" required minlength="6">
            </div>
            <div class="form-group">
                <label>Role Akses</label>
                <select name="role">
                    <option value="staff">Staff Gudang</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label>Nama Lengkap (Tabel user_profiles 1:1)</label>
                <input name="full_name" required>
            </div>
            <div class="form-group">
                <label>No. Telepon</label>
                <input name="phone" value="0812-">
            </div>
            <button type="submit" class="btn btn-primary">Simpan User</button>
        </form>
    </div>

    <div class="card" style="flex: 2 1 500px;">
        <h3 style="margin-top:0;">Daftar Pengguna & Profil (Relasi One-to-One)</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Nama Lengkap (Profil 1:1)</th>
                        <th>Telepon</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td><strong><?= e($u['username']) ?></strong></td>
                        <td><?= e($u['full_name'] ?? '-') ?></td>
                        <td><?= e($u['phone'] ?? '-') ?></td>
                        <td><span class="badge badge-role"><?= e(strtoupper($u['role'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
</body>
</html>