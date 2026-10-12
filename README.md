# Inventory App (PHP + MySQL + PDO)

## Menjalankan
1. Import `database/schema.sql` (membuat DB `inventory_app` + semua tabel).
2. Sesuaikan kredensial di `config/database.php`.
3. `php database/seed.php` -> membuat user & data contoh.
4. `php -S localhost:8000 -t public` lalu buka http://localhost:8000
   - admin / admin123 — staff / staff123

## ERD
```mermaid
erDiagram
  users ||--|| user_profiles : "1:1 (user_id UNIQUE)"
  categories ||--o{ products : "1:N"
  users ||--o{ stock_movements : "1:N"
  products ||--o{ stock_movements : "1:N"
  products ||--o{ product_suppliers : "M:N"
  suppliers ||--o{ product_suppliers : "M:N"
  users { int id PK  string username UK  string password_hash  enum role }
  user_profiles { int id PK  int user_id FK_UK  string full_name  string phone }
  categories { int id PK  string name UK }
  suppliers { int id PK  string name UK  string phone }
  products { int id PK  int category_id FK  string sku UK  string name  decimal price  int stock  int min_stock }
  product_suppliers { int product_id PK_FK  int supplier_id PK_FK }
  stock_movements { int id PK  int product_id FK  int user_id FK  enum type  int quantity  datetime created_at }
```
Semua FK: `ON UPDATE CASCADE ON DELETE RESTRICT`.

## Uji keamanan (untuk dokumentasi tugas)
- Login sebagai **staff**, buka `/products/form.php` -> **403 Forbidden**.
- Barang keluar melebihi stok -> ditolak, `rollBack()`.
- Jumlah `0`, `-5`, `abc` -> ditolak validasi.
- Harga `0` / SKU kosong atau duplikat -> ditolak.
- Cari `' OR 1=1 --` -> diperlakukan sebagai teks biasa (prepared statement).
- Nama produk `<script>alert(1)</script>` -> tampil sebagai teks (htmlspecialchars).
- `?sort=name;DROP TABLE products` -> diabaikan (allowlist).
