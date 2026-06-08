<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = db();

// Age group distribution
$ageGroups = $pdo->query("
    SELECT
      CASE
        WHEN age BETWEEN 15 AND 24 THEN '15-24'
        WHEN age BETWEEN 25 AND 34 THEN '25-34'
        WHEN age BETWEEN 35 AND 44 THEN '35-44'
        WHEN age BETWEEN 45 AND 54 THEN '45-54'
        ELSE '55+'
      END AS age_group,
      COUNT(*) cnt
    FROM applicants WHERE age IS NOT NULL
    GROUP BY age_group ORDER BY age_group
")->fetchAll();

// Education level
$eduData = $pdo->query("
    SELECT education_level, COUNT(*) cnt
    FROM applicants GROUP BY education_level
    ORDER BY FIELD(education_level,'Elementary','High School','Vocational','College','Post-Graduate')
")->fetchAll();

// Top courses
$courses = $pdo->query("
    SELECT course, COUNT(*) cnt
    FROM applicants WHERE course IS NOT NULL AND course != ''
    GROUP BY course ORDER BY cnt DESC LIMIT 10
")->fetchAll();

// Top skills
$topSkills = $pdo->query("
    SELECT skill, COUNT(*) cnt FROM applicant_skills
    GROUP BY skill ORDER BY cnt DESC LIMIT 12
")->fetchAll();

$pageTitle = 'Reports & Statistics — PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Detailed Reports &amp; Statistics</h4>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-people me-2 text-primary"></i>Age Group Distribution</h6>
      <canvas id="ageChart" height="180"></canvas>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-mortarboard me-2 text-primary"></i>Education Level Distribution</h6>
      <canvas id="eduChart" height="180"></canvas>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-journal-bookmark me-2 text-primary"></i>Top Courses / Degrees</h6>
      <canvas id="courseChart" height="200"></canvas>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-tools me-2 text-primary"></i>Most Common Skills</h6>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($topSkills as $s):
          $size = max(12, min(20, 12 + $s['cnt']));
        ?>
        <span class="badge bg-primary bg-opacity-<?= min(100, $s['cnt'] * 10) ?> text-primary border border-primary border-opacity-25 px-3 py-2"
              style="font-size:<?= $size ?>px;">
          <?= h($s['skill']) ?> <small class="opacity-75">(<?= $s['cnt'] ?>)</small>
        </span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Tabular summary -->
<div class="card stat-card p-3">
  <h6 class="fw-semibold mb-3"><i class="bi bi-table me-2 text-primary"></i>Education Level Summary</h6>
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr><th>Education Level</th><th>Count</th><th>Percentage</th><th>Distribution</th></tr></thead>
      <tbody>
      <?php
      $total = array_sum(array_column($eduData, 'cnt'));
      foreach ($eduData as $row):
        $pct = $total > 0 ? round($row['cnt'] / $total * 100, 1) : 0;
      ?>
      <tr>
        <td class="fw-semibold"><?= h($row['education_level']) ?></td>
        <td><?= $row['cnt'] ?></td>
        <td><?= $pct ?>%</td>
        <td style="width:200px">
          <div class="progress skill-bar">
            <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const colors6 = ['#0d47a1','#1565c0','#1976d2','#1e88e5','#42a5f5','#90caf9'];

new Chart(document.getElementById('ageChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($ageGroups,'age_group')) ?>,
    datasets: [{ label: 'Applicants', data: <?= json_encode(array_column($ageGroups,'cnt')) ?>, backgroundColor: colors6, borderRadius: 6 }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('eduChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($eduData,'education_level')) ?>,
    datasets: [{ data: <?= json_encode(array_column($eduData,'cnt')) ?>, backgroundColor: colors6 }]
  },
  options: { plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('courseChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($courses,'course')) ?>,
    datasets: [{ label: 'Applicants', data: <?= json_encode(array_column($courses,'cnt')) ?>, backgroundColor: '#1976d2', borderRadius: 6 }]
  },
  options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
