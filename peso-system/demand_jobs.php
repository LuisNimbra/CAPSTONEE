<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = db();

// Jobs ranked by total recommendations (application volume)
$demandData = $pdo->query("
    SELECT jv.job_title, jv.company, jv.status,
           COUNT(r.id)            AS application_count,
           AVG(r.match_score)     AS avg_score,
           jv.salary_min, jv.salary_max
    FROM job_vacancies jv
    LEFT JOIN recommendations r ON r.job_id = jv.id
    GROUP BY jv.id
    ORDER BY application_count DESC
    LIMIT 15
")->fetchAll();

// Top 3
$top3 = array_slice($demandData, 0, 3);

// Skills most requested by employers
$requiredSkills = $pdo->query("
    SELECT skill, COUNT(*) cnt FROM job_required_skills
    GROUP BY skill ORDER BY cnt DESC LIMIT 12
")->fetchAll();

$pageTitle = 'Most In-Demand Jobs — PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Most In-Demand Jobs</h4>
</div>

<!-- Top 3 highlight cards -->
<div class="row g-3 mb-4">
  <?php
  $medals = ['rank-1','rank-2','rank-3'];
  $icons  = ['trophy-fill','trophy','award'];
  foreach ($top3 as $i => $job):
    $avgPct = $job['avg_score'] ? round($job['avg_score'] * 100, 1) : 0;
  ?>
  <div class="col-md-4">
    <div class="card stat-card p-3 border-2 <?= $i === 0 ? 'border-warning' : '' ?>">
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="badge <?= $medals[$i] ?> fs-6 px-3">#<?= $i + 1 ?></span>
        <i class="bi bi-<?= $icons[$i] ?> text-warning fs-5"></i>
      </div>
      <h6 class="fw-bold mb-1"><?= h($job['job_title']) ?></h6>
      <small class="text-muted d-block mb-2"><?= h($job['company']) ?></small>
      <div class="d-flex justify-content-between">
        <span><i class="bi bi-people me-1 text-primary"></i><?= $job['application_count'] ?> applications</span>
        <span class="score-badge <?= $avgPct >= 70 ? 'score-high' : 'score-mid' ?>"><?= $avgPct ?>% avg match</span>
      </div>
      <div class="mt-2 small text-muted"><?= formatSalary($job['salary_min'], $job['salary_max']) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-7">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-bar-chart me-2 text-primary"></i>All Jobs by Application Volume</h6>
      <canvas id="demandChart" height="200"></canvas>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-briefcase me-2 text-primary"></i>Most Requested Skills (Employers)</h6>
      <canvas id="reqSkillsChart" height="200"></canvas>
    </div>
  </div>
</div>

<!-- Full table -->
<div class="card stat-card p-3">
  <h6 class="fw-semibold mb-3"><i class="bi bi-table me-2 text-primary"></i>In-Demand Jobs — Full List</h6>
  <div class="table-responsive">
    <table class="table table-hover table-sm align-middle">
      <thead class="table-light"><tr><th>Rank</th><th>Job Title</th><th>Company</th><th>Applications</th><th>Avg Match Score</th><th>Salary</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($demandData as $i => $job):
        $pct    = $job['avg_score'] ? round($job['avg_score'] * 100, 1) : 0;
        $rankCls = ['rank-1','rank-2','rank-3'][$i] ?? 'rank-n';
        $sc     = ['active'=>'success','filled'=>'warning','inactive'=>'secondary'][$job['status']] ?? 'secondary';
      ?>
      <tr>
        <td><span class="badge <?= $rankCls ?>">#<?= $i + 1 ?></span></td>
        <td class="fw-semibold"><?= h($job['job_title']) ?></td>
        <td><?= h($job['company']) ?></td>
        <td><span class="badge bg-primary"><?= $job['application_count'] ?></span></td>
        <td>
          <?php if ($pct > 0): ?>
          <span class="score-badge <?= $pct >= 70 ? 'score-high' : 'score-mid' ?>"><?= $pct ?>%</span>
          <?php else: ?>
          <span class="text-muted small">—</span>
          <?php endif; ?>
        </td>
        <td><?= formatSalary($job['salary_min'], $job['salary_max']) ?></td>
        <td><span class="badge bg-<?= $sc ?>"><?= ucfirst($job['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($demandData)): ?><tr><td colspan="7" class="text-center text-muted">No data available.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
new Chart(document.getElementById('demandChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($demandData,'job_title')) ?>,
    datasets: [{ label: 'Applications', data: <?= json_encode(array_column($demandData,'application_count')) ?>, backgroundColor: '#0d47a1', borderRadius: 6 }]
  },
  options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});

new Chart(document.getElementById('reqSkillsChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($requiredSkills,'skill')) ?>,
    datasets: [{ data: <?= json_encode(array_column($requiredSkills,'cnt')) ?>, backgroundColor: '#1976d2', borderRadius: 6 }]
  },
  options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
