<?php
require_once __DIR__ . '/includes/layout_header.php';
require_once __DIR__ . '/includes/radius_sync.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id            = $_POST['id'] ?? '';
        $name          = trim($_POST['name']);
        $download_kbps = (int)$_POST['download_kbps'];
        $upload_kbps   = (int)$_POST['upload_kbps'];
        $price         = (float)$_POST['price'];
        $validity_days = (int)$_POST['validity_days'];
        $description   = trim($_POST['description']);

        if ($id) {
            $stmt = $pdo->prepare("UPDATE plans SET name=?, download_kbps=?, upload_kbps=?, price=?, validity_days=?, description=? WHERE id=?");
            $stmt->execute([$name, $download_kbps, $upload_kbps, $price, $validity_days, $description, $id]);
            radius_resync_plan_users((int)$id); // push new speeds to everyone on this plan
        } else {
            $stmt = $pdo->prepare("INSERT INTO plans (name, download_kbps, upload_kbps, price, validity_days, description) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$name, $download_kbps, $upload_kbps, $price, $validity_days, $description]);
        }
        header('Location: plans.php');
        exit;
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM plans WHERE id=?")->execute([$_POST['id']]);
        header('Location: plans.php');
        exit;
    }
}

$plans = $pdo->query("
  SELECT p.*, (SELECT COUNT(*) FROM pppoe_users u WHERE u.plan_id = p.id) AS user_count
  FROM plans p ORDER BY p.price ASC
")->fetchAll();
?>
<div class="page-head">
  <div>
    <h1>Plans</h1>
    <p>Speed tiers your PPPoE users subscribe to</p>
  </div>
  <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#planModal" onclick="openPlanModal()">
    <i class="bi bi-plus-lg"></i> Add Plan
  </button>
</div>

<div class="panel">
  <?php if (empty($plans)): ?>
    <div class="empty-state">
      <i class="bi bi-diagram-3"></i>
      No plans yet. Create one, e.g. "10Mbps Home".
    </div>
  <?php else: ?>
  <table class="data">
    <thead><tr><th>Name</th><th>Download</th><th>Upload</th><th>Price</th><th>Validity</th><th>Users</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($plans as $p): ?>
      <tr>
        <td><?= htmlspecialchars($p['name']) ?></td>
        <td class="mono"><?= number_format($p['download_kbps']) ?> kbps</td>
        <td class="mono"><?= number_format($p['upload_kbps']) ?> kbps</td>
        <td class="mono"><?= number_format($p['price'], 2) ?></td>
        <td><?= $p['validity_days'] ?> days</td>
        <td><?= $p['user_count'] ?></td>
        <td class="text-end">
          <a href="#" class="icon-btn" title="Edit"
             onclick='openPlanModal(<?= json_encode($p) ?>); return false;'><i class="bi bi-pencil"></i></a>
          <a href="#" class="icon-btn text-danger" title="Delete"
             onclick="deletePlan(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>'); return false;"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="planModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="plan_id">
      <div class="modal-header">
        <h5 class="modal-title" id="planModalTitle">Add Plan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small">Plan name</label>
          <input type="text" name="name" id="plan_name" class="form-control" placeholder="10Mbps Home" required>
        </div>
        <div class="row">
          <div class="col mb-3">
            <label class="form-label small">Download (kbps)</label>
            <input type="number" name="download_kbps" id="plan_download" class="form-control" min="1" required>
          </div>
          <div class="col mb-3">
            <label class="form-label small">Upload (kbps)</label>
            <input type="number" name="upload_kbps" id="plan_upload" class="form-control" min="1" required>
          </div>
        </div>
        <div class="row">
          <div class="col mb-3">
            <label class="form-label small">Price</label>
            <input type="number" step="0.01" name="price" id="plan_price" class="form-control" min="0">
          </div>
          <div class="col mb-3">
            <label class="form-label small">Validity (days)</label>
            <input type="number" name="validity_days" id="plan_validity" class="form-control" min="1" value="30">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small">Description</label>
          <input type="text" name="description" id="plan_description" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-accent">Save Plan</button>
      </div>
    </form>
  </div>
</div>

<form method="post" id="deletePlanForm" class="d-none">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="delete_plan_id">
</form>

<script>
function openPlanModal(plan) {
  document.getElementById('planModalTitle').textContent = plan ? 'Edit Plan' : 'Add Plan';
  document.getElementById('plan_id').value = plan ? plan.id : '';
  document.getElementById('plan_name').value = plan ? plan.name : '';
  document.getElementById('plan_download').value = plan ? plan.download_kbps : '';
  document.getElementById('plan_upload').value = plan ? plan.upload_kbps : '';
  document.getElementById('plan_price').value = plan ? plan.price : 0;
  document.getElementById('plan_validity').value = plan ? plan.validity_days : 30;
  document.getElementById('plan_description').value = plan ? plan.description : '';
  new bootstrap.Modal(document.getElementById('planModal')).show();
}
function deletePlan(id, name) {
  if (confirm('Delete plan "' + name + '"? Users on this plan will keep their account but lose their speed limit until reassigned.')) {
    document.getElementById('delete_plan_id').value = id;
    document.getElementById('deletePlanForm').submit();
  }
}
</script>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>
