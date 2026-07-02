<?php
require_once __DIR__ . '/db.php';

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function any_admin_exists(): bool
{
    $count = db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    return $count > 0;
}

function current_admin_username(): string
{
    return $_SESSION['admin_username'] ?? '';
}
