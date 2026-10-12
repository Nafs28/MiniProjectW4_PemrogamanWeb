<?php
declare(strict_types=1);
require __DIR__ . '/../../helpers/auth.php';
require_role('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = v_in($_POST['action'] ?? null, ['add', 'edit', 'delete']);
    $id = v_int_positive($_POST['id'] ?? null);
    $name = v_text($_POST['name'] ?? null, 2, 100);
    $desc = v_text($_POST['description'] ?? '', 0, 255) ?? '';
    try {
        if ($action === 'add' && $name !== null) {
            $pdo->prepare('INSERT INTO categories (name, description) VALUES (:n,:d)')->execute([':n' => $name, ':d' => $desc]);
            flash('ok', 'Kategori ditambahkan.');
        } elseif ($action === 'edit' && $id !== null && $name !== null) {
            $pdo->prepare('UPDATE categories SET name=:n, description=:d WHERE id=:id')->execute([':n' => $name, ':d' => $desc, ':id' => $id]);
            flash('ok', 'Kategori diperbarui.');
        } elseif ($action === 'delete' && $id !== null) {
            $pdo->prepare('DELETE FROM categories WHERE id=:id')->execute([':id' => $id]);
            flash('ok', 'Kategori dihapus.');
        } else {
            flash('err', 'Input tidak valid (nama 2-100 karakter).');
        }
    } catch (PDOException $e) {
        flash('err', $e->getCode() === '23000'
            ? 'Gagal: nama sudah ada, atau kategori masih dipakai produk (ON DELETE RESTRICT).' : 'Terjadi kesalahan database.');
    }
    header('Location: /categories/'); exit;
}
$rows = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS n FROM categories c ORDER BY name')->fetchAll();
$title = 'Kategori';
include __DIR__ . '/../../views/header.php';
?>
<h2>Kategori</h2>
<form method="post" class="card row"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <input name="name" placeholder="Nama kategori" required><input name="description" placeholder="Deskripsi" style="flex:1"><button class="btn">Tambah</button></form>
<div class="card"><table><tr><th>Nama</th><th>Deskripsi</th><th>Produk</th><th>Aksi</th></tr>
<?php foreach ($rows as $r): ?>
<tr><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($r['id']) ?>">
  <td><input name="name" value="<?= e($r['name']) ?>"></td><td><input name="description" value="<?= e($r['description']) ?>"></td>
  <td><?= e($r['n']) ?></td>
  <td><button class="btn gray" name="action" value="edit">Simpan</button>
      <button class="btn red" name="action" value="delete" onclick="return confirm('Hapus kategori?')">Hapus</button></td></form></tr>
<?php endforeach; ?></table></div>
<?php include __DIR__ . '/../../views/footer.php'; ?>
