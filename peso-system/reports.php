<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = db();

// ── Monthly registration trend (12 months) ────────────────────────
$monthlyReg = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') mo,
           DATE_FORMAT(created_at,'%Y-%m') ym,
           COUNT(*) cnt
    FROM applicants
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY ym, mo ORDER BY ym
")->fetchAll();

// ── Monthly summary: registered / referred / placed ───────────────
$monthlySummaryRegs = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) cnt
    FROM applicants
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY ym ORDER BY ym
")->fetchAll(PDO::FETCH_KEY_PAIR);

$monthlySummaryRef = $pdo->query("
    SELECT DATE_FORMAT(referral_date,'%Y-%m') ym, COUNT(*) cnt
    FROM referrals
    WHERE referral_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY ym ORDER BY ym
")->fetchAll(PDO::FETCH_KEY_PAIR);

$monthlySummaryPlaced = $pdo->query("
    SELECT DATE_FORMAT(placement_date,'%Y-%m') ym, COUNT(*) cnt
    FROM placements
    WHERE placement_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY ym ORDER BY ym
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Build unified 12-month timeline
$allMonths = [];
for ($i = 11; $i >= 0; $i--) {
    $allMonths[] = date('Y-m', strtotime("-$i months"));
}
$summaryRows = [];
foreach ($allMonths as $ym) {
    $summaryRows[] = [
        'month'     => date('M Y', strtotime($ym . '-01')),
        'ym'        => $ym,
        'registered'=> (int)($monthlySummaryRegs[$ym]   ?? 0),
        'referred'  => (int)($monthlySummaryRef[$ym]    ?? 0),
        'placed'    => (int)($monthlySummaryPlaced[$ym] ?? 0),
    ];
}

// ── Sector distribution ───────────────────────────────────────────
$sectorDist = $pdo->query("
    SELECT sector, COUNT(*) cnt FROM applicants
    GROUP BY sector ORDER BY cnt DESC
")->fetchAll();

$sectorPlaced = $pdo->query("
    SELECT a.sector, COUNT(p.id) cnt FROM placements p
    JOIN applicants a ON a.id = p.applicant_id
    WHERE p.employer_confirmation = 'Hired'
    GROUP BY a.sector ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Marginalized sector stats (exclude 'None')
$marginalizedStats = $pdo->query("
    SELECT a.sector,
           COUNT(a.id)   total,
           SUM(a.status='placed') placed,
           SUM(a.status='active') active
    FROM applicants a
    WHERE a.sector != 'None'
    GROUP BY a.sector ORDER BY total DESC
")->fetchAll();

// ── Age group distribution ────────────────────────────────────────
$ageGroups = $pdo->query("
    SELECT CASE
        WHEN age BETWEEN 15 AND 24 THEN '15-24'
        WHEN age BETWEEN 25 AND 34 THEN '25-34'
        WHEN age BETWEEN 35 AND 44 THEN '35-44'
        WHEN age BETWEEN 45 AND 54 THEN '45-54'
        ELSE '55+'
      END AS age_group, COUNT(*) cnt
    FROM applicants WHERE age IS NOT NULL
    GROUP BY age_group ORDER BY age_group
")->fetchAll();

// ── Education level ───────────────────────────────────────────────
$eduData = $pdo->query("
    SELECT education_level, COUNT(*) cnt FROM applicants
    GROUP BY education_level
    ORDER BY FIELD(education_level,'Elementary','High School','Vocational','College','Post-Graduate')
")->fetchAll();

// ── Top courses ───────────────────────────────────────────────────
$courses = $pdo->query("
    SELECT course, COUNT(*) cnt FROM applicants
    WHERE course IS NOT NULL AND course != ''
    GROUP BY course ORDER BY cnt DESC LIMIT 10
")->fetchAll();

// ── Top skills ────────────────────────────────────────────────────
$topSkills = $pdo->query("
    SELECT skill, COUNT(*) cnt FROM applicant_skills
    GROUP BY skill ORDER BY cnt DESC LIMIT 15
")->fetchAll();

// ── Referral-to-placement conversion ─────────────────────────────
$totalReferrals    = $pdo->query("SELECT COUNT(*) FROM referrals")->fetchColumn();
$confirmedHired    = $pdo->query("SELECT COUNT(*) FROM placements WHERE employer_confirmation='Hired'")->fetchColumn();
$conversionRate    = $totalReferrals > 0 ? round($confirmedHired / $totalReferrals * 100, 1) : 0;

$referralOutcomes  = $pdo->query("
    SELECT outcome, COUNT(*) cnt FROM referrals GROUP BY outcome ORDER BY cnt DESC
")->fetchAll();

// ── In-demand jobs (from job_vacancies) ──────────────────────────
$inDemandJobs = $pdo->query("
    SELECT jv.job_title, COUNT(r.id) match_count, jv.slots
    FROM job_vacancies jv
    LEFT JOIN recommendations r ON r.job_id = jv.id
    WHERE jv.status = 'active'
    GROUP BY jv.id ORDER BY match_count DESC LIMIT 10
")->fetchAll();

$pageTitle = 'Reports & Statistics — PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Detailed Reports &amp; Statistics</h4>
  <div class="d-flex gap-2 no-print">
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print / Save PDF
    </button>
    <button class="btn btn-outline-success btn-sm" onclick="exportAllTables()">
      <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
    </button>
  </div>
</div>

<!-- ── SECTION 1: Monthly Registration Trend ── -->
<div class="card stat-card p-3 mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Monthly Applicant Registrations (Last 12 Months)</h6>
    <button class="btn btn-outline-secondary btn-sm no-print" onclick="exportTableExcel('monthlySummaryTable','Monthly_Summary')">
      <i class="bi bi-download me-1"></i>Export
    </button>
  </div>
  <canvas id="monthlyRegChart" height="80" class="mb-4"></canvas>
  <div class="table-responsive">
    <table class="table table-sm table-hover" id="monthlySummaryTable">
      <thead class="table-light">
        <tr><th>Month</th><th>Registered</th><th>Referred</th><th>Placed (Confirmed)</th></tr>
      </thead>
      <tbody>
      <?php foreach (array_reverse($summaryRows) as $row): ?>
      <tr>
        <td class="fw-semibold"><?= h($row['month']) ?></td>
        <td><?= number_format($row['registered']) ?></td>
        <td><?= number_format($row['referred']) ?></td>
        <td><?= number_format($row['placed']) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="table-light fw-semibold">
        <td>Total</td>
        <td><?= number_format(array_sum(array_column($summaryRows,'registered'))) ?></td>
        <td><?= number_format(array_sum(array_column($summaryRows,'referred'))) ?></td>
        <td><?= number_format(array_sum(array_column($summaryRows,'placed'))) ?></td>
      </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ── SECTION 2: Referral-to-Placement Conversion ── -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card stat-card p-3 h-100">
      <h6 class="fw-semibold mb-3"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Referral Conversion</h6>
      <div class="text-center py-3">
        <div class="fs-1 fw-bold text-primary"><?= $conversionRate ?>%</div>
        <div class="text-muted small">Referral → Confirmed Hired</div>
      </div>
      <div class="mt-3">
        <div class="d-flex justify-content-between mb-1">
          <small>Total Referrals Issued</small><strong><?= number_format((int)$totalReferrals) ?></strong>
        </div>
        <div class="d-flex justify-content-between">
          <small>Confirmed Hired by Employer</small><strong class="text-success"><?= number_format((int)$confirmedHired) ?></strong>
        </div>
      </div>
      <div class="progress skill-bar mt-3">
        <div class="progress-bar bg-success" style="width:<?= $conversionRate ?>%"></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card p-3 h-100">
      <h6 class="fw-semibold mb-3"><i class="bi bi-pie-chart me-2 text-primary"></i>Referral Outcomes</h6>
      <canvas id="referralOutcomeChart" height="220"></canvas>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card p-3 h-100">
      <h6 class="fw-semibold mb-3"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Most In-Demand Jobs</h6>
      <?php foreach ($inDemandJobs as $job): ?>
      <div class="mb-2">
        <div class="d-flex justify-content-between small">
          <span class="text-truncate" style="max-width:160px"><?= h($job['job_title']) ?></span>
          <span class="fw-semibold"><?= $job['match_count'] ?> matches</span>
        </div>
        <div class="progress skill-bar">
          <div class="progress-bar bg-primary" style="width:<?= $inDemandJobs[0]['match_count'] > 0 ? round($job['match_count']/$inDemandJobs[0]['match_count']*100) : 0 ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($inDemandJobs)): ?><p class="text-muted small">No match data yet.</p><?php endif; ?>
    </div>
  </div>
</div>

<!-- ── SECTION 3: Marginalized Sector Statistics ── -->
<div class="card stat-card p-3 mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0"><i class="bi bi-people me-2 text-primary"></i>Marginalized Sector Statistics</h6>
    <button class="btn btn-outline-secondary btn-sm no-print" onclick="exportTableExcel('sectorTable','Sector_Statistics')">
      <i class="bi bi-download me-1"></i>Export
    </button>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-md-5">
      <canvas id="sectorChart" height="240"></canvas>
    </div>
    <div class="col-md-7">
      <div class="table-responsive">
        <table class="table table-sm table-hover" id="sectorTable">
          <thead class="table-light">
            <tr><th>Sector</th><th>Registered</th><th>Active</th><th>Placed</th><th>Placement Rate</th></tr>
          </thead>
          <tbody>
          <?php if ($marginalizedStats): ?>
          <?php foreach ($marginalizedStats as $row):
            $rate = $row['total'] > 0 ? round($row['placed']/$row['total']*100,1) : 0;
          ?>
          <tr>
            <td><?= sectorBadge($row['sector']) ?></td>
            <td><?= number_format($row['total']) ?></td>
            <td><?= number_format($row['active']) ?></td>
            <td><?= number_format($row['placed']) ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <span class="fw-semibold"><?= $rate ?>%</span>
                <div class="progress skill-bar flex-grow-1"><div class="progress-bar bg-success" style="width:<?= $rate ?>%"></div></div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php else: ?>
          <tr><td colspan="5" class="text-center text-muted py-3">No marginalized sector data yet. Set sector classification when adding applicants.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ── SECTION 4: Age & Education ── -->
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

<!-- ── SECTION 5: Courses & Skills ── -->
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
        <?php if (empty($topSkills)): ?><p class="text-muted">No skills data yet.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── SECTION 6: Education Summary Table ── -->
<div class="card stat-card p-3 mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0"><i class="bi bi-table me-2 text-primary"></i>Education Level Summary</h6>
    <button class="btn btn-outline-secondary btn-sm no-print" onclick="exportTableExcel('eduTable','Education_Summary')">
      <i class="bi bi-download me-1"></i>Export
    </button>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover" id="eduTable">
      <thead class="table-light"><tr><th>Education Level</th><th>Count</th><th>Percentage</th><th>Distribution</th></tr></thead>
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
const colors6 = ['#0d47a1','#1565c0','#1976d2','#1e88e5','#42a5f5','#90caf9','#bbdefb','#e3f2fd'];

// Monthly registration trend
new Chart(document.getElementById('monthlyRegChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($monthlyReg,'mo')) ?>,
    datasets: [
      { label: 'Registered', data: <?= json_encode(array_column($monthlyReg,'cnt')) ?>, borderColor: '#0d47a1', backgroundColor: 'rgba(13,71,161,.1)', fill: true, tension: 0.4, pointRadius: 5 }
    ]
  },
  options: { plugins: { legend: { display: true, position: 'top' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Referral outcomes doughnut
new Chart(document.getElementById('referralOutcomeChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($referralOutcomes,'outcome')) ?>,
    datasets: [{ data: <?= json_encode(array_column($referralOutcomes,'cnt')) ?>,
      backgroundColor: ['#ffc107','#198754','#dc3545','#0dcaf0','#fd7e14'] }]
  },
  options: { plugins: { legend: { position: 'bottom' } } }
});

// Sector doughnut
new Chart(document.getElementById('sectorChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($sectorDist,'sector')) ?>,
    datasets: [{ data: <?= json_encode(array_column($sectorDist,'cnt')) ?>, backgroundColor: colors6 }]
  },
  options: { plugins: { legend: { position: 'bottom' } } }
});

// Age bar
new Chart(document.getElementById('ageChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($ageGroups,'age_group')) ?>,
    datasets: [{ label: 'Applicants', data: <?= json_encode(array_column($ageGroups,'cnt')) ?>, backgroundColor: colors6, borderRadius: 6 }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

// Education doughnut
new Chart(document.getElementById('eduChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($eduData,'education_level')) ?>,
    datasets: [{ data: <?= json_encode(array_column($eduData,'cnt')) ?>, backgroundColor: colors6 }]
  },
  options: { plugins: { legend: { position: 'bottom' } } }
});

// Top courses
new Chart(document.getElementById('courseChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($courses,'course')) ?>,
    datasets: [{ label: 'Applicants', data: <?= json_encode(array_column($courses,'cnt')) ?>, backgroundColor: '#1976d2', borderRadius: 6 }]
  },
  options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});

function exportTableExcel(tableId, sheetName) {
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(document.getElementById(tableId), {raw: true});
    XLSX.utils.book_append_sheet(wb, ws, sheetName);
    XLSX.writeFile(wb, sheetName + '_' + new Date().toISOString().slice(0,10) + '.xlsx');
}

function exportAllTables() {
    const wb = XLSX.utils.book_new();
    const tables = [
        ['monthlySummaryTable', 'Monthly_Summary'],
        ['sectorTable',         'Sector_Statistics'],
        ['eduTable',            'Education_Summary'],
    ];
    tables.forEach(([id, name]) => {
        const el = document.getElementById(id);
        if (el) {
            const ws = XLSX.utils.table_to_sheet(el, {raw: true});
            XLSX.utils.book_append_sheet(wb, ws, name);
        }
    });
    XLSX.writeFile(wb, 'PESO_Reports_' + new Date().toISOString().slice(0,10) + '.xlsx');
}
</script>

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
