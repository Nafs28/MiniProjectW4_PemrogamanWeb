<?php
// Jalankan setelah import schema.sql:  php database/seed.php
declare(strict_types=1);
require __DIR__ . '/../config/database.php';
$pdo = db();
$pdo->beginTransaction();

$users = [['admin', 'admin123', 'admin', 'Administrator'], ['staff', 'staff123', 'staff', 'Staff Gudang']];
foreach ($users as [$u, $p, $r, $f]) {
    $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?,?,?)')->execute([$u, password_hash($p, PASSWORD_DEFAULT), $r]);
    $pdo->prepare('INSERT INTO user_profiles (user_id, full_name) VALUES (?,?)')->execute([$pdo->lastInsertId(), $f]);
}
foreach (['Elektronik', 'Alat Tulis', 'Makanan & Minuman'] as $c) $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$c]);
foreach ([['PT Maju Jaya', '08123456789'], ['CV Sentosa', '08987654321']] as [$n, $ph]) $pdo->prepare('INSERT INTO suppliers (name, phone) VALUES (?,?)')->execute([$n, $ph]);

$prods = [[1, 'ELK-001', 'Mouse Wireless', '85000.00', 20, 5], [1, 'ELK-002', 'Keyboard USB', '120000.00', 3, 5],
          [2, 'ATK-001', 'Pulpen Hitam (box)', '25000.00', 50, 10], [3, 'MKN-001', 'Kopi Sachet (karton)', '60000.00', 8, 10]];
foreach ($prods as $i => [$c, $sku, $n, $pr, $st, $min]) {
    $pdo->prepare('INSERT INTO products (category_id, sku, name, price, stock, min_stock) VALUES (?,?,?,?,?,?)')->execute([$c, $sku, $n, $pr, $st, $min]);
    $pid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO product_suppliers VALUES (?,?)')->execute([$pid, ($i % 2) + 1]);
    $pdo->prepare("INSERT INTO stock_movements (product_id, user_id, type, quantity, note) VALUES (?,1,'IN',?,'Stok awal')")->execute([$pid, $st]);
}
$pdo->commit();
echo "Seed selesai. Login: admin/admin123 atau staff/staff123\n";
