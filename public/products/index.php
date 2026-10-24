<?php
// public/products/index.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';

requireLogin('../login.php');
$user = currentUser();

// 1. Ambil input Search & Filter (Slide 16)
$q = trim($_GET['q'] ?? '');
$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: 0;

// 2. Allowlist Sorting & Direction agar aman dari SQL Injection (Slide 16 & 33)
$allowedSortColumns = [
    'name' => 'p.name',
    'price' => 'p.price',
    'stock' => 'p.stock',
    'sku' => 'p.sku'
];
$sortKey = $_GET['sort'] ?? 'name';
if (!array_key_exists($sortKey, $allowedSortColumns)) {
    $sortKey = 'name';
}
$orderByColumn = $allowedSortColumns[$sortKey];

$dir = strtoupper($_GET['dir'] ?? 'ASC');
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'ASC';
}

// 3. Bangun klausa WHERE dinamis dengan Prepared Statement
$whereClauses = ['1=1'];
$params = [];

if ($q !== '') {
    $whereClauses[] = '(p.name LIKE :q1 OR p.sku LIKE :q2)';
    $params['q1'] = "%$q%";
    $params['q2'] = "%$q%";
}
if ($categoryId > 0) {
    $whereClauses[] = 'p.category_id = :cat_id';
    $params['cat_id'] = $categoryId;
}
$whereSql = implode(' AND ', $whereClauses);

// 4. Hitung Total Data untuk Pagination (Slide 17)
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
$countStmt->execute($params);
$totalData = (int)$countStmt->fetchColumn();

$perPage = 5; // 5 produk per halaman agar pagination mudah didemokan
$totalPages = max(1, (int)ceil($totalData / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

// 5. Query Utama dengan JOIN categories & Subquery Suppliers (Many-to-Many) + LIMIT OFFSET
$sql = "
    SELECT p.*, c.name AS category_name,
           GROUP_CONCAT(CONCAT(s.name, ' (Rp', FORMAT(ps.supplier_price, 0, 'id_ID'), ')') SEPARATOR ', ') AS supplier_list
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN product_suppliers ps ON ps.product_id = p.id
    LEFT JOIN suppliers s ON s.id = ps.supplier_id
    WHERE $whereSql
    GROUP BY p.id
    ORDER BY $orderByColumn $dir
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(":$k", $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$status = $_GET['status'] ?? '';
$errMsg = $_GET['err'] ?? '';

$pageTitle = 'Daftar Produk - Inventory System';
require_once __DIR__ . '/../../views/header.php';
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
        <h2 style="margin:0;">Daftar Produk & Inventaris</h2>
        <?php if ($user['role'] === 'admin'): ?>
            <a href="create.php" class="btn btn-primary">+ Tambah Produk Baru</a>
        <?php endif; ?>
    </div>

    <?php if ($status === 'created'): ?>
        <div class="alert alert-success">Produk baru berhasil ditambahkan.</div>
    <?php elseif ($status === 'updated'): ?>
        <div class="alert alert-success">Data produk berhasil diperbarui.</div>
    <?php elseif ($status === 'deleted'): ?>
        <div class="alert alert-success">Produk berhasil dihapus.</div>
    <?php elseif ($errMsg === 'has_history'): ?>
        <div class="alert alert-danger">
            <strong>Gagal Menghapus (ON DELETE RESTRICT):</strong> Produk ini sudah memiliki riwayat transaksi stok dan tidak boleh dihapus demi integritas data histori!
        </div>
    <?php endif; ?>

    <!-- Form Search, Filter & Sorting (Slide 16) -->
    <form method="GET" action="index.php" class="filter-bar" style="margin-bottom: 16px;">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari Nama atau SKU..." style="flex:1 1 200px;">
        
        <select name="category_id">
            <option value="0">Semua Kategori</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="sort">
            <option value="name" <?= $sortKey === 'name' ? 'selected' : '' ?>>Urut: Nama</option>
            <option value="sku" <?= $sortKey === 'sku' ? 'selected' : '' ?>>Urut: SKU</option>
            <option value="price" <?= $sortKey === 'price' ? 'selected' : '' ?>>Urut: Harga</option>
            <option value="stock" <?= $sortKey === 'stock' ? 'selected' : '' ?>>Urut: Stok</option>
        </select>

        <select name="dir">
            <option value="ASC" <?= $dir === 'ASC' ? 'selected' : '' ?>>Naik (ASC)</option>
            <option value="DESC" <?= $dir === 'DESC' ? 'selected' : '' ?>>Turun (DESC)</option>
        </select>

        <button type="submit" class="btn btn-primary">Terapkan</button>
        <a href="index.php" class="btn btn-secondary">Reset</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th>Supplier (Many-to-Many)</th>
                    <th>Harga Jual</th>
                    <th>Stok</th>
                    <?php if ($user['role'] === 'admin'): ?>
                        <th>Aksi (Admin)</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="7" style="text-align:center; color:#64748b;">Tidak ada produk yang cocok.</td></tr>
                <?php endif; ?>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><code><?= e($p['sku']) ?></code></td>
                        <!-- Class .product-title memiliki font-weight: 400 agar uji Anti-XSS <b>Promo</b> terlihat jelas -->
                        <td class="product-title"><?= e($p['name']) ?></td>
                        <td><span class="badge"><?= e($p['category_name']) ?></span></td>
                        <td style="font-size:12px; color:#475569;"><?= e($p['supplier_list'] ?: 'Belum diatur') ?></td>
                        <td><strong>Rp <?= number_format((float)$p['price'], 0, ',', '.') ?></strong></td>
                        <td>
                            <strong><?= (int)$p['stock'] ?></strong>
                            <?php if ((int)$p['stock'] <= (int)$p['minimum_stock']): ?>
                                <span class="badge badge-low">Rendah (Min: <?= (int)$p['minimum_stock'] ?>)</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($user['role'] === 'admin'): ?>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <a href="edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-edit btn-sm">Edit</a>
                                <form method="POST" action="delete.php" onsubmit="return confirm('Hapus produk ini?');" style="margin:0;">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                    <button type="submit" class="btn btn-delete btn-sm">Hapus</button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination dengan mempertahankan query string (Slide 17) -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            for ($i = 1; $i <= $totalPages; $i++):
                $queryParams = $_GET;
                $queryParams['page'] = $i;
                $url = 'index.php?' . http_build_query($queryParams);
            ?>
                <a href="<?= e($url) ?>" class="<?= $i === $page ? 'active' : '' ?>">Hal <?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

</div>
</body>
</html>