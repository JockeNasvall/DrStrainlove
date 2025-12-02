<?php
/** 
 * File: functions.php
 * Cleaned: 2025-11-12
 * Description: Part of Dr Strainlove frontend.
 */

// Load centralized permission helpers if present (non-fatal)
if (file_exists(__DIR__ . '/permissions.php')) {
    require_once __DIR__ . '/permissions.php';
} else {
    // permissions.php is optional in some deployments; log for diagnostics
    error_log('[BOOT] permissions.php not found at ' . __DIR__ . '/permissions.php — continuing without it.');
}

// CHECK IF USER EXISTS
/**
 * user_exists — function documentation.
 *
 * @param mixed $user
 * @param mixed $field
 * @return mixed
 */

function user_exists($user = NULL, $field = "id"){
    global $dbh;

    if ($field === "id") {
        $stmt = $dbh->prepare("SELECT 1 FROM users WHERE Id = :user LIMIT 1");
    } elseif ($field === "username") {
        $stmt = $dbh->prepare("SELECT 1 FROM users WHERE Username = :user LIMIT 1");
    } elseif ($field === "signature") {
        $stmt = $dbh->prepare("SELECT 1 FROM users WHERE Signature = :user LIMIT 1");
    } else {
        return false;
    }

    $stmt->bindValue(":user", $user);
    $stmt->execute();
    return (bool)$stmt->fetchColumn();
}

// CHECK IF STRAIN EXISTS
/**
 * strain_exists — function documentation.
 *
 * @param mixed $strain
 * @return mixed
 */

function strain_exists($strain = NULL): bool {
    global $dbh;
    if ($strain === null) return false;
    if (!is_numeric($strain)) return false;

    $stmt = $dbh->prepare("SELECT 1 FROM strains WHERE Strain = :strain LIMIT 1");
    $stmt->bindValue(":strain", (int)$strain, PDO::PARAM_INT);
    $stmt->execute();
    return (bool)$stmt->fetchColumn();
}

// Output-escape helper
if (!function_exists('e')) {
    /**
     * e — function documentation.
     *
     * @param mixed $s
     * @return mixed
     */

    function e(?string $s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// --- Centralized flash helpers (used across the app) ---
if (!function_exists('flash')) {
    function flash(string $type, string $message): void {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['feedback_type'] = $type;
        $_SESSION['feedback_message'] = $message;
    }
}
if (!function_exists('flash_success')) { function flash_success(string $m): void { flash('success', $m); } }
if (!function_exists('flash_error'))   { function flash_error(string $m): void { flash('error',   $m); } }
if (!function_exists('flash_info'))    { function flash_info(string $m): void { flash('info',    $m); } }

// CSRF token helpers
if (!function_exists('csrf_token')) {
    /**
     * csrf_token — function documentation.
     *
     * @return mixed
     */

    function csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf')) {
    /**
     * verify_csrf — function documentation.
     *
     * @param mixed $t
     * @return mixed
     */

    function verify_csrf(?string $t): void {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $expected = $_SESSION['csrf_token'] ?? '';
        if (!is_string($t) || !hash_equals((string)$expected, (string)$t)) {
            http_response_code(419);
            exit('CSRF token mismatch');
        }
    }
}
