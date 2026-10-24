<?php
// public/products/categories_suppliers.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

requireAdmin();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf'] ?? '');
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_category') {
            $name = trim($_POST['name'] ?? '');
            if (mb_strlen($name) < 3) throw new Exception('Nama kategori minimal 3 karakter.');
            $pdo->prepare("INSERT INTO categories (name) VALUES (:n)")->execute(['n' => $name]);
            $msg = 'Kategori baru berhasil ditambahkan.';
        } elseif ($action === 'add_supplier') {
            $name = trim($_POST['name'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            if (mb_strlen($name) < 3) throw new Exception('Nama supplier minimal 3 karakter.');
            $pdo->prepare("INSERT INTO suppliers (name, contact) VALUES (:n, :c)")->execute(['n' => $name, 'c' => $contact]);
            $msg = 'Supplier baru berhasil ditambahkan.';
        }
    } catch (Throwable $e) {
        $err = 'Gagal menyimpan: ' . $e->getMessage();
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY id DESC")->fetchAll();

$pageTitle = 'Master Kategori & Supplier';
require_once __DIR__ . '/../../views/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

<div style="display:flex; flex-wrap:wrap; gap:20px;">
    <!-- Panel Kategori -->
    <div class="card" style="flex: 1 1 350px;">
        <h3 style="margin-top:0;">Master Kategori Produk</h3>
        <form method="POST" style="margin-bottom:16px;">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="add_category">
            <div class="form-group">
                <input name="name" required placeholder="Nama kategori baru...">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">+ Tambah Kategori</button>
        </form>
        <table>
            <thead><tr><th>ID</th><th>Nama Kategori</th></tr></thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr><td><?= (int)$c['id'] ?></td><td><?= e($c['name']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Panel Supplier -->
    <div class="card" style="flex: 1 1 350px;">
        <h3 style="margin-top:0;">Master Supplier</h3>
        <form method="POST" style="margin-bottom:16px;">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="add_supplier">
            <div class="form-group">
                <input name="name" required placeholder="Nama perusahaan supplier...">
            </div>
            <div class="form-group">
                <input name="contact" required placeholder="Nomor telepon / kontak...">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">+ Tambah Supplier</button>
        </form>
        <table>
            <thead><tr><th>Nama Supplier</th><th>Kontak</th></tr></thead>
            <tbody>
                <?php foreach ($suppliers as $s): ?>
                    <tr><td><?= e($s['name']) ?></td><td><?= e($s['contact']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</body>
</html>