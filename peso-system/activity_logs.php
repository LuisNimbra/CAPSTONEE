<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = db();

$logs = $pdo->query("
    SELECT al.*, u.full_name
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 200
")->fetchAll();

// System status
$dbOk = true;
try { db()->query('SELECT 1'); } catch (Throwable) { $dbOk = false; }
$mlStatus = mlApiGet('/status');
$mlOk     = ($mlStatus !== null);

// Module counts
$moduleCounts = $pdo->query("
    SELECT module, COUNT(*) cnt FROM activity_logs GROUP BY module ORDER BY cnt DESC
")->fetchAll();

$pageTitle = 'Activity Logs "” PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-clock-history me-2 text-primary"></i>Activity Logs &amp; System Management</h4>
</div>

<div class="row g-3 mb-4">
  <!-- System status -->
  <div class="col-md-4">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-hdd-stack me-2 text-primary"></i>System Status</h6>
      <?php
      $statusItems = [
        ['ML Model',    $mlOk,  $mlOk  ? ($mlStatus['model'] ?? 'Active') : 'Offline "” start Flask API', 'cpu'],
        ['Database',    $dbOk,  $dbOk  ? 'Connected (MySQL)'           : 'Connection failed',            'database'],
        ['Sync Status', true,   'Running',                                                                 'arrow-repeat'],
      ];
      foreach ($statusItems as [$name, $ok, $detail, $icon]): ?>
      <div class="d-flex justify-content-between align-items-center mb-3 p-2 border rounded">
        <div>
          <i class="bi bi-<?= $icon ?> me-2 text-<?= $ok ? 'success' : 'danger' ?>"></i>
          <span class="fw-semibold"><?= $name ?></span><br>
          <small class="text-muted ms-4"><?= h($detail) ?></small>
        </div>
        <span class="badge bg-<?= $ok ? 'success' : 'danger' ?>"><?= $ok ? 'OK' : 'Error' ?></span>
      </div>
      <?php endforeach; ?>

      <?php if ($mlOk && isset($mlStatus['accuracy'])): ?>
      <div class="border rounded p-2 bg-light">
        <small class="text-muted">Active model: <strong><?= h($mlStatus['model']) ?></strong></small><br>
        <small class="text-muted">Accuracy: <strong><?= round($mlStatus['accuracy'] * 100, 1) ?>%</strong></small><br>
        <small class="text-muted">Precision: <strong><?= round(($mlStatus['precision'] ?? 0) * 100, 1) ?>%</strong></small>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick links -->
  <div class="col-md-4">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-lightning me-2 text-primary"></i>Quick Actions</h6>
      <div class="d-grid gap-2">
        <a href="/applicants.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-people me-2"></i>Manage Applicants</a>
        <a href="/jobs.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-briefcase me-2"></i>Manage Job Vacancies</a>
        <a href="/matching.php" class="btn btn-outline-success btn-sm"><i class="bi bi-cpu me-2"></i>AI Job Matching</a>
        <a href="/dataset_upload.php" class="btn btn-outline-warning btn-sm"><i class="bi bi-cloud-upload me-2"></i>Upload Dataset / Train ML</a>
      </div>
    </div>
  </div>

  <!-- Activity by module -->
  <div class="col-md-4">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-bar-chart me-2 text-primary"></i>Activity by Module</h6>
      <?php foreach ($moduleCounts as $m): ?>
      <div class="mb-2">
        <div class="d-flex justify-content-between small mb-1">
          <span><?= h($m['module']) ?></span>
          <span class="fw-bold"><?= $m['cnt'] ?></span>
        </div>
        <div class="progress skill-bar">
          <div class="progress-bar bg-primary" style="width:<?= min(100, $m['cnt'] * 5) ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Log table -->
<div class="card stat-card p-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0"><i class="bi bi-list-ul me-2 text-primary"></i>User Activity Log</h6>
    <input type="text" id="logSearch" class="form-control form-control-sm" placeholder="Search logs…" style="max-width:250px;">
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover" id="logTable">
      <thead class="table-light"><tr><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>Details</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $log): ?>
      <tr>
        <td class="text-muted small text-nowrap"><?= date('M d, H:i', strtotime($log['created_at'])) ?></td>
        <td><?= h($log['full_name'] ?? 'System') ?></td>
        <td class="fw-semibold"><?= h($log['action']) ?></td>
        <td><span class="badge bg-secondary"><?= h($log['module'] ?? '') ?></span></td>
        <td class="text-muted small"><?= h($log['details'] ?? '') ?></td>
        <td class="text-muted small"><?= h($log['ip_address'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($logs)): ?><tr><td colspan="6" class="text-center text-muted py-4">No activity logs yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>initTableSearch('logSearch','logTable');</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

