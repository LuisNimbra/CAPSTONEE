<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = db();

// Top skills among placed applicants
$topSkillsPlaced = $pdo->query("
    SELECT s.skill, COUNT(*) cnt
    FROM applicant_skills s
    JOIN applicants a ON a.id = s.applicant_id AND a.status = 'placed'
    GROUP BY s.skill ORDER BY cnt DESC LIMIT 12
")->fetchAll();

// Top skills overall
$topSkillsAll = $pdo->query("
    SELECT skill, skill_type, COUNT(*) cnt
    FROM applicant_skills
    GROUP BY skill, skill_type ORDER BY cnt DESC LIMIT 15
")->fetchAll();

// Work experience distribution
$expDist = $pdo->query("
    SELECT
      CASE
        WHEN years_experience = 0         THEN 'No Experience'
        WHEN years_experience <= 1        THEN '< 1 year'
        WHEN years_experience <= 3        THEN '1-3 years'
        WHEN years_experience <= 5        THEN '3-5 years'
        WHEN years_experience <= 10       THEN '5-10 years'
        ELSE '10+ years'
      END AS exp_range,
      COUNT(*) cnt
    FROM applicants
    GROUP BY exp_range
    ORDER BY MIN(years_experience)
")->fetchAll();

// Preferred vs actual positions
$preferred = $pdo->query("
    SELECT preferred_position pos, COUNT(*) cnt
    FROM applicants WHERE preferred_position IS NOT NULL AND preferred_position != ''
    GROUP BY preferred_position ORDER BY cnt DESC LIMIT 8
")->fetchAll();

$actual = $pdo->query("
    SELECT position pos, COUNT(*) cnt
    FROM placements WHERE position IS NOT NULL
    GROUP BY position ORDER BY cnt DESC LIMIT 8
")->fetchAll();

$pageTitle = 'Skills & Experience Analysis — PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-tools me-2 text-primary"></i>Skills &amp; Experience Analysis</h4>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-star-fill me-2 text-warning"></i>Top Skills Among Placed Applicants</h6>
      <canvas id="placedSkillsChart" height="220"></canvas>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-briefcase me-2 text-primary"></i>Work Experience Distribution</h6>
      <canvas id="expChart" height="220"></canvas>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-heart me-2 text-primary"></i>Preferred Positions</h6>
      <canvas id="preferredChart" height="200"></canvas>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-check2-circle me-2 text-success"></i>Actual Placements by Position</h6>
      <canvas id="actualChart" height="200"></canvas>
    </div>
  </div>
</div>

<!-- Skill inventory table -->
<div class="card stat-card p-3">
  <h6 class="fw-semibold mb-3"><i class="bi bi-table me-2 text-primary"></i>Skill Inventory (All Applicants)</h6>
  <div class="row">
    <?php foreach ($topSkillsAll as $s): ?>
    <div class="col-md-4 mb-2">
      <div class="d-flex justify-content-between align-items-center small mb-1">
        <span><?= h($s['skill']) ?> <span class="badge bg-<?= $s['skill_type'] === 'Technical' ? 'primary' : 'info' ?> bg-opacity-75 ms-1"><?= $s['skill_type'] ?></span></span>
        <span class="fw-bold"><?= $s['cnt'] ?></span>
      </div>
      <div class="progress skill-bar">
        <div class="progress-bar bg-<?= $s['skill_type'] === 'Technical' ? 'primary' : 'info' ?>"
             style="width:<?= min(100, $s['cnt'] * 12) ?>%"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
const c = ['#0d47a1','#1565c0','#1976d2','#1e88e5','#42a5f5','#90caf9','#bbdefb','#e3f2fd','#0277bd','#01579b','#006064','#00838f'];

new Chart(document.getElementById('placedSkillsChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($topSkillsPlaced,'skill')) ?>,
    datasets: [{ data: <?= json_encode(array_column($topSkillsPlaced,'cnt')) ?>, backgroundColor: '#ffd600', borderRadius: 6 }]
  },
  options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});

new Chart(document.getElementById('expChart'), {
  type: 'doughnut',
  data: { labels: <?= json_encode(array_column($expDist,'exp_range')) ?>, datasets: [{ data: <?= json_encode(array_column($expDist,'cnt')) ?>, backgroundColor: c }] },
  options: { plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('preferredChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($preferred,'pos')) ?>,
    datasets: [{ label: 'Preferred', data: <?= json_encode(array_column($preferred,'cnt')) ?>, backgroundColor: '#42a5f5', borderRadius: 6 }]
  },
  options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});

new Chart(document.getElementById('actualChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($actual,'pos')) ?>,
    datasets: [{ label: 'Placed', data: <?= json_encode(array_column($actual,'cnt')) ?>, backgroundColor: '#2e7d32', borderRadius: 6 }]
  },
  options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
