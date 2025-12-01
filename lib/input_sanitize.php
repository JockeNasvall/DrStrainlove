<?php declare(strict_types=1);
/**
 * Numeric input sanitizers & validators (up to 6 digits).
 * Used for Strain/Recipient/Donor and search ranges.
 */
if (!function_exists('sanitize_num6')) {
    function sanitize_num6($v): ?int {
        if ($v === null) return null;
        if (is_array($v)) $v = reset($v);
        $s = preg_replace('/\D+/', '', (string)$v);
        if ($s === '') return null;
        if (strlen($s) > 6) $s = substr($s, 0, 6);
        $n = (int)$s;
        if ($n < 0) $n = 0;
        if ($n > 999999) $n = 999999;
        return $n;
    }
}
if (!function_exists('require_num6')) {
    function require_num6(string $label, $v): array {
        $n = sanitize_num6($v);
        if ($n === null) return [false, "$label must be 1–6 digits (0–999999)."];
        return [true, $n];
    }
}
