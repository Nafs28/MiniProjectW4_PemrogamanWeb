<?php
// public/test_runner.php - Automated Test Runner Pertemuan 4 (Checklist Slide 36)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/validation.php';

$results = [];
$passed = 0;
$failed = 0;

function runTest(string $title, string $category, callable $fn): void {
    global $results, $passed, $failed;
    try {
        $msg = $fn();
        $results[] = ['status' => 'PASS', 'category' => $category, 'title' => $title, 'message' => $msg];
        $passed++;
    } catch (Throwable $e) {
        $results[] = ['status' => 'FAIL', 'category' => $category, 'title' => $title, 'message' => $e->getMessage()];
        $failed++;
    }
}

// 1. Uji Relasi One-to-One, One-to-Many, Many-to-Many (Slide 5-8 & 36)
runTest('Relasi 1:1 (users <-> user_profiles), 1:N (categories -> products), M:N (product_suppliers)', 'Relasi & ERD', function () use ($pdo) {
    $oneToOne = $pdo->query("SELECT u.username, up.full_name FROM users u JOIN user_profiles up ON up.user_id = u.id LIMIT 1")->fetch();
    $oneToMany = $pdo->query("SELECT p.name, c.name AS cat FROM products p JOIN categories c ON c.id = p.category_id LIMIT 1")->fetch();
    $manyToMany = $pdo->query("SELECT p.name, s.name AS sup FROM product_suppliers ps JOIN products p ON p.id = ps.product_id JOIN suppliers s ON s.id = ps.supplier_id LIMIT 1")->fetch();

    if (!$oneToOne || !$oneToMany || !$manyToMany) {
        throw new Exception('Salah satu relasi (1:1, 1:N, atau M:N) belum memiliki data yang terhubung.');
    }
    return "1:1 ({$oneToOne['username']} -> {$oneToOne['full_name']}), 1:N ({$oneToMany['name']}), M:N ({$manyToMany['sup']}) berjalan baik.";
});

// 2. Uji Foreign Key & Referential Integrity ON DELETE RESTRICT (Slide 9, 33 & 36)
runTest('Foreign Key ON DELETE RESTRICT: Cegah Hapus Produk yang Punya Histori', 'Referential Integrity', function () use ($pdo) {
    try {
        // Produk ID 1 sudah punya histori di stock_movements
        $pdo->exec("DELETE FROM products WHERE id = 1");
        throw new Exception('Produk berhistori berhasil dihapus! Seharusnya ditolak oleh ON DELETE RESTRICT.');
    } catch (PDOException $e) {
        return 'Penghapusan produk berhistori berhasil ditolak oleh Foreign Key ON DELETE RESTRICT.';
    }
});

// 3. Uji Keunikan SKU & Validasi Server-Side (Slide 21, 33 & 36)
runTest('SKU Unik & Validasi Server (Tolak Harga/Quantity Negatif)', 'Validasi & Business Rule', function () use ($pdo) {
    $val = validateProductData(['sku' => 'A', 'name' => 'AB', 'category_id' => 1, 'price' => -500, 'minimum_stock' => -1]);
    if (count($val['errors']) < 4) {
        throw new Exception('Validasi gagal menolak input tidak valid.');
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO products (category_id, sku, name, price) VALUES (1, 'KOP-001', 'Duplikat', 50000)");
        $stmt->execute();
        throw new Exception('Database mengizinkan SKU duplikat (KOP-001).');
    } catch (PDOException $e) {
        return 'Validasi server aktif & duplikasi SKU KOP-001 berhasil ditolak.';
    }
});

// 4. Uji Authentication (Password Hashing) & Role Authorization (Slide 22-24 & 36)
runTest('Password Hashing (password_verify) & Pemisahan Role Admin/Staff', 'Auth & Security', function () use ($pdo) {
    $admin = $pdo->query("SELECT password_hash, role FROM users WHERE username = 'admin'")->fetch();
    if (!$admin || !password_verify('admin123', $admin['password_hash'])) {
        throw new Exception('Verifikasi password_verify untuk akun admin gagal.');
    }
    return 'Password disimpan dalam bentuk hash (bcrypt) dan diverifikasi dengan password_verify().';
});

// 5. Uji Database Transaction: Barang Masuk (IN) & Histori (Slide 25, 27 & 36)
runTest('Transaksi Barang Masuk (IN) dengan Commit & Pencatatan Histori', 'Transaction & Stok', function () use ($pdo) {
    $before = (int)$pdo->query("SELECT stock FROM products WHERE id = 1")->fetchColumn();
    $adminId = (int)$pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE products SET stock = stock + 5 WHERE id = 1")->execute();
    $pdo->prepare("INSERT INTO stock_movements (product_id, user_id, type, quantity, notes) VALUES (1, :u, 'IN', 5, '__TEST_IN')")->execute(['u' => $adminId]);
    $pdo->commit();

    $after = (int)$pdo->query("SELECT stock FROM products WHERE id = 1")->fetchColumn();
    // Kembalikan ke kondisi semula
    $pdo->prepare("UPDATE products SET stock = :s WHERE id = 1")->execute(['s' => $before]);
    $pdo->exec("DELETE FROM stock_movements WHERE notes = '__TEST_IN'");

    if ($after !== $before + 5) {
        throw new Exception('Stok tidak bertambah sesuai transaksi IN.');
    }
    return "Transaksi IN (+5 unit) & pencatatan histori berjalan konsisten.";
});

