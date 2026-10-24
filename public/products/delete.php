<?php
// public/products/delete.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method tidak diizinkan.');
}

verifyCsrfToken($_POST['csrf'] ?? '');

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if ($id) {
    try {
        // Karena stock_movements menggunakan ON DELETE RESTRICT (Slide 9),
        // MySQL otomatis menolak penghapusan jika produk sudah punya histori transaksi!
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute(['id' => $id]);
        header('Location: index.php?status=deleted');
        exit;
    } catch (PDOException $e) {
        header('Location: index.php?err=has_history');
        exit;
    }
}

header('Location: index.php');
exit;