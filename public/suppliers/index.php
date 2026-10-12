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
    $phone = v_text($_POST['phone'] ?? '', 0, 20) ?? '';
    $addr = v_text($_POST['address'] ?? '', 0, 255) ?? '';
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $phone)) { $name = null; }
    try {
        if ($action === 'add' && $name !== null) {
            $pdo->prepare('INSERT INTO suppliers (name, phone, address) VALUES (:n,:p,:a)')->execute([':n' => $name, ':p' => $phone, ':a' => $addr]);
            flash('ok', 'Supplier ditambahkan.');
        } elseif ($action === 'edit' && $id !== null && $name !== null) {
            $pdo->prepare('UPDATE suppliers SET name=:n, phone=:p, address=:a WHERE id=:id')->execute([':n' => $name, ':p' => $phone, ':a' => $addr, ':id' => $id]);
            flash('ok', 'Supplier diperbarui.');
        } elseif ($action === 'delete' && $id !== null) {
            $pdo->prepare('DELETE FROM suppliers WHERE id=:id')->execute([':id' => $id]);
            flash('ok', 'Supplier dihapus.');
        } else {
            flash('err', 'Input tidak valid (nama 2-100 karakter, telepon hanya angka/+/-).');
        }
    } catch (PDOException $e) {
        flash('err', $e->getCode() === '23000'
            ? 'Gagal: nama sudah ada, atau supplier masih terhubung ke produk (ON DELETE RESTRICT).' : 'Terjadi kesalahan database.');
    }
    header('Location: /suppliers/'); exit;
}
$rows = $pdo->query('SELECT * FROM suppliers ORDER BY name')->fetchAll();
$title = 'Supplier';
include __DIR__ . '/../../views/header.php';
?>
<h2>Supplier</h2>
<form method="post" class="card row"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <input name="name" placeholder="Nama supplier" required><input name="phone" placeholder="Telepon"><input name="address" placeholder="Alamat" style="flex:1"><button class="btn">Tambah</button></form>
<div class="card"><table><tr><th>Nama</th><th>Telepon</th><th>Alamat</th><th>Aksi</th></tr>
<?php foreach ($rows as $r): ?>
<tr><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($r['id']) ?>">
  <td><input name="name" value="<?= e($r['name']) ?>"></td><td><input name="phone" value="<?= e($r['phone']) ?>"></td>
  <td><input name="address" value="<?= e($r['address']) ?>"></td>
  <td><button class="btn gray" name="action" value="edit">Simpan</button>
      <button class="btn red" name="action" value="delete" onclick="return confirm('Hapus supplier?')">Hapus</button></td></form></tr>
<?php endforeach; ?></table></div>
<?php include __DIR__ . '/../../views/footer.php'; ?>
