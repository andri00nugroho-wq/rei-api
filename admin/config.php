<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('REI_ADMIN_BASE', '/rei');
define('REI_API_BASE', REI_ADMIN_BASE . '/api');

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_user']) || !empty($_SESSION['admin_logged_in']);
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        header('Location: ' . REI_ADMIN_BASE . '/admin/login.php');
        exit;
    }
}
