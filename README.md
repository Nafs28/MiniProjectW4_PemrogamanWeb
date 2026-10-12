# Inventory App (PHP + MySQL + PDO)

## Menjalankan
1. Import `database/schema.sql` (membuat DB `inventory_app` + semua tabel).
2. Sesuaikan kredensial di `config/database.php`.
3. `php database/seed.php` -> membuat user & data contoh.
4. `php -S localhost:8000 -t public` lalu buka http://localhost:8000
   - admin / admin123 — staff / staff123

## Uji keamanan (untuk dokumentasi tugas)
- Login sebagai **staff**, buka `/products/form.php` -> **403 Forbidden**.
- Barang keluar melebihi stok -> ditolak, `rollBack()`.
- Jumlah `0`, `-5`, `abc` -> ditolak validasi.
- Harga `0` / SKU kosong atau duplikat -> ditolak.
- Cari `' OR 1=1 --` -> diperlakukan sebagai teks biasa (prepared statement).
- Nama produk `<script>alert(1)</script>` -> tampil sebagai teks (htmlspecialchars).
- `?sort=name;DROP TABLE products` -> diabaikan (allowlist).
