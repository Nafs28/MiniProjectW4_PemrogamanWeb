<?php
// public/stock/index.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

requireLogin('../login.php');
$user = currentUser();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf'] ?? '');

    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $type = $_POST['type'] ?? '';
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $notes = trim($_POST['notes'] ?? '');

    // Syntactic Validation (Slide 21)
    if (!$productId || !in_array($type, ['IN', 'OUT'], true)) {
        $error = 'Pilih produk dan jenis transaksi (IN / OUT) yang valid.';
    } elseif ($quantity === false || $quantity <= 0) {
        $error = 'Jumlah barang (quantity) harus lebih dari nol.';
    } else {
        // Database Transaction (Slide 25, 26, 27)
        $pdo->beginTransaction();
        try {
            // 1. Kunci baris produk dengan SELECT ... FOR UPDATE (Slide 26)
            $stmtLock = $pdo->prepare("SELECT id, name, stock FROM products WHERE id = :id FOR UPDATE");
            $stmtLock->execute(['id' => $productId]);
            $product = $stmtLock->fetch();

            if (!$product) {
                throw new Exception('Produk tidak ditemukan di database.');
            }

            // 2. Semantic Validation: Barang keluar tidak boleh melebihi stok tersedia (Slide 26 & 33)
            if ($type === 'OUT' && $quantity > (int)$product['stock']) {
                throw new Exception(
                    "Stok tidak mencukupi! Stok '{$product['name']}' saat ini hanya {$product['stock']} unit, tidak bisa dikeluarkan {$quantity} unit."
                );
            }

            // 3. Update stok produk
            $newStock = ($type === 'IN')
                ? ((int)$product['stock'] + $quantity)
                : ((int)$product['stock'] - $quantity);

            $stmtUpdate = $pdo->prepare("UPDATE products SET stock = :stock WHERE id = :id");
            $stmtUpdate->execute(['stock' => $newStock, 'id' => $productId]);

            // 4. Catat histori pada tabel stock_movements (Slide 25 & 26)
            $stmtHist = $pdo->prepare("
                INSERT INTO stock_movements (product_id, user_id, type, quantity, notes)
                VALUES (:pid, :uid, :type, :qty, :notes)
            ");
            $stmtHist->execute([
                'pid' => $productId,
                'uid' => (int)$user['id'],
                'type' => $type,
                'qty' => $quantity,
                'notes' => $notes
            ]);

            // 5. Commit transaksi jika kedua proses berhasil (Slide 27)
            $pdo->commit();
            header('Location: index.php?status=recorded');
            exit;
        } catch (Throwable $e) {
            // Rollback otomatis jika terjadi error / stok kurang (Slide 27)
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$products = $pdo->query("SELECT id, sku, name, stock FROM products ORDER BY name ASC")->fetchAll();

// Ambil histori transaksi lengkap dengan JOIN ke products dan users
$movements = $pdo->query("
    SELECT sm.*, p.sku, p.name AS product_name, u.username, up.full_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN users u ON u.id = sm.user_id
    LEFT JOIN user_profiles up ON up.user_id = u.id
    ORDER BY sm.id DESC
    LIMIT 30
")->fetchAll();

$pageTitle = 'Transaksi Barang Masuk & Keluar';
require_once __DIR__ . '/../../views/header.php';
?>

<div style="display:flex; flex-wrap:wrap; gap:20px;">
    <!-- Form Pencatatan Barang Masuk / Keluar -->
    <div class="card" style="flex: 1 1 320px; align-self: flex-start;">
        <h3 style="margin-top:0;">📦 Catat Transaksi Stok</h3>
        <p style="font-size:12px; color:#64748b;">
            Menggunakan <code>PDO Transaction</code> &amp; <code>SELECT ... FOR UPDATE</code>.
        </p>

        <?php if (($_GET['status'] ?? '') === 'recorded'): ?>
            <div class="alert alert-success">Transaksi stok & histori berhasil disimpan!</div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

            <div class="form-group">
                <label>Pilih Produk</label>
                <select name="product_id" required>
                    <option value="">-- Pilih Produk --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int)$p['id'] ?>">
                            [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Sisa Stok: <?= (int)$p['stock'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Jenis Transaksi</label>
                <select name="type" required>
                    <option value="IN">Barang Masuk (+ IN)</option>
                    <option value="OUT">Barang Keluar (- OUT)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Jumlah Unit (Quantity &gt; 0)</label>
                <input type="number" name="quantity" min="1" required placeholder="Contoh: 5">
            </div>

            <div class="form-group">
                <label>Catatan Transaksi</label>
                <input type="text" name="notes" placeholder="Contoh: Kiriman dari supplier / Diambil barista">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;">Simpan Transaksi</button>
        </form>
    </div>

    <!-- Tabel Histori Stock Movements -->
    <div class="card" style="flex: 2 1 540px;">
        <h3 style="margin-top:0;">📜 Histori Pergerakan Stok (Stock Movements)</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>SKU & Produk</th>
                        <th>Tipe</th>
                        <th>Jumlah</th>
                        <th>Petugas</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $m): ?>
                    <tr>
                        <td style="font-size:12px; color:#64748b;"><?= e($m['created_at']) ?></td>
                        <td><code><?= e($m['sku']) ?></code> <?= e($m['product_name']) ?></td>
                        <td>
                            <?php if ($m['type'] === 'IN'): ?>
                                <span class="badge badge-in">MASUK (IN)</span>
                            <?php else: ?>
                                <span class="badge badge-out">KELUAR (OUT)</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= (int)$m['quantity'] ?></strong></td>
                        <td><?= e($m['full_name'] ?: $m['username']) ?></td>
                        <td style="font-size:13px;"><?= e($m['notes']) ?></td>
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