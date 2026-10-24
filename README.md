# Inventory Management System (Mini Project 3 - Pertemuan 4)

Pengembangan lanjutan dari aplikasi CRUD *Product Manager* (Pertemuan 3) menjadi sistem manajemen inventaris berbasis **Relational Database** dan **Advanced PHP**.

## Akun Login Bawaan
- **Admin**: Username `admin` | Password `admin123` (Akses penuh ke seluruh fitur & kelola user)
- **Staff**: Username `staff` | Password `staff123` (Akses melihat produk, dashboard, dan mencatat transaksi stok IN/OUT)

## Implementasi Relasi Database
1. **One-to-One (`users` ↔ `user_profiles`)**: Setiap akun pengguna terhubung dengan tepat satu profil lengkap (`user_id` memiliki constraint `FOREIGN KEY` dan `UNIQUE`).
2. **One-to-Many (`categories` → `products` & `products` → `stock_movements`)**: Satu kategori memiliki banyak produk, dan satu produk memiliki banyak histori keluar/masuk barang.
3. **Many-to-Many (`products` ↔ `suppliers`)**: Menggunakan *junction table* `product_suppliers` dengan *composite primary key* `(product_id, supplier_id)`.

## Penjelasan Business Rules yang Diterapkan
1. **SKU Unik & Validasi Server**: Setiap produk wajib memiliki kode SKU unik, harga > 0, dan stok minimum >= 0.
2. **Konsistensi Stok & Database Transaction**: Barang keluar (`OUT`) diperiksa menggunakan `SELECT ... FOR UPDATE` di dalam `$pdo->beginTransaction()`. Jika jumlah barang keluar melebihi stok tersedia, transaksi langsung dibatalkan (`rollBack()`).
3. **Referential Integrity (`ON DELETE RESTRICT`)**: Produk yang sudah memiliki riwayat di `stock_movements` tidak dapat dihapus sembarangan.
4. **Allowlist Sorting**: Pengurutan kolom produk divalidasi menggunakan daftar putih (`name`, `sku`, `price`, `stock`) untuk mencegah SQL Injection pada klausa `ORDER BY`.