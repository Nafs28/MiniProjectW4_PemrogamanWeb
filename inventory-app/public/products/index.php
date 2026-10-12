<?php
declare(strict_types=1);
require __DIR__ . '/../../helpers/auth.php';
require_login();
$pdo = db();

// --- Parameter: search, filter, sort (whitelist), pagination ---
$q     = trim((string) ($_GET['q'] ?? ''));
$cat   = v_int_positive($_GET['category_id'] ?? null);
$sortMap = ['name' => 'p.name', 'sku' => 'p.sku', 'price' => 'p.price', 'stock' => 'p.stock']; // allowlist kolom
$sort  = array_key_exists($_GET['sort'] ?? '', $sortMap) ? $_GET['sort'] : 'name';
$dir   = (($_GET['dir'] ?? '') === 'desc') ? 'DESC' : 'ASC';
$page  = max(1, v_int_positive($_GET['page'] ?? '1') ?? 1);
$perPage = 10;

$where = []; $params = [];
if ($q !== '') {
    $where[] = '(p.name LIKE :q1 OR p.sku LIKE :q2)';
    $params[':q1'] = $params[':q2'] = '%' . $q . '%';
}
if ($cat !== null) { $where[] = 'p.category_id = :cat'; $params[':cat'] = $cat; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = $pdo->prepare("SELECT COUNT(*) FROM products p $whereSql");
$st->execute($params);
$total = (int) $st->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page  = min($page, $pages);
$offset = ($page - 1) * $perPage;               // offset = (page - 1) * perPage

$sql = "SELECT p.*, c.name AS category FROM products p
        JOIN categories c ON c.id = p.category_id
        $whereSql ORDER BY {$sortMap[$sort]} $dir LIMIT :limit OFFSET :offset";
$st = $pdo->prepare($sql);
foreach ($params as $k => $v) { $st->bindValue($k, $v); }
$st->bindValue(':limit', $perPage, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

$cats = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

// Pertahankan parameter pencarian/filter saat navigasi halaman
function qs(array $over = []): string {
    $base = array_filter([
        'q' => $_GET['q'] ?? '', 'category_id' => $_GET['category_id'] ?? '',
        'sort' => $_GET['sort'] ?? '', 'dir' => $_GET['dir'] ?? '', 'page' => $_GET['page'] ?? '',
    ], fn($v) => $v !== '');
    return '?' . http_build_query(array_merge($base, $over));
}
$nextDir = $dir === 'ASC' ? 'desc' : 'asc';

$title = 'Produk';
include __DIR__ . '/../../views/header.php';
?>
<div class="row"><h2 style="margin:0;flex:1">Produk</h2>
<?php if (is_admin()): ?><a class="btn" href="/products/form.php">+ Tambah Produk</a><?php endif; ?></div>

<form class="card row" method="get">
  <input name="q" placeholder="Cari nama / SKU" value="<?= e($q) ?>">
  <select name="category_id"><option value="">Semua kategori</option>
    <?php foreach ($cats as $c): ?><option value="<?= e($c['id']) ?>" <?= $cat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
  </select>
  <select name="sort">
    <?php foreach (['name' => 'Nama', 'sku' => 'SKU', 'price' => 'Harga', 'stock' => 'Stok'] as $k => $l): ?>
      <option value="<?= e($k) ?>" <?= $sort === $k ? 'selected' : '' ?>>Urut: <?= e($l) ?></option><?php endforeach; ?>
  </select>
  <select name="dir"><option value="asc" <?= $dir === 'ASC' ? 'selected' : '' ?>>Naik</option><option value="desc" <?= $dir === 'DESC' ? 'selected' : '' ?>>Turun</option></select>
  <button class="btn">Terapkan</button>
</form>

<div class="card">
<table>
  <tr>
    <?php foreach (['sku' => 'SKU', 'name' => 'Nama', 'price' => 'Harga', 'stock' => 'Stok'] as $k => $l): ?>
      <th><a href="<?= e(qs(['sort' => $k, 'dir' => $nextDir, 'page' => 1])) ?>"><?= e($l) ?></a></th>
    <?php endforeach; ?>
    <th>Kategori</th><?php if (is_admin()): ?><th>Aksi</th><?php endif; ?>
  </tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['sku']) ?></td><td><?= e($r['name']) ?></td>
    <td>Rp <?= e(number_format((float) $r['price'], 0, ',', '.')) ?></td>
    <td class="<?= $r['stock'] <= $r['min_stock'] ? 'low' : '' ?>"><?= e($r['stock']) ?></td>
    <td><?= e($r['category']) ?></td>
    <?php if (is_admin()): ?><td>
      <a class="btn gray" href="/products/form.php?id=<?= e($r['id']) ?>">Edit</a>
      <form method="post" action="/products/delete.php" style="display:inline" onsubmit="return confirm('Hapus produk ini?')">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($r['id']) ?>"><button class="btn red">Hapus</button>
      </form></td><?php endif; ?>
  </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="6">Tidak ada data.</td></tr><?php endif; ?>
</table>
<p class="pg" style="margin-top:14px">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a class="<?= $i === $page ? 'cur' : '' ?>" href="<?= e(qs(['page' => $i])) ?>"><?= $i ?></a>
  <?php endfor; ?> <small>(<?= e($total) ?> produk)</small>
</p>
</div>
<?php include __DIR__ . '/../../views/footer.php'; ?>
