<?php
/**
 * File: permissions.php
 * Centralized role + permission helpers.
 */

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function allowed_roles(): array {
    return ['Guest','User','Superuser'];
}

function current_role(): string {
    $r = $_SESSION['Usertype'] ?? 'Guest';
    return in_array($r, allowed_roles(), true) ? $r : 'Guest';
}

/**
 * can — permission check with simple role mapping.
 * add_strains, edit_strains, manage_users, search
 */
function can(string $perm, ?string $role = null): bool {
    $role = $role ?? current_role();
    switch ($perm) {
        case 'search':
            return in_array($role, ['Guest','User','Superuser'], true);
        case 'add_strains':
        case 'edit_strains':
            return in_array($role, ['User','Superuser'], true);
        case 'manage_users':
            return $role === 'Superuser';
        default:
            return false;
    }
}
