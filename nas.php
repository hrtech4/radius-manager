<?php
require_once __DIR__ . '/includes/layout_header.php';
$pdo = db();

// ---- Handle actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = $_POST['id'] ?? '';
        $nasname     = trim($_POST['nasname']);
        $shortname   = trim($_POST['shortname']);
        $type        = trim($_POST['type']) ?: 'other';
        $secret      = trim($_POST['secret']);
        $description = trim($_POST['description']);

        if ($id) {
            $stmt = $pdo->prepare("UPDATE nas SET nasname=?, shortname=?, type=?, secret=?, description=? WHERE id=?");
            $stmt->execute([$nasname, $shortname, $type, $secret, $description, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO nas (nasname, shortname, type, secret, description) VALUES (?,?,?,?,?)");
            $stmt->execute([$nasname, $shortname, $type, $secret, $description]);
        }
        header('Location: nas.php');
        exit;
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM nas WHERE id=?")->execute([$_POST['id']]);
        header('Location: nas.php');
        exit;
    }
}

$devices = $pdo->query("SELECT * FROM nas ORDER BY shortname")->fetchAll();
?>
<div class="page-head">
  <div>
    <h1>NAS / Routers</h1>
    <p>The BRAS or routers (e.g. MikroTik) that send RADIUS requests here</p>
  </div>
  <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#nasModal" onclick="openNasModal()">
    <i class="bi bi-plus-lg"></i> Add NAS
  </button>
</div>

<div class="panel">
  <?php if (empty($devices)): ?>
    <div class="empty-state">
      <i class="bi bi-hdd-network"></i>
      No NAS devices yet. Add the router that will send PPPoE auth requests.
    </div>
  <?php else: ?>
  <table class="data">
    <thead><tr><th>Short name</th><th>IP address</th><th>Type</th><th>Secret</th><th>Description</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($devices as $n): ?>
      <tr>
        <td><?= htmlspecialchars($n['shortname']) ?></td>
        <td class="mono"><?= htmlspecialchars($n['nasname']) ?></td>
        <td><?= htmlspecialchars($n['type']) ?></td>
        <td class="mono">••••••••</td>
        <td class="text-muted"><?= htmlspecialchars($n['description']) ?></td>
        <td class="text-end">
          <a href="#" class="icon-btn" title="Edit"
             onclick='openNasModal(<?= json_encode($n) ?>); return false;'><i class="bi bi-pencil"></i></a>
          <a href="#" class="icon-btn text-danger" title="Delete"
             onclick="deleteNas(<?= $n['id'] ?>, '<?= htmlspecialchars($n['shortname'], ENT_QUOTES) ?>'); return false;"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="nasModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="nas_id">
      <div class="modal-header">
        <h5 class="modal-title" id="nasModalTitle">Add NAS</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small">Short name</label>
          <input type="text" name="shortname" id="nas_shortname" class="form-control" placeholder="core-router-1" required>
        </div>
        <div class="mb-3">
          <label class="form-label small">IP address</label>
          <input type="text" name="nasname" id="nas_nasname" class="form-control mono" placeholder="10.0.0.1" required>
        </div>
        <div class="mb-3">
          <label class="form-label small">Type</label>
          <select name="type" id="nas_type" class="form-select">
            <option value="other">other</option>
            <option value="mikrotik">mikrotik</option>
            <option value="cisco">cisco</option>
            <option value="juniper">juniper</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small">Shared secret</label>
          <input type="text" name="secret" id="nas_secret" class="form-control mono" required>
        </div>
        <div class="mb-3">
          <label class="form-label small">Description</label>
          <input type="text" name="description" id="nas_description" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-accent">Save NAS</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete form (hidden, submitted by JS) -->
<form method="post" id="deleteNasForm" class="d-none">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="delete_nas_id">
</form>

<script>
function openNasModal(nas) {
  document.getElementById('nasModalTitle').textContent = nas ? 'Edit NAS' : 'Add NAS';
  document.getElementById('nas_id').value = nas ? nas.id : '';
  document.getElementById('nas_shortname').value = nas ? nas.shortname : '';
  document.getElementById('nas_nasname').value = nas ? nas.nasname : '';
  document.getElementById('nas_type').value = nas ? nas.type : 'other';
  document.getElementById('nas_secret').value = nas ? nas.secret : '';
  document.getElementById('nas_description').value = nas ? nas.description : '';
  new bootstrap.Modal(document.getElementById('nasModal')).show();
}
function deleteNas(id, name) {
  if (confirm('Delete NAS "' + name + '"? This cannot be undone.')) {
    document.getElementById('delete_nas_id').value = id;
    document.getElementById('deleteNasForm').submit();
  }
}
</script>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>
