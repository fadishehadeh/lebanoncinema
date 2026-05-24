<?php
/**
 * Simple admin authentication helper.
 * Include at the top of any admin page.
 */
function requireAuth(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['admin_logged_in'])) {
        $loginUrl = '/admin/login.php';
        // If SITE_URL is available, use it; otherwise just use relative path
        if (defined('SITE_URL')) {
            $loginUrl = rtrim(SITE_URL, '/') . $loginUrl;
        }
        header('Location: ' . $loginUrl);
        exit;
    }
}

function adminUrl(string $path = ''): string {
    // Use SITE_URL if available, otherwise compute from request
    if (defined('SITE_URL')) {
        return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$scheme://$host/$path";
}

function attemptLogin(string $user, string $pass): bool {
    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $user;
        return true;
    }
    return false;
}

function logout(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_user']);
    session_destroy();
}
