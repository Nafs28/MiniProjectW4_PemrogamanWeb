<?php
declare(strict_types=1);
require __DIR__ . '/../helpers/auth.php';
require_login();
$pdo = db();

// Agregasi SQL: COUNT, SUM
$totalProducts = (int) $pdo->query('SELECT COUNT(id) FROM products')->fetchColumn();
$totalValue    = (float) $pdo->query('SELECT COALESCE(SUM(stock * price),0) FROM products')->fetchColumn();

// Peringatan stok menipis: stock <= min_stock
$low = $pdo->query('SELECT sku, name, stock, min_stock FROM products WHERE stock <= min_stock ORDER BY stock ASC LIMIT 20')->fetchAll();

// Laporan mutasi: SUM(CASE WHEN type='IN' ...) + GROUP BY
$report = $pdo->query(
    "SELECT p.sku, p.name,
            COALESCE(SUM(CASE WHEN m.type = 'IN'  THEN m.quantity ELSE 0 END),0) AS total_in,
            COALESCE(SUM(CASE WHEN m.type = 'OUT' THEN m.quantity ELSE 0 END),0) AS total_out
     FROM products p
     LEFT JOIN stock_movements m ON m.product_id = p.id
     GROUP BY p.id, p.sku, p.name
     ORDER BY p.name"
)->fetchAll();

$title = 'Dashboard';
include __DIR__ . '/../views/header.php';
?>
<h2>Dashboard</h2>
<div class="grid">
  <div class="card"><div>Total Produk</div><div class="stat"><?= e($totalProducts) ?></div></div>
  <div class="card"><div>Total Nilai Inventaris</div><div class="stat">Rp <?= e(number_format($totalValue, 0, ',', '.')) ?></div></div>
  <div class="card"><div>Stok Menipis</div><div class="stat <?= $low ? 'low' : '' ?>"><?= e(count($low)) ?></div></div>
</div>

<div class="card">
  <h3>⚠️ Peringatan Stok Menipis</h3>
  <?php if (!$low): ?><p>Semua stok aman.</p><?php else: ?>
  <table><tr><th>SKU</th><th>Produk</th><th>Stok</th><th>Minimum</th></tr>
  <?php foreach ($low as $r): ?>
    <tr><td><?= e($r['sku']) ?></td><td><?= e($r['name']) ?></td><td class="low"><?= e($r['stock']) ?></td><td><?= e($r['min_stock']) ?></td></tr>
  <?php endforeach; ?></table><?php endif; ?>
</div>

<div class="card">
  <h3>Laporan Mutasi Stok per Produk</h3>
  <table><tr><th>SKU</th><th>Produk</th><th>Total Masuk</th><th>Total Keluar</th></tr>
  <?php foreach ($report as $r): ?>
    <tr><td><?= e($r['sku']) ?></td><td><?= e($r['name']) ?></td><td><?= e($r['total_in']) ?></td><td><?= e($r['total_out']) ?></td></tr>
  <?php endforeach; ?></table>
</div>
<?php include __DIR__ . '/../views/footer.php'; ?>
