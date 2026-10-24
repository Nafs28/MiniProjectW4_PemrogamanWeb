<?php
// public/products/edit.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

requireAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
   ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    exit('Produk tidak ditemukan.');
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

// Ambil relasi supplier saat ini
$curSupStmt = $pdo->prepare("SELECT supplier_id, supplier_price FROM product_suppliers WHERE product_id = :pid");
$curSupStmt->execute(['pid' => $id]);
$currentSuppliers = [];
foreach ($curSupStmt->fetchAll() as $row) {
    $currentSuppliers[(int)$row['supplier_id']] = (float)$row['supplier_price'];
}

$errors = [];
$old = $product;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf'] ?? '');
    $old = $_POST;

    $validation = validateProductData($_POST);
    $errors = $validation['errors'];
    $data = $validation['data'];

    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT id FROM products WHERE sku = :sku AND id != :id");
        $chk->execute(['sku' => $data['sku'], 'id' => $id]);
        if ($chk->fetch()) {
            $errors['sku'] = 'SKU sudah digunakan oleh produk lain.';
        } else {
            $pdo->beginTransaction();
            try {
                $upd = $pdo->prepare("
                    UPDATE products 
                    SET category_id=:category_id, sku=:sku, name=:name, price=:price, minimum_stock=:minimum_stock
                    WHERE id=:id
                ");
                $data['id'] = $id;
                $upd->execute($data);

                // Perbarui relasi Many-to-Many product_suppliers
                $pdo->prepare("DELETE FROM product_suppliers WHERE product_id = :pid")->execute(['pid' => $id]);
                $selectedSuppliers = $_POST['suppliers'] ?? [];
                $supplierPrices = $_POST['supplier_prices'] ?? [];
                $stmtSup = $pdo->prepare("INSERT INTO product_suppliers (product_id, supplier_id, supplier_price) VALUES (:pid, :sid, :sprice)");
                foreach ($selectedSuppliers as $sid) {
                    $sidInt = (int)$sid;
                    $sPrice = max(0, (float)($supplierPrices[$sidInt] ?? 0));
                    $stmtSup->execute(['pid' => $id, 'sid' => $sidInt, 'sprice' => $sPrice]);
                }

                $pdo->commit();
                header('Location: index.php?status=updated');
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors['db'] = $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Edit Produk - Inventory System';
require_once __DIR__ . '/../../views/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2 style="margin-top:0;">Edit Produk: <?= e($product['sku']) ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $err) echo '<div>• ' . e($err) . '</div>'; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="edit.php">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

        <div class="form-group">
            <label>Kode SKU</label>
            <input name="sku" value="<?= e($old['sku'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>Nama Produk</label>
            <input name="name" value="<?= e($old['name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>Kategori</label>
            <select name="category_id" required>
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
            <label>Batas Minimum Stok</label>
            <input name="minimum_stock" type="number" min="0" value="<?= e((string)($old['minimum_stock'] ?? '5')) ?>" required>
        </div>

        <div class="form-group">
            <label>Supplier Pemasok (Many-to-Many)</label>
            <div style="border:1px solid #cbd5e1; border-radius:8px; padding:12px;">
                <?php foreach ($suppliers as $s):
                    $sid = (int)$s['id'];
                    $checked = array_key_exists($sid, $currentSuppliers);
                    $sPriceVal = $checked ? $currentSuppliers[$sid] : '';
                ?>
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px;">
                        <label style="font-weight:normal; margin:0; display:flex; align-items:center; gap:6px;">
                            <input type="checkbox" name="suppliers[]" value="<?= $sid ?>" <?= $checked ? 'checked' : '' ?> style="width:auto;">
                            <?= e($s['name']) ?>
                        </label>
                        <input type="number" name="supplier_prices[<?= $sid ?>]" value="<?= e((string)$sPriceVal) ?>" placeholder="Harga beli (Rp)" style="width:180px; padding:6px;">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

</div>
</body>
</html>