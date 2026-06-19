<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = db();

$totals = $pdo->query("SELECT
    (SELECT COUNT(*) FROM applicants) AS applicants,
    (SELECT COUNT(*) FROM job_vacancies) AS jobs,
    (SELECT COUNT(*) FROM placements) AS placements,
    (SELECT COUNT(*) FROM applicants WHERE status='placed') AS placed,
    (SELECT COUNT(*) FROM applicants WHERE status='active')  AS active_app,
    (SELECT COUNT(*) FROM job_vacancies WHERE status='active') AS active_jobs
")->fetch();

$placementRate = $totals['applicants'] > 0
    ? round($totals['placed'] / $totals['applicants'] * 100, 1)
    : 0;

// Sex distribution
$sexData = $pdo->query("SELECT sex, COUNT(*) cnt FROM applicants WHERE sex IS NOT NULL GROUP BY sex")->fetchAll();

// Civil status distribution
$civilData = $pdo->query("SELECT civil_status, COUNT(*) cnt FROM applicants WHERE civil_status IS NOT NULL GROUP BY civil_status")->fetchAll();

// Education level distribution
$eduData = $pdo->query("SELECT education_level, COUNT(*) cnt FROM applicants GROUP BY education_level ORDER BY FIELD(education_level,'Elementary','High School','Vocational','College','Post-Graduate')")->fetchAll();

// Monthly trend (12 months)
$monthly = $pdo->query("
    SELECT DATE_FORMAT(placement_date,'%b %Y') mo, DATE_FORMAT(placement_date,'%Y-%m') ym, COUNT(*) cnt
    FROM placements WHERE placement_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY ym, mo ORDER BY ym
")->fetchAll();

$pageTitle = 'Analytics Dashboard "” PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-bar-chart-line me-2 text-primary"></i>Analytics Dashboard</h4>
</div>

<!-- KPI cards -->
<div class="row g-3 mb-4">
  <?php foreach ([
    ['Total Applicants',  $totals['applicants'],  'people-fill',       'primary'],
    ['Active Applicants', $totals['active_app'],   'person-check-fill', 'success'],
    ['Job Postings',      $totals['jobs'],          'briefcase-fill',    'info'],
    ['Active Vacancies',  $totals['active_jobs'],   'briefcase',         'warning'],
    ['Total Placements',  $totals['placements'],    'award-fill',        'success'],
    ['Placement Rate',    $placementRate . '%',      'graph-up-arrow',    'primary'],
  ] as [$label, $val, $icon, $color]): ?>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card stat-card p-3">
      <div class="icon-wrap bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> mb-2">
        <i class="bi bi-<?= $icon ?>"></i>
      </div>
      <div class="fs-4 fw-bold"><?= $val ?></div>
      <small class="text-muted"><?= $label ?></small>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-gender-ambiguous me-2 text-primary"></i>Sex Distribution</h6>
      <canvas id="sexChart" height="200"></canvas>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-heart me-2 text-primary"></i>Civil Status</h6>
      <canvas id="civilChart" height="200"></canvas>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-mortarboard me-2 text-primary"></i>Education Level</h6>
      <canvas id="eduChart" height="200"></canvas>
    </div>
  </div>
</div>

<div class="card stat-card p-3">
  <h6 class="fw-semibold mb-3"><i class="bi bi-graph-up me-2 text-primary"></i>Monthly Placement Trend (12 months)</h6>
  <canvas id="trendChart" height="80"></canvas>
</div>

<script>
const colors = ['#0d47a1','#1565c0','#1976d2','#42a5f5','#90caf9','#bbdefb'];

new Chart(document.getElementById('sexChart'), {
  type: 'doughnut',
  data: { labels: <?= json_encode(array_column($sexData,'sex')) ?>, datasets: [{ data: <?= json_encode(array_column($sexData,'cnt')) ?>, backgroundColor: colors }] },
  options: { plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('civilChart'), {
  type: 'doughnut',
  data: { labels: <?= json_encode(array_column($civilData,'civil_status')) ?>, datasets: [{ data: <?= json_encode(array_column($civilData,'cnt')) ?>, backgroundColor: colors }] },
  options: { plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('eduChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($eduData,'education_level')) ?>,
    datasets: [{ label: 'Applicants', data: <?= json_encode(array_column($eduData,'cnt')) ?>, backgroundColor: '#0d47a1', borderRadius: 6 }]
  },
  options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});

new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($monthly,'mo')) ?>,
    datasets: [{ label: 'Placements', data: <?= json_encode(array_column($monthly,'cnt')) ?>,
      borderColor: '#0d47a1', backgroundColor: 'rgba(13,71,161,.1)', fill: true, tension: 0.4 }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

