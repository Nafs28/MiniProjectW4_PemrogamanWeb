<?php
declare(strict_types=1);

/** Escape semua output ke HTML (anti-XSS). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
