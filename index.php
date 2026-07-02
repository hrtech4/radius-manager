<?php
require_once __DIR__ . '/includes/layout_header.php';

$pdo = db();
$totalUsers     = $pdo->query("SELECT COUNT(*) FROM pppoe_users")->fetchColumn();
$activeUsers    = $pdo->query("SELECT COUNT(*) FROM pppoe_users WHERE status='active'")->fetchColumn();
$suspendedUsers = $pdo->query("SELECT COUNT(*) FROM pppoe_users WHERE status='suspended'")->fetchColumn();
$totalPlans     = $pdo->query("SELECT COUNT(*) FROM plans")->fetchColumn();
$totalNas       = $pdo->query("SELECT COUNT(*) FROM nas")->fetchColumn();

$recent = $pdo->query("
  SELECT u.*, p.name AS plan_name FROM pppoe_users u
  LEFT JOIN plans p ON p.id = u.plan_id
  ORDER BY u.created_at DESC LIMIT 8
")->fetchAll();
?>
<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p>Overview of your PPPoE subscriber base</p>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <div class="label"><span class="pulse-dot"></span> Active Users</div>
    <div class="value"><?= $activeUsers ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><i class="bi bi-people"></i> Total Users</div>
    <div class="value"><?= $totalUsers ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><i class="bi bi-pause-circle"></i> Suspended</div>
    <div class="value"><?= $suspendedUsers ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><i class="bi bi-diagram-3"></i> Plans</div>
    <div class="value"><?= $totalPlans ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><i class="bi bi-hdd-network"></i> NAS Devices</div>
    <div class="value"><?= $totalNas ?></div>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>Recently added users</h2>
    <a href="users.php" class="btn btn-sm btn-outline-secondary">View all</a>
  </div>
  <?php if (empty($recent)): ?>
    <div class="empty-state">
      <i class="bi bi-inbox"></i>
      No PPPoE users yet. <a href="users.php">Add your first one</a>.
    </div>
  <?php else: ?>
  <table class="data">
    <thead><tr><th>Username</th><th>Full name</th><th>Plan</th><th>Status</th><th>Created</th></tr></thead>
    <tbody>
      <?php foreach ($recent as $u): ?>
      <tr>
        <td class="mono"><?= htmlspecialchars($u['username']) ?></td>
        <td><?= htmlspecialchars($u['full_name'] ?: '—') ?></td>
        <td><?= htmlspecialchars($u['plan_name'] ?: '—') ?></td>
        <td><span class="badge-status badge-<?= $u['status'] ?>"><?= $u['status'] ?></span></td>
        <td class="mono text-muted"><?= htmlspecialchars($u['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>
