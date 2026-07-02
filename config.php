<?php
// ============================================================
// Simple RADIUS Manager - Configuration
// Edit these values to match your MySQL database.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'radius_manager');
define('DB_USER', 'radius_manager');
define('DB_PASS', 'change_me');

// App display name
define('APP_NAME', 'Simple RADIUS Manager');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('UTC');
