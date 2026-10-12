<?php $u = current_user(); $flash = take_flash(); ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Inventory App') ?></title>
<style>
 body{font-family:system-ui,sans-serif;margin:0;background:#f4f6f8;color:#222}
 nav{background:#1f2937;padding:10px 20px;display:flex;gap:14px;flex-wrap:wrap;align-items:center}
 nav a{color:#e5e7eb;text-decoration:none} nav a:hover{text-decoration:underline} nav .sp{flex:1}
 main{max-width:1050px;margin:20px auto;padding:0 16px}
 .card{background:#fff;border-radius:8px;padding:18px;margin-bottom:16px;box-shadow:0 1px 3px #0002}
 table{width:100%;border-collapse:collapse} th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}
 th a{color:inherit} input,select,textarea{padding:7px;border:1px solid #cbd5e1;border-radius:5px;font:inherit}
 .btn{background:#2563eb;color:#fff;border:0;padding:7px 14px;border-radius:5px;cursor:pointer;text-decoration:none;font-size:14px;display:inline-block}
 .btn.red{background:#dc2626}.btn.gray{background:#6b7280}
 .alert{padding:10px 14px;border-radius:6px;margin-bottom:14px}.ok{background:#dcfce7}.err{background:#fee2e2}
 .low{color:#b91c1c;font-weight:600} .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px}
 .stat{font-size:26px;font-weight:700} .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
 label{display:block;margin:10px 0 4px;font-weight:600;font-size:14px}.pg a{padding:4px 9px;border:1px solid #cbd5e1;border-radius:4px;text-decoration:none;color:#222;margin-right:3px}
 .pg a.cur{background:#2563eb;color:#fff}
</style>
</head>
<body>
<?php if ($u): ?>
<nav>
 <strong style="color:#fff">📦 Inventory</strong>
 <a href="/dashboard.php">Dashboard</a>
 <a href="/products/">Produk</a>
 <a href="/stock/movement.php?type=IN">Barang Masuk</a>
 <a href="/stock/movement.php?type=OUT">Barang Keluar</a>
 <a href="/stock/history.php">Histori</a>
 <?php if ($u['role'] === 'admin'): ?>
  <a href="/categories/">Kategori</a><a href="/suppliers/">Supplier</a><a href="/users/">User</a>
 <?php endif; ?>
 <span class="sp"></span>
 <span style="color:#9ca3af"><?= e($u['username']) ?> (<?= e($u['role']) ?>)</span>
 <a href="/logout.php">Logout</a>
</nav>
<?php endif; ?>
<main>
<?php if ($flash): ?><div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
