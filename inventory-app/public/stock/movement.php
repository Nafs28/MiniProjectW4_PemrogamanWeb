<?php
declare(strict_types=1);
require __DIR__ . '/../../helpers/auth.php';
require_login();
$pdo = db();

$type = v_in($_GET['type'] ?? $_POST['type'] ?? 'IN', ['IN', 'OUT']) ?? 'IN';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $pid  = v_int_positive($_POST['product_id'] ?? null);
    $qty  = v_int_positive($_POST['quantity'] ?? null);          // harus integer > 0
    $note = v_text($_POST['note'] ?? '', 0, 255) ?? '';

    if ($pid === null) $errors[] = 'Produk wajib dipilih.';
    if ($qty === null) $errors[] = 'Jumlah harus bilangan bulat positif (> 0).';

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            // 1. Kunci baris produk (cegah race condition)
            $st = $pdo->prepare('SELECT stock FROM products WHERE id = :id FOR UPDATE');
            $st->execute([':id' => $pid]);
            $row = $st->fetch();
            if (!$row) throw new RuntimeException('Produk tidak ditemukan.');

            // 2. Validasi: stok keluar tidak boleh melebihi stok saat ini
            if ($type === 'OUT' && $qty > (int) $row['stock']) {
                throw new RuntimeException('Stok tidak mencukupi. Stok saat ini: ' . (int) $row['stock'] . '.');
            }

            // 3. Update stok
            $sql = $type === 'IN'
                ? 'UPDATE products SET stock = stock + :q WHERE id = :id'
                : 'UPDATE products SET stock = stock - :q WHERE id = :id';
            $pdo->prepare($sql)->execute([':q' => $qty, ':id' => $pid]);

            // 4. Catat riwayat mutasi
            $pdo->prepare('INSERT INTO stock_movements (product_id, user_id, type, quantity, note) VALUES (:p,:u,:t,:q,:n)')
                ->execute([':p' => $pid, ':u' => current_user()['id'], ':t' => $type, ':q' => $qty, ':n' => $note !== '' ? $note : null]);

            $pdo->commit();
            flash('ok', 'Transaksi barang ' . ($type === 'IN' ? 'masuk' : 'keluar') . ' berhasil dicatat.');
            header('Location: /stock/history.php');
            exit;
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Terjadi kesalahan sistem. Transaksi dibatalkan.';
        }
    }
}

$products = $pdo->query('SELECT id, sku, name, stock FROM products ORDER BY name')->fetchAll();
$title = $type === 'IN' ? 'Barang Masuk' : 'Barang Keluar';
include __DIR__ . '/../../views/header.php';
?>
<div class="card" style="max-width:520px">
  <h2><?= e($title) ?></h2>
  <?php foreach ($errors as $er): ?><div class="alert err"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="type" value="<?= e($type) ?>">
    <label>Produk</label>
    <select name="product_id" required style="width:100%"><option value="">-- pilih --</option>
      <?php foreach ($products as $p): ?><option value="<?= e($p['id']) ?>" <?= (string) ($_POST['product_id'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>>
        <?= e($p['sku']) ?> — <?= e($p['name']) ?> (stok: <?= e($p['stock']) ?>)</option><?php endforeach; ?>
    </select>
    <label>Jumlah</label><input name="quantity" required value="<?= e($_POST['quantity'] ?? '') ?>">
    <label>Catatan (opsional)</label><input name="note" style="width:100%" maxlength="255" value="<?= e($_POST['note'] ?? '') ?>">
    <p><button class="btn"><?= $type === 'IN' ? 'Simpan Barang Masuk' : 'Simpan Barang Keluar' ?></button></p>
  </form>
</div>
<?php include __DIR__ . '/../../views/footer.php'; ?>