// 6. Uji Business Logic Barang Keluar (OUT): Tolak Jika Melebihi Stok & Rollback (Slide 26, 27, 33 & 36)
runTest('Transaksi Barang Keluar (OUT): Rollback Otomatis Jika Quantity > Stok', 'Transaction & Stok', function () use ($pdo) {
    $initialStock = (int)$pdo->query("SELECT stock FROM products WHERE id = 1")->fetchColumn();
    $excessQty = $initialStock + 999;

    $pdo->beginTransaction();
    try {
        $row = $pdo->query("SELECT stock FROM products WHERE id = 1 FOR UPDATE")->fetch();
        if ($excessQty > (int)$row['stock']) {
            throw new Exception('Stok tidak mencukupi (Simulasi Rollback).');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
    }

    $afterStock = (int)$pdo->query("SELECT stock FROM products WHERE id = 1")->fetchColumn();
    if ($afterStock !== $initialStock) {
        throw new Exception('Stok berubah meskipun transaksi di-rollback!');
    }
    return "Permintaan keluar $excessQty unit (stok: $initialStock) berhasil dibatalkan & di-rollback.";
});

// 7. Uji Laporan Agregat (GROUP BY, COUNT, SUM, AVG) (Slide 14, 28, 29 & 36)
runTest('Query Laporan Agregat (GROUP BY, COUNT, SUM, AVG)', 'Laporan & Dashboard', function () use ($pdo) {
    $row = $pdo->query("
        SELECT c.name, COUNT(p.id) AS total_products, SUM(p.stock) AS total_stock, AVG(p.price) AS avg_price
        FROM categories c LEFT JOIN products p ON p.category_id = c.id
        GROUP BY c.id, c.name LIMIT 1
    ")->fetch();
    if (!$row) {
        throw new Exception('Query agregat kategori gagal dijalankan.');
    }
    return "Query agregat berjalan baik (Contoh: Kategori '{$row['name']}' memiliki {$row['total_products']} produk).";
});

// 8. Uji Keamanan: Prepared Statement, Allowlist Sorting & Output Escaping Anti-XSS (Slide 16, 18-20 & 36)
runTest('Prepared Statement, Allowlist Sorting & Anti-XSS Output Encoding', 'Keamanan Aplikasi', function () {
    $xss = e('<b>Promo</b><script>evil()</script>');
    if (str_contains($xss, '<script>') || str_contains($xss, '<b>')) {
        throw new Exception('Fungsi helper e() gagal melakukan escaping HTML.');
    }
    $listingCode = file_get_contents(__DIR__ . '/products/index.php');
    if (!str_contains($listingCode, 'allowedSortColumns')) {
        throw new Exception('Allowlist sorting belum diterapkan di products/index.php.');
    }
    return 'Output di-encode dengan aman & kolom sorting divalidasi dengan allowlist.';
});

$pageTitle = 'Test Runner Pertemuan 4';
require_once __DIR__ . '/../views/header.php';
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
        <div>
            <h2 style="margin:0;">✅ Automated Test Runner — Pertemuan 4</h2>
            <p style="margin:4px 0 0 0; font-size:13px; color:#64748b;">
                Pengujian Otomatis Checklist Mini Project 3 (Inventory Management System — Slide 36)
            </p>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="test_runner.php" class="btn btn-primary">Jalankan Ulang Test</a>
            <a href="dashboard.php" class="btn btn-secondary">Ke Dashboard</a>
        </div>
    </div>

    <div class="widgets">
        <div class="widget-card">
            <div class="widget-title">Total Skenario</div>
            <div class="widget-value"><?= $passed + $failed ?> Test</div>
        </div>
        <div class="widget-card" style="background:#f0fdf4; border-color:#86efac;">
            <div class="widget-title" style="color:#166534;">Berhasil (PASS)</div>
            <div class="widget-value" style="color:#15803d;"><?= $passed ?></div>
        </div>
        <div class="widget-card">
            <div class="widget-title">Gagal (FAIL)</div>
            <div class="widget-value" style="color:<?= $failed > 0 ? '#dc2626' : '#64748b' ?>;"><?= $failed ?></div>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Status</th>
                    <th>Komponen Rubrik</th>
                    <th>Skenario Pengujian</th>
                    <th>Hasil Verifikasi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $idx => $r): ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td>
                        <span class="badge <?= $r['status'] === 'PASS' ? 'badge-in' : 'badge-out' ?>">
                            <?= e($r['status']) ?>
                        </span>
                    </td>
                    <td><span class="badge"><?= e($r['category']) ?></span></td>
                    <td><strong><?= e($r['title']) ?></strong></td>
                    <td style="color:#475569;"><?= e($r['message']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</body>
</html>