<?php
require_once __DIR__ . '/includes/layout_header.php';
require_once __DIR__ . '/includes/radius_sync.php';
$pdo = db();

function get_plan($pdo, $planId) {
    if (!$planId) return null;
    $stmt = $pdo->prepare('SELECT * FROM plans WHERE id = ?');
    $stmt->execute([$planId]);
    return $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = $_POST['id'] ?? '';
        $username    = trim($_POST['username']);
        $password    = trim($_POST['password']);
        $full_name   = trim($_POST['full_name']);
        $phone       = trim($_POST['phone']);
        $plan_id     = $_POST['plan_id'] ?: null;
        $status      = $_POST['status'];
        $expiry_date = $_POST['expiry_date'] ?: null;

        if ($id) {
            // Keep existing password if left blank on edit
            if ($password === '') {
                $stmt = $pdo->prepare('SELECT password FROM pppoe_users WHERE id = ?');
                $stmt->execute([$id]);
                $password = $stmt->fetchColumn();
            }
            $stmt = $pdo->prepare("UPDATE pppoe_users SET username=?, password=?, full_name=?, phone=?, plan_id=?, status=?, expiry_date=? WHERE id=?");
            $stmt->execute([$username, $password, $full_name, $phone, $plan_id, $status, $expiry_date, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO pppoe_users (username, password, full_name, phone, plan_id, status, expiry_date) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$username, $password, $full_name, $phone, $plan_id, $status, $expiry_date]);
        }

        radius_sync_user($username, $password, get_plan($pdo, $plan_id), $status);
        header('Location: users.php');
        exit;
    }

    if ($action === 'delete') {
        $stmt = $pdo->prepare('SELECT username FROM pppoe_users WHERE id = ?');
        $stmt->execute([$_POST['id']]);
        $username = $stmt->fetchColumn();
        $pdo->prepare("DELETE FROM pppoe_users WHERE id=?")->execute([$_POST['id']]);
        if ($username) radius_delete_user($username);
        header('Location: users.php');
        exit;
    }

    if ($action === 'toggle_status') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare('SELECT * FROM pppoe_users WHERE id = ?');
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if ($u) {
            $newStatus = $u['status'] === 'active' ? 'suspended' : 'active';
            $pdo->prepare('UPDATE pppoe_users SET status=? WHERE id=?')->execute([$newStatus, $id]);
            radius_sync_user($u['username'], $u['password'], get_plan($pdo, $u['plan_id']), $newStatus);
        }
        header('Location: users.php');
        exit;
    }
}

$plans = $pdo->query("SELECT * FROM plans ORDER BY name")->fetchAll();
$users = $pdo->query("
  SELECT u.*, p.name AS plan_name FROM pppoe_users u
  LEFT JOIN plans p ON p.id = u.plan_id
  ORDER BY u.created_at DESC
")->fetchAll();
?>
<div class="page-head">
  <div>
    <h1>PPPoE Users</h1>
    <p>Subscriber accounts authenticated via RADIUS</p>
  </div>
  <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openUserModal()">
    <i class="bi bi-plus-lg"></i> Add User
  </button>
</div>

<div class="panel">
  <?php if (empty($users)): ?>
    <div class="empty-state">
      <i class="bi bi-people"></i>
      No PPPoE users yet.<?= empty($plans) ? ' Create a plan first, then add a user.' : '' ?>
    </div>
  <?php else: ?>
  <table class="data">
    <thead><tr><th>Username</th><th>Full name</th><th>Plan</th><th>Expiry</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td class="mono"><?= htmlspecialchars($u['username']) ?></td>
        <td><?= htmlspecialchars($u['full_name'] ?: '—') ?><?php if ($u['phone']): ?><div class="text-muted small"><?= htmlspecialchars($u['phone']) ?></div><?php endif; ?></td>
        <td><?= htmlspecialchars($u['plan_name'] ?: '—') ?></td>
        <td class="mono text-muted"><?= htmlspecialchars($u['expiry_date'] ?: '—') ?></td>
        <td><span class="badge-status badge-<?= $u['status'] ?>"><?= $u['status'] ?></span></td>
        <td class="text-end">
          <a href="#" class="icon-btn" title="<?= $u['status'] === 'active' ? 'Suspend' : 'Reactivate' ?>"
             onclick="toggleStatus(<?= $u['id'] ?>); return false;">
             <i class="bi bi-<?= $u['status'] === 'active' ? 'pause-circle' : 'play-circle' ?>"></i>
          </a>
          <a href="#" class="icon-btn" title="Edit"
             onclick='openUserModal(<?= json_encode($u) ?>); return false;'><i class="bi bi-pencil"></i></a>
          <a href="#" class="icon-btn text-danger" title="Delete"
             onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>'); return false;"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="user_id">
      <div class="modal-header">
        <h5 class="modal-title" id="userModalTitle">Add PPPoE User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small">PPPoE username</label>
          <input type="text" name="username" id="user_username" class="form-control mono" required>
        </div>
        <div class="mb-3">
          <label class="form-label small">Password <span id="pw_hint" class="text-muted d-none">(leave blank to keep current)</span></label>
          <input type="text" name="password" id="user_password" class="form-control mono">
        </div>
        <div class="mb-3">
          <label class="form-label small">Full name</label>
          <input type="text" name="full_name" id="user_full_name" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label small">Phone</label>
          <input type="text" name="phone" id="user_phone" class="form-control">
        </div>
        <div class="row">
          <div class="col mb-3">
            <label class="form-label small">Plan</label>
            <select name="plan_id" id="user_plan_id" class="form-select">
              <option value="">— none —</option>
              <?php foreach ($plans as $p): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col mb-3">
            <label class="form-label small">Status</label>
            <select name="status" id="user_status" class="form-select">
              <option value="active">active</option>
              <option value="suspended">suspended</option>
              <option value="expired">expired</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small">Expiry date</label>
          <input type="date" name="expiry_date" id="user_expiry_date" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-accent">Save User</button>
      </div>
    </form>
  </div>
</div>

<form method="post" id="deleteUserForm" class="d-none">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="delete_user_id">
</form>
<form method="post" id="toggleStatusForm" class="d-none">
  <input type="hidden" name="action" value="toggle_status">
  <input type="hidden" name="id" id="toggle_user_id">
</form>

<script>
function openUserModal(u) {
  document.getElementById('userModalTitle').textContent = u ? 'Edit PPPoE User' : 'Add PPPoE User';
  document.getElementById('user_id').value = u ? u.id : '';
  document.getElementById('user_username').value = u ? u.username : '';
  document.getElementById('user_password').value = '';
  document.getElementById('user_password').required = !u;
  document.getElementById('pw_hint').classList.toggle('d-none', !u);
  document.getElementById('user_full_name').value = u ? u.full_name : '';
  document.getElementById('user_phone').value = u ? u.phone : '';
  document.getElementById('user_plan_id').value = u && u.plan_id ? u.plan_id : '';
  document.getElementById('user_status').value = u ? u.status : 'active';
  document.getElementById('user_expiry_date').value = u ? (u.expiry_date || '') : '';
  new bootstrap.Modal(document.getElementById('userModal')).show();
}
function deleteUser(id, name) {
  if (confirm('Delete user "' + name + '"? This removes them from RADIUS too.')) {
    document.getElementById('delete_user_id').value = id;
    document.getElementById('deleteUserForm').submit();
  }
}
function toggleStatus(id) {
  document.getElementById('toggle_user_id').value = id;
  document.getElementById('toggleStatusForm').submit();
}
</script>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>
