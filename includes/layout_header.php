<?php
require_once __DIR__ . '/auth.php';
require_login();
$current = basename($_SERVER['PHP_SELF']);
function navclass($file, $current) { return $file === $current ? 'nav-link active' : 'nav-link'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand">
      <i class="bi bi-router"></i>
      <span>RADIUS<b>Manager</b></span>
    </div>
    <nav class="nav flex-column">
      <a class="<?= navclass('index.php', $current) ?>" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a class="<?= navclass('users.php', $current) ?>" href="users.php"><i class="bi bi-people"></i> PPPoE Users</a>
      <a class="<?= navclass('plans.php', $current) ?>" href="plans.php"><i class="bi bi-diagram-3"></i> Plans</a>
      <a class="<?= navclass('nas.php', $current) ?>" href="nas.php"><i class="bi bi-hdd-network"></i> NAS / Routers</a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-chip"><i class="bi bi-person-circle"></i> <?= htmlspecialchars(current_admin_username()) ?></div>
      <a href="logout.php" class="logout-link"><i class="bi bi-box-arrow-right"></i> Log out</a>
    </div>
  </aside>
  <main class="content">
