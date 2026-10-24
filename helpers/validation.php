<?php
// helpers/validation.php

function verifyCsrfToken(?string $token): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)$token)) {
        http_response_code(403);
        exit('Akses Ditolak: Token CSRF tidak valid.');
    }
}

function validateProductData(array $input): array {
    $errors = [];
    $sku = strtoupper(trim($input['sku'] ?? ''));
    $name = trim($input['name'] ?? '');
    $categoryId = filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT);
    $price = filter_var($input['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $minStock = filter_var($input['minimum_stock'] ?? null, FILTER_VALIDATE_INT);

    if (mb_strlen($sku) < 3) {
        $errors['sku'] = 'SKU wajib diisi (minimal 3 karakter, contoh: KOP-001).';
    }
    if (mb_strlen($name) < 3) {
        $errors['name'] = 'Nama produk minimal 3 karakter.';
    }
    if ($categoryId === false || $categoryId <= 0) {
        $errors['category_id'] = 'Kategori wajib dipilih.';
    }
    if ($price === false || $price <= 0) {
        $errors['price'] = 'Harga harus berupa angka dan lebih dari 0.';
    }
    if ($minStock === false || $minStock < 0) {
        $errors['minimum_stock'] = 'Minimum stok tidak boleh negatif.';
    }

    return [
        'errors' => $errors,
        'data' => [
            'sku' => $sku,
            'name' => $name,
            'category_id' => (int)$categoryId,
            'price' => (float)$price,
            'minimum_stock' => (int)$minStock
        ]
    ];
}