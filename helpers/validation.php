<?php
declare(strict_types=1);

/** Validasi sintaks + semantik. Mengembalikan nilai bersih atau null jika tidak valid. */
function v_int_positive($v): ?int
{
    return (is_string($v) || is_int($v)) && preg_match('/^[1-9][0-9]*$/', (string) $v) ? (int) $v : null;
}

function v_int_nonneg($v): ?int
{
    return (is_string($v) || is_int($v)) && preg_match('/^(0|[1-9][0-9]*)$/', (string) $v) ? (int) $v : null;
}

function v_price($v): ?string
{
    if (!is_string($v) || !preg_match('/^\d{1,10}(\.\d{1,2})?$/', $v) || (float) $v <= 0) {
        return null;
    }
    return $v;
}

function v_sku($v): ?string
{
    return is_string($v) && preg_match('/^[A-Za-z0-9_-]{3,30}$/', trim($v)) ? strtoupper(trim($v)) : null;
}

function v_text($v, int $min = 1, int $max = 100): ?string
{
    if (!is_string($v)) return null;
    $v = trim($v);
    $len = mb_strlen($v);
    return ($len >= $min && $len <= $max) ? $v : null;
}

function v_in($v, array $allowed): ?string
{
    return is_string($v) && in_array($v, $allowed, true) ? $v : null;
}
