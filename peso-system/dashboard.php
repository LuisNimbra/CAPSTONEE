<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = db();

$totalApplicants  = $pdo->query('SELECT COUNT(*) FROM applicants WHERE status != "inactive"')->fetchColumn();
$activeApplicants = $pdo->query('SELECT COUNT(*) FROM applicants WHERE status = "active"')->fetchColumn();
$placedApplicants = $pdo->query('SELECT COUNT(*) FROM applicants WHERE status = "placed"')->fetchColumn();
$activeJobs       = $pdo->query('SELECT COUNT(*) FROM job_vacancies WHERE status = "active"')->fetchColumn();
$totalJobs        = $pdo->query('SELECT COUNT(*) FROM job_vacancies')->fetchColumn();
$totalPlacements  = $pdo->query('SELECT COUNT(*) FROM placements')->fetchColumn();
$pendingMatches   = $pdo->query('SELECT COUNT(*) FROM recommendations WHERE status = "pending"')->fetchColumn();

// Monthly placements for chart (last 6 months)
$monthlyData = $pdo->query("
    SELECT DATE_FORMAT(placement_date,'%b %Y') AS month,
           DATE_FORMAT(placement_date,'%Y-%m') AS ym,
           COUNT(*) AS total
    FROM placements
    WHERE placement_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym, month ORDER BY ym
")->fetchAll();

// Top jobs by applications
$topJobs = $pdo->query("
    SELECT jv.job_title, COUNT(r.id) AS matches
    FROM job_vacancies jv
    LEFT JOIN recommendations r ON r.job_id = jv.id
    GROUP BY jv.id ORDER BY matches DESC LIMIT 5
")->fetchAll();

// Recent activity
$recentLogs = $pdo->query("
    SELECT al.action, al.module, al.created_at, u.full_name
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC LIMIT 8
")->fetchAll();

// ML model status
$mlStatus = mlApiGet('/status');

$pageTitle = 'Dashboard — PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h4>
  <span class="text-muted small"><?= date('F d, Y') ?></span>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <?php
  $stats = [
    ['Total Applicants',   $totalApplicants,  'people-fill',        'primary'],
    ['Active Applicants',  $activeApplicants,  'person-check-fill',  'success'],
    ['Placed',             $placedApplicants,  'award-fill',         'warning'],
    ['Active Vacancies',   $activeJobs,        'briefcase-fill',     'info'],
    ['Total Placements',   $totalPlacements,   'check2-circle',      'success'],
    ['Pending Matches',    $pendingMatches,    'cpu-fill',           'secondary'],
  ];
  foreach ($stats as [$label, $val, $icon, $color]): ?>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card stat-card p-3">
      <div class="d-flex align-items-center gap-3">
        <div class="icon-wrap bg-<?= $color ?> bg-opacity-10 text-<?= $color ?>">
          <i class="bi bi-<?= $icon ?>"></i>
        </div>
        <div>
          <div class="fs-4 fw-bold"><?= number_format((int)$val) ?></div>
          <div class="text-muted small"><?= $label ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts row -->
<div class="row g-3 mb-4">
  <div class="col-md-7">
    <div class="card stat-card p-3 h-100">
      <h6 class="fw-semibold mb-3"><i class="bi bi-bar-chart me-2 text-primary"></i>Monthly Placements (Last 6 Months)</h6>
      <canvas id="monthlyChart" height="120"></canvas>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card stat-card p-3 h-100">
      <h6 class="fw-semibold mb-3"><i class="bi bi-pie-chart me-2 text-primary"></i>Top Jobs by Matches</h6>
      <canvas id="jobsChart" height="160"></canvas>
    </div>
  </div>
</div>

<!-- ML Status + Recent Activity -->
<div class="row g-3">
  <div class="col-md-4">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-cpu me-2 text-primary"></i>System Status</h6>
      <?php
      $dbOk = true;
      try { db()->query('SELECT 1'); } catch (Throwable) { $dbOk = false; }
      $mlOk = ($mlStatus !== null);
      $items = [
        ['ML Model', $mlOk, $mlOk ? ($mlStatus['model'] ?? 'Active') : 'Offline'],
        ['Database',  $dbOk,  $dbOk ? 'Connected' : 'Error'],
        ['Sync',      true,   'Running'],
      ];
      foreach ($items as [$name, $ok, $detail]): ?>
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small"><?= $name ?></span>
        <span class="badge bg-<?= $ok ? 'success' : 'danger' ?>"><?= h($detail) ?></span>
      </div>
      <?php endforeach; ?>
      <?php if ($mlOk && isset($mlStatus['accuracy'])): ?>
      <hr class="my-2">
      <small class="text-muted">
        Best model: <strong><?= h($mlStatus['model']) ?></strong><br>
        Accuracy: <strong><?= round($mlStatus['accuracy'] * 100, 1) ?>%</strong>
      </small>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Activity</h6>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead><tr><th>Action</th><th>Module</th><th>User</th><th>Time</th></tr></thead>
          <tbody>
          <?php foreach ($recentLogs as $log): ?>
          <tr>
            <td><?= h($log['action']) ?></td>
            <td><span class="badge bg-secondary"><?= h($log['module']) ?></span></td>
            <td><?= h($log['full_name'] ?? 'System') ?></td>
            <td class="text-muted small"><?= timeSince($log['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recentLogs)): ?>
          <tr><td colspan="4" class="text-center text-muted">No activity yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
const monthlyLabels = <?= json_encode(array_column($monthlyData, 'month')) ?>;
const monthlyValues = <?= json_encode(array_column($monthlyData, 'total')) ?>;
const jobLabels     = <?= json_encode(array_column($topJobs, 'job_title')) ?>;
const jobValues     = <?= json_encode(array_column($topJobs, 'matches')) ?>;

new Chart(document.getElementById('monthlyChart'), {
  type: 'bar',
  data: {
    labels: monthlyLabels,
    datasets: [{ label: 'Placements', data: monthlyValues,
      backgroundColor: 'rgba(13,71,161,.7)', borderRadius: 6 }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

new Chart(document.getElementById('jobsChart'), {
  type: 'doughnut',
  data: {
    labels: jobLabels,
    datasets: [{ data: jobValues,
      backgroundColor: ['#0d47a1','#1565c0','#1976d2','#42a5f5','#90caf9'] }]
  },
  options: { plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
