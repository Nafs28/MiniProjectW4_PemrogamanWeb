<?php
// views/header.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';

$user = currentUser();
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$inSubfolder = (strpos($scriptName, '/products/') !== false) || (strpos($scriptName, '/stock/') !== false);
$base = $inSubfolder ? '..' : '.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Inventory Management System') ?></title>
    <link rel="stylesheet" href="<?= $base ?>/assets/style.css">
</head>
<body>
<div class="container">
    <?php if ($user): ?>
    <nav class="navbar card">
        <div class="nav-brand">
            <strong>📦 Inventory System</strong>
            <span class="badge badge-role"><?= e(strtoupper($user['role'])) ?></span>
        </div>
        <div class="nav-links">
            <a href="<?= $base ?>/dashboard.php">Dashboard</a>
            <a href="<?= $base ?>/products/index.php">Produk</a>
            <a href="<?= $base ?>/stock/index.php">Transaksi Stok (IN/OUT)</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="<?= $base ?>/products/categories_suppliers.php">Kategori & Supplier</a>
                <a href="<?= $base ?>/users.php">Kelola User</a>
            <?php endif; ?>
            <a href="<?= $base ?>/test_runner.php" style="color:#0f766e;">✓ Test Runner</a>
        </div>
        <div class="nav-user">
            <span>👤 <?= e($user['full_name'] ?? $user['username']) ?></span>
            <a href="<?= $base ?>/logout.php" class="btn btn-delete btn-sm">Logout</a>
        </div>
    </nav>
    <?php endif; ?>