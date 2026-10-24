<?php
// public/dashboard.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';

requireLogin('login.php');

// 1. Data 5 Widget Utama (Slide 29)
$totalProducts = (int)$pdo->query("SELECT COUNT(id) FROM products")->fetchColumn();
$inventoryValue = (float)$pdo->query("SELECT COALESCE(SUM(stock * price), 0) FROM products")->fetchColumn();
$lowStockCount = (int)$pdo->query("SELECT COUNT(id) FROM products WHERE stock <= minimum_stock")->fetchColumn();
$totalIn = (int)$pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE type = 'IN'")->fetchColumn();
$totalOut = (int)$pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE type = 'OUT'")->fetchColumn();

// 2. Daftar Produk dengan Stok Rendah (stock <= minimum_stock)
$lowStockProducts = $pdo->query("
    SELECT p.sku, p.name, p.stock, p.minimum_stock, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.stock <= p.minimum_stock
    ORDER BY p.stock ASC
")->fetchAll();

// 3. Laporan Agregat per Kategori (Slide 14 & 15: LEFT JOIN, GROUP BY, COUNT, SUM, AVG + opsi HAVING)
$onlyActiveCat = isset($_GET['having_active']);
$catSql = "
    SELECT c.name, 
           COUNT(p.id) AS total_products,
           COALESCE(SUM(p.stock), 0) AS total_stock,
           COALESCE(AVG(p.price), 0) AS average_price
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name
";
if ($onlyActiveCat) {
    $catSql .= " HAVING COUNT(p.id) >= 2"; // Klausa HAVING sesuai Slide 15
}
$categoryReport = $pdo->query($catSql)->fetchAll();

// 4. Laporan Mutasi Barang Masuk & Keluar per Produk (Slide 28)
$movementReport = $pdo->query("
    SELECT p.sku, p.name, p.stock AS current_stock,
           SUM(CASE WHEN sm.type = 'IN' THEN sm.quantity ELSE 0 END) AS total_in,
           SUM(CASE WHEN sm.type = 'OUT' THEN sm.quantity ELSE 0 END) AS total_out
    FROM products p
    LEFT JOIN stock_movements sm ON sm.product_id = p.id
    GROUP BY p.id, p.sku, p.name, p.stock
    ORDER BY p.sku ASC
")->fetchAll();

$pageTitle = 'Dashboard & Laporan Inventory';
require_once __DIR__ . '/../views/header.php';
?>

<!-- 5 Widget Ringkasan Keputusan Bisnis (Slide 29) -->
<div class="widgets">
    <div class="widget-card">
        <div class="widget-title">Total Produk</div>
        <div class="widget-value"><?= $totalProducts ?> Item</div>
    </div>
    <div class="widget-card">
        <div class="widget-title">Nilai Inventory</div>
        <div class="widget-value" style="color:#0f766e;">Rp <?= number_format($inventoryValue, 0, ',', '.') ?></div>
    </div>
    <div class="widget-card" style="border-color: <?= $lowStockCount > 0 ? '#f59e0b' : '#cbd5e1' ?>;">
        <div class="widget-title">Produk Stok Rendah</div>
        <div class="widget-value" style="color: <?= $lowStockCount > 0 ? '#d97706' : '#0f172a' ?>;"><?= $lowStockCount ?> Item</div>
    </div>
    <div class="widget-card">
        <div class="widget-title">Total Barang Masuk (IN)</div>
        <div class="widget-value" style="color:#16a34a;">+<?= $totalIn ?> Unit</div>
    </div>
    <div class="widget-card">
        <div class="widget-title">Total Barang Keluar (OUT)</div>
        <div class="widget-value" style="color:#dc2626;">-<?= $totalOut ?> Unit</div>
    </div>
</div>

<!-- Peringatan Produk Stok Rendah -->
<?php if (!empty($lowStockProducts)): ?>
<div class="card" style="border-left: 5px solid #f59e0b;">
    <h3 style="margin-top:0; color:#b45309;">⚠️ Peringatan Stok Rendah (Stock &le; Minimum Stock)</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th>Stok Saat Ini</th>
                    <th>Batas Minimum</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lowStockProducts as $lp): ?>
                <tr>
                    <td><code><?= e($lp['sku']) ?></code></td>
                    <td class="product-title"><?= e($lp['name']) ?></td>
                    <td><span class="badge"><?= e($lp['category_name']) ?></span></td>
                    <td><span class="badge badge-low"><?= (int)$lp['stock'] ?></span></td>
                    <td><?= (int)$lp['minimum_stock'] ?></td>
                    <td><a href="stock/index.php" class="btn btn-primary btn-sm">+ Tambah Stok (IN)</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Laporan Agregat Kategori (Slide 14 & 15) -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px;">
        <h3 style="margin:0;">📊 Laporan Agregat per Kategori (COUNT, SUM, AVG & GROUP BY)</h3>
        <div>
            <?php if ($onlyActiveCat): ?>
                <a href="dashboard.php" class="btn btn-secondary btn-sm">Tampilkan Semua Kategori</a>
            <?php else: ?>
                <a href="dashboard.php?having_active=1" class="btn btn-primary btn-sm">Filter HAVING COUNT &ge; 2</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nama Kategori</th>
                    <th>Jumlah Produk (COUNT)</th>
                    <th>Total Unit Stok (SUM)</th>
                    <th>Rata-rata Harga (AVG)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categoryReport as $cr): ?>
                <tr>
                    <td><strong><?= e($cr['name']) ?></strong></td>
                    <td><?= (int)$cr['total_products'] ?> Produk</td>
                    <td><?= (int)$cr['total_stock'] ?> Unit</td>
                    <td>Rp <?= number_format((float)$cr['average_price'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Laporan Mutasi Stok IN/OUT (Slide 28) -->
<div class="card">
    <h3 style="margin-top:0;">🔄 Rekapitulasi Barang Masuk & Keluar per Produk</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nama Produk</th>
                    <th>Total Masuk (IN)</th>
                    <th>Total Keluar (OUT)</th>
                    <th>Stok Akhir Saat Ini</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movementReport as $mr): ?>
                <tr>
                    <td><code><?= e($mr['sku']) ?></code></td>
                    <td class="product-title"><?= e($mr['name']) ?></td>
                    <td><span class="badge badge-in">+<?= (int)$mr['total_in'] ?></span></td>
                    <td><span class="badge badge-out">-<?= (int)$mr['total_out'] ?></span></td>
                    <td><strong><?= (int)$mr['current_stock'] ?> Unit</strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</body>
</html>