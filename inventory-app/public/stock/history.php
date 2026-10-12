<?php
declare(strict_types=1);
require __DIR__ . '/../../helpers/auth.php';
require_login();
$pdo = db();

$type = v_in($_GET['type'] ?? '', ['IN', 'OUT']);
$page = max(1, v_int_positive($_GET['page'] ?? '1') ?? 1);
$perPage = 15;
$where = $type ? 'WHERE m.type = :t' : '';
$params = $type ? [':t' => $type] : [];

$st = $pdo->prepare("SELECT COUNT(*) FROM stock_movements m $where");
$st->execute($params);
$pages = max(1, (int) ceil($st->fetchColumn() / $perPage));
$page = min($page, $pages);

$st = $pdo->prepare("SELECT m.*, p.sku, p.name, u.username FROM stock_movements m
    JOIN products p ON p.id = m.product_id JOIN users u ON u.id = m.user_id
    $where ORDER BY m.created_at DESC, m.id DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) $st->bindValue($k, $v);
$st->bindValue(':limit', $perPage, PDO::PARAM_INT);
$st->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

$title = 'Histori Stok';
include __DIR__ . '/../../views/header.php';
?>
<div class="row"><h2 style="margin:0;flex:1">Histori Mutasi Stok</h2>
  <a class="btn gray" href="?">Semua</a><a class="btn gray" href="?type=IN">Masuk</a><a class="btn gray" href="?type=OUT">Keluar</a></div>
<div class="card"><table>
  <tr><th>Waktu</th><th>Produk</th><th>Tipe</th><th>Jumlah</th><th>User</th><th>Catatan</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr><td><?= e($r['created_at']) ?></td><td><?= e($r['sku']) ?> — <?= e($r['name']) ?></td>
    <td><?= $r['type'] === 'IN' ? '⬇️ Masuk' : '⬆️ Keluar' ?></td><td><?= e($r['quantity']) ?></td>
    <td><?= e($r['username']) ?></td><td><?= e($r['note']) ?></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="6">Belum ada mutasi.</td></tr><?php endif; ?>
</table>
<p class="pg" style="margin-top:14px"><?php for ($i = 1; $i <= $pages; $i++): ?>
  <a class="<?= $i === $page ? 'cur' : '' ?>" href="?<?= e(http_build_query(array_filter(['type' => $type, 'page' => $i]))) ?>"><?= $i ?></a><?php endfor; ?></p>
</div>
<?php include __DIR__ . '/../../views/footer.php'; ?>
