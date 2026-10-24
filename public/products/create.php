<?php
// public/products/create.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

requireAdmin(); // Hanya Admin yang boleh menambah master produk (Slide 24)

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

$errors = [];
$old = ['sku' => '', 'name' => '', 'category_id' => '', 'price' => '', 'minimum_stock' => '5'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf'] ?? '');
    $old = $_POST;

    $validation = validateProductData($_POST);
    $errors = $validation['errors'];
    $data = $validation['data'];

    if (empty($errors)) {
        // Cek keunikan SKU (Business Rule Slide 33)
        $chk = $pdo->prepare("SELECT id FROM products WHERE sku = :sku");
        $chk->execute(['sku' => $data['sku']]);
        if ($chk->fetch()) {
            $errors['sku'] = 'Kode SKU sudah digunakan oleh produk lain.';
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO products (category_id, sku, name, price, stock, minimum_stock)
                    VALUES (:category_id, :sku, :name, :price, 0, :minimum_stock)
                ");
                $stmt->execute($data);
                $newProductId = (int)$pdo->lastInsertId();

                // Simpan Relasi Many-to-Many ke tabel product_suppliers (Slide 8)
                $selectedSuppliers = $_POST['suppliers'] ?? [];
                $supplierPrices = $_POST['supplier_prices'] ?? [];
                $stmtSup = $pdo->prepare("
                    INSERT INTO product_suppliers (product_id, supplier_id, supplier_price)
                    VALUES (:pid, :sid, :sprice)
                ");
                foreach ($selectedSuppliers as $sid) {
                    $sidInt = (int)$sid;
                    $sPrice = max(0, (float)($supplierPrices[$sidInt] ?? 0));
                    $stmtSup->execute(['pid' => $newProductId, 'sid' => $sidInt, 'sprice' => $sPrice]);
                }

                $pdo->commit();
                header('Location: index.php?status=created');
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors['db'] = 'Gagal menyimpan produk: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Tambah Produk - Inventory System';
require_once __DIR__ . '/../../views/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2 style="margin-top:0;">Tambah Master Produk Baru</h2>
    <p style="font-size:13px; color:#64748b;">
        Stok awal produk baru dimulai dari <strong>0</strong>. Penambahan stok dilakukan melalui menu <strong>Transaksi Stok (IN)</strong> agar tercatat di histori gudang.
    </p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $err): ?>
                <div>• <?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="create.php">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

        <div class="form-group">
            <label>Kode SKU (Unik)</label>
            <input name="sku" value="<?= e($old['sku'] ?? '') ?>" required placeholder="Contoh: KOP-003">
        </div>

        <div class="form-group">
            <label>Nama Produk</label>
            <input name="name" value="<?= e($old['name'] ?? '') ?>" required placeholder="Contoh: Kopi Liberika Meranti 250g">
        </div>

        <div class="form-group">
            <label>Kategori (One-to-Many)</label>
            <select name="category_id" required>
                <option value="">-- Pilih Kategori --</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ((int)($old['category_id'] ?? 0)) === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Harga Jual (Rp)</label>
            <input name="price" type="number" min="1" step="any" value="<?= e((string)($old['price'] ?? '')) ?>" required>
        </div>

        <div class="form-group">
            <label>Batas Minimum Stok (Untuk Peringatan Stok Rendah)</label>
            <input name="minimum_stock" type="number" min="0" value="<?= e((string)($old['minimum_stock'] ?? '5')) ?>" required>
        </div>

        <div class="form-group">
            <label>Pilih Supplier Pemasok (Relasi Many-to-Many)</label>
            <div style="border:1px solid #cbd5e1; border-radius:8px; padding:12px;">
                <?php foreach ($suppliers as $s): ?>
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px;">
                        <label style="font-weight:normal; margin:0; display:flex; align-items:center; gap:6px;">
                            <input type="checkbox" name="suppliers[]" value="<?= (int)$s['id'] ?>" style="width:auto;">
                            <?= e($s['name']) ?>
                        </label>
                        <input type="number" name="supplier_prices[<?= (int)$s['id'] ?>]" placeholder="Harga beli dari supplier (Rp)" style="width:210px; padding:6px;">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary">Simpan Produk</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

</div>
</body>
</html>