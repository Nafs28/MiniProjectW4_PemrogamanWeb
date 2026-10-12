<?php
declare(strict_types=1);
require __DIR__ . '/../../helpers/auth.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { forbidden(); }
csrf_verify();

$id = v_int_positive($_POST['id'] ?? null);
if ($id !== null) {
    try {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM product_suppliers WHERE product_id = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM products WHERE id = :id')->execute([':id' => $id]); // RESTRICT jika ada riwayat mutasi
        $pdo->commit();
        flash('ok', 'Produk dihapus.');
    } catch (PDOException $e) {
        if (db()->inTransaction()) db()->rollBack();
        flash('err', $e->getCode() === '23000'
            ? 'Produk tidak dapat dihapus karena memiliki riwayat mutasi stok (ON DELETE RESTRICT).'
            : 'Terjadi kesalahan database.');
    }
}
header('Location: /products/');
