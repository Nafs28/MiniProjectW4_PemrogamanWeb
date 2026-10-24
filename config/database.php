<?php
// config/database.php
// Wajib tampilkan pesan error agar tidak layar putih (blank)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kompatibel untuk PHP 7 maupun PHP 8
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$host = 'localhost';
$dbname = 'inventory_db';
$username = 'root';
$password = ''; // Kosongkan untuk XAMPP / Laragon standar

try {
    $pdo = new PDO(
        "mysql:host=$host;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Buat database otomatis jika belum ada
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // Buat seluruh tabel relasi secara otomatis jika belum ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_profiles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL UNIQUE,
        full_name VARCHAR(100) NOT NULL,
        phone VARCHAR(30) DEFAULT '-',
        FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        contact VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT PRIMARY KEY AUTO_INCREMENT,
        category_id INT NOT NULL,
        sku VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(150) NOT NULL,
        price DECIMAL(12,2) NOT NULL,
        stock INT NOT NULL DEFAULT 0,
        minimum_stock INT NOT NULL DEFAULT 5,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS product_suppliers (
        product_id INT NOT NULL,
        supplier_id INT NOT NULL,
        supplier_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        PRIMARY KEY (product_id, supplier_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE CASCADE,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_movements (
        id INT PRIMARY KEY AUTO_INCREMENT,
        product_id INT NOT NULL,
        user_id INT NOT NULL,
        type ENUM('IN', 'OUT') NOT NULL,
        quantity INT NOT NULL,
        notes VARCHAR(255) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE RESTRICT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB");

    // Seed Data Awal jika tabel users masih kosong
    $userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount === 0) {
        $stmtUser = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (:u, :p, :r)");
        $stmtProfile = $pdo->prepare("INSERT INTO user_profiles (user_id, full_name, phone) VALUES (:uid, :fn, :ph)");

        $stmtUser->execute(['u' => 'admin', 'p' => password_hash('admin123', PASSWORD_DEFAULT), 'r' => 'admin']);
        $adminId = (int)$pdo->lastInsertId();
        $stmtProfile->execute(['uid' => $adminId, 'fn' => 'Budi Santoso (Kepala Gudang)', 'ph' => '0812-1111-2222']);

        $stmtUser->execute(['u' => 'staff', 'p' => password_hash('staff123', PASSWORD_DEFAULT), 'r' => 'staff']);
        $staffId = (int)$pdo->lastInsertId();
        $stmtProfile->execute(['uid' => $staffId, 'fn' => 'Siti Aminah (Staff Gudang)', 'ph' => '0813-3333-4444']);

        $pdo->exec("INSERT INTO categories (id, name) VALUES (1, 'Biji Kopi'), (2, 'Alat Seduh'), (3, 'Aksesoris Barista')");
        $pdo->exec("INSERT INTO suppliers (id, name, contact) VALUES 
            (1, 'Koperasi Petani Kopi Gayo', '0811-6000-111'),
            (2, 'PT Nusantara Brewing Gear', '021-555-8899')");

        $pdo->exec("INSERT INTO products (id, category_id, sku, name, price, stock, minimum_stock) VALUES 
            (1, 1, 'KOP-001', 'Kopi Arabika Aceh Gayo 250g', 85000.00, 15, 5),
            (2, 2, 'ALT-001', 'V60 Dripper Ceramic 02', 120000.00, 4, 5),
            (3, 3, 'AKS-001', 'Paper Filter V60 Isi 100', 45000.00, 25, 10),
            (4, 1, 'KOP-002', 'Kopi Robusta Lampung 500g', 65000.00, 3, 5)");

        $pdo->exec("INSERT INTO product_suppliers (product_id, supplier_id, supplier_price) VALUES 
            (1, 1, 68000.00), (4, 1, 48000.00), (2, 2, 95000.00), (3, 2, 32000.00)");

        $pdo->exec("INSERT INTO stock_movements (product_id, user_id, type, quantity, notes) VALUES 
            (1, $adminId, 'IN', 20, 'Stok awal gudang dari petani'),
            (1, $staffId, 'OUT', 5, 'Diambil untuk etalase toko'),
            (2, $adminId, 'IN', 4, 'Stok awal alat seduh'),
            (3, $adminId, 'IN', 25, 'Stok awal paper filter'),
            (4, $adminId, 'IN', 3, 'Stok awal robusta')");
    }

} catch (PDOException $e) {
    http_response_code(500);
    die("Koneksi database gagal: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}