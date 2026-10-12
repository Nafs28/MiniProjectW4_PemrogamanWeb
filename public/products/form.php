<?php
declare(strict_types=1);
require __DIR__ . '/../../helpers/auth.php';
require_role('admin');                       // staff -> 403
$pdo = db();

$id = v_int_positive($_GET['id'] ?? $_POST['id'] ?? null);
$product = ['sku' => '', 'name' => '', 'category_id' => '', 'price' => '', 'min_stock' => '5'];
$selSuppliers = [];

if ($id !== null) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id = :id');
    $st->execute([':id' => $id]);
    $product = $st->fetch() ?: null;
    if (!$product) { flash('err', 'Produk tidak ditemukan.'); header('Location: /products/'); exit; }
    $st = $pdo->prepare('SELECT supplier_id FROM product_suppliers WHERE product_id = :id');
    $st->execute([':id' => $id]);
    $selSuppliers = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

$cats = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$sups = $pdo->query('SELECT id, name FROM suppliers ORDER BY name')->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $sku   = v_sku($_POST['sku'] ?? null);
    $name  = v_text($_POST['name'] ?? null, 2, 150);
    $catId = v_int_positive($_POST['category_id'] ?? null);
    $price = v_price($_POST['price'] ?? null);
    $min   = v_int_nonneg($_POST['min_stock'] ?? null);
    $supIds = [];
    foreach ((array) ($_POST['suppliers'] ?? []) as $s) { if (($s = v_int_positive($s)) !== null) $supIds[] = $s; }

    if ($sku === null)   $errors[] = 'SKU wajib 3-30 karakter (huruf, angka, - atau _).';
    if ($name === null)  $errors[] = 'Nama produk wajib 2-150 karakter.';
    if ($catId === null) $errors[] = 'Kategori wajib dipilih.';
    if ($price === null) $errors[] = 'Harga harus angka lebih dari 0.';
    if ($min === null)   $errors[] = 'Stok minimum harus bilangan bulat >= 0.';

    $product = ['sku' => $_POST['sku'] ?? '', 'name' => $_POST['name'] ?? '', 'category_id' => $_POST['category_id'] ?? '',
                'price' => $_POST['price'] ?? '', 'min_stock' => $_POST['min_stock'] ?? ''];
    $selSuppliers = $supIds;

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            if ($id === null) {
                $st = $pdo->prepare('INSERT INTO products (category_id, sku, name, price, min_stock) VALUES (:c,:s,:n,:p,:m)');
                $st->execute([':c' => $catId, ':s' => $sku, ':n' => $name, ':p' => $price, ':m' => $min]);
                $pid = (int) $pdo->lastInsertId();
            } else {
                $st = $pdo->prepare('UPDATE products SET category_id=:c, sku=:s, name=:n, price=:p, min_stock=:m WHERE id=:id');
                $st->execute([':c' => $catId, ':s' => $sku, ':n' => $name, ':p' => $price, ':m' => $min, ':id' => $id]);
                $pid = $id;
                $pdo->prepare('DELETE FROM product_suppliers WHERE product_id = :id')->execute([':id' => $pid]);
            }
            $ins = $pdo->prepare('INSERT IGNORE INTO product_suppliers (product_id, supplier_id) VALUES (:p,:s)');
            foreach (array_unique($supIds) as $sid) { $ins->execute([':p' => $pid, ':s' => $sid]); }
            $pdo->commit();
            flash('ok', 'Produk berhasil disimpan.');
            header('Location: /products/');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = ($e->getCode() === '23000') ? 'SKU sudah dipakai, kategori/supplier tidak valid.' : 'Terjadi kesalahan database.';
        }
    }
}

$title = $id ? 'Edit Produk' : 'Tambah Produk';
include __DIR__ . '/../../views/header.php';
?>
<div class="card" style="max-width:560px">
  <h2><?= e($title) ?></h2>
  <?php foreach ($errors as $er): ?><div class="alert err"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($id ?? '') ?>">
    <label>SKU</label><input name="sku" value="<?= e($product['sku']) ?>" required>
    <label>Nama</label><input name="name" value="<?= e($product['name']) ?>" required style="width:100%">
    <label>Kategori</label>
    <select name="category_id" required><option value="">-- pilih --</option>
      <?php foreach ($cats as $c): ?><option value="<?= e($c['id']) ?>" <?= (string) $product['category_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <label>Harga (Rp)</label><input name="price" value="<?= e($product['price']) ?>" required>
    <label>Stok Minimum</label><input name="min_stock" value="<?= e($product['min_stock']) ?>" required>
    <label>Supplier (boleh lebih dari satu)</label>
    <?php foreach ($sups as $s): ?>
      <div><label style="font-weight:400;margin:2px 0"><input type="checkbox" name="suppliers[]" value="<?= e($s['id']) ?>" <?= in_array((int) $s['id'], $selSuppliers, true) ? 'checked' : '' ?>> <?= e($s['name']) ?></label></div>
    <?php endforeach; ?>
    <p><small>Stok awal 0. Ubah stok lewat menu Barang Masuk / Keluar agar tercatat di histori.</small></p>
    <button class="btn">Simpan</button> <a class="btn gray" href="/products/">Batal</a>
  </form>
</div>
<?php include __DIR__ . '/../../views/footer.php'; ?>
