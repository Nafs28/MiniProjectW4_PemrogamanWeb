<?php
// helpers/auth.php

function isLoggedIn(): bool {
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function requireLogin(string $loginUrl = 'login.php'): void {
    if (!isLoggedIn()) {
        header("Location: $loginUrl");
        exit;
    }
}

function requireAdmin(): void {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $inSubfolder = (strpos($scriptName, '/products/') !== false) || (strpos($scriptName, '/stock/') !== false);
    requireLogin($inSubfolder ? '../login.php' : 'login.php');

    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style="font-family:sans-serif;padding:40px;text-align:center;">
            <h1 style="color:#dc2626;">403 Forbidden</h1>
            <p>Maaf, halaman ini hanya dapat diakses oleh <strong>Admin</strong> (Role Anda saat ini: <strong>' . htmlspecialchars($_SESSION['user']['role'] ?? 'guest', ENT_QUOTES, 'UTF-8') . '</strong>).</p>
            <a href="javascript:history.back()">Kembali ke halaman sebelumnya</a>
        </body></html>');
    }
}