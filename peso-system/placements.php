<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = db();

// Top employers
$topEmployers = $pdo->query("SELECT employer_name, COUNT(*) cnt FROM placements GROUP BY employer_name ORDER BY cnt DESC LIMIT 8")->fetchAll();

// Most frequent positions
$topPositions = $pdo->query("SELECT position, COUNT(*) cnt FROM placements GROUP BY position ORDER BY cnt DESC LIMIT 8")->fetchAll();

// Monthly trend (12 months)
$monthly = $pdo->query("
    SELECT DATE_FORMAT(placement_date,'%b %Y') mo, DATE_FORMAT(placement_date,'%Y-%m') ym, COUNT(*) cnt
    FROM placements WHERE placement_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY ym, mo ORDER BY ym
")->fetchAll();

// By transaction type
$txTypes = $pdo->query("SELECT transaction_type, COUNT(*) cnt FROM placements GROUP BY transaction_type ORDER BY cnt DESC")->fetchAll();

// Recent placements list
$recent = $pdo->query("
    SELECT p.*, a.first_name, a.last_name
    FROM placements p JOIN applicants a ON a.id = p.applicant_id
    ORDER BY p.placement_date DESC LIMIT 20
")->fetchAll();

// Record new placement form
$errors  = [];
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'placement') {
    $appId   = (int)($_POST['applicant_id'] ?? 0);
    $jobId   = (int)($_POST['job_id'] ?? 0);
    if (!$appId || !$jobId) {
        $errors[] = 'Applicant and job are required.';
    } else {
        $pdo->prepare("
            INSERT INTO placements (applicant_id, job_id, employer_name, position, placement_date, transaction_type, barangay, created_by)
            VALUES (?,?,?,?,?,?,?,?)
        ")->execute([
            $appId, $jobId,
            trim($_POST['employer_name'] ?? ''), trim($_POST['position'] ?? ''),
            $_POST['placement_date'] ?: date('Y-m-d'),
            $_POST['transaction_type'] ?? 'Referral',
            trim($_POST['barangay'] ?? ''),
            $_SESSION['user_id'],
        ]);
        $pdo->prepare("UPDATE applicants SET status='placed' WHERE id=?")->execute([$appId]);
        logActivity('Record Placement', 'Placements', "Applicant ID: $appId, Job ID: $jobId");
        header('Location: /peso-system/placements.php?placed=1');
        exit;
    }
}

$allApplicants = $pdo->query("SELECT id, first_name, last_name FROM applicants WHERE status='active' ORDER BY last_name")->fetchAll();
$allJobs       = $pdo->query("SELECT id, job_title, company FROM job_vacancies WHERE status='active' ORDER BY job_title")->fetchAll();

$pageTitle = 'Placement Analytics — PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-award me-2 text-primary"></i>Placement Analytics</h4>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#placementModal">
    <i class="bi bi-plus-lg me-1"></i>Record Placement
  </button>
</div>

<?php if (isset($_GET['placed'])): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>Placement recorded. <button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Charts -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-building me-2 text-primary"></i>Top Employers by Placements</h6>
      <canvas id="employerChart" height="200"></canvas>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-pie-chart me-2 text-primary"></i>By Transaction Type</h6>
      <canvas id="txChart" height="200"></canvas>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-8">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-graph-up me-2 text-primary"></i>Monthly Placement Trend</h6>
      <canvas id="trendChart" height="100"></canvas>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-briefcase me-2 text-primary"></i>Most Frequent Positions</h6>
      <?php foreach ($topPositions as $p): ?>
      <div class="mb-2">
        <div class="d-flex justify-content-between small">
          <span><?= h($p['position']) ?></span>
          <span class="fw-semibold"><?= $p['cnt'] ?></span>
        </div>
        <div class="progress skill-bar">
          <div class="progress-bar bg-primary" style="width:<?= min(100, $p['cnt'] * 20) ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Recent placements table -->
<div class="card stat-card p-3">
  <h6 class="fw-semibold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Placements</h6>
  <div class="table-responsive">
    <table class="table table-hover table-sm">
      <thead><tr><th>Applicant</th><th>Position</th><th>Employer</th><th>Type</th><th>Date</th><th>Barangay</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $p): ?>
      <tr>
        <td><?= h($p['first_name'] . ' ' . $p['last_name']) ?></td>
        <td class="fw-semibold"><?= h($p['position']) ?></td>
        <td><?= h($p['employer_name']) ?></td>
        <td><span class="badge bg-info text-dark"><?= h($p['transaction_type']) ?></span></td>
        <td><?= date('M d, Y', strtotime($p['placement_date'])) ?></td>
        <td><?= h($p['barangay'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($recent)): ?><tr><td colspan="6" class="text-center text-muted">No placements yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Record Placement Modal -->
<div class="modal fade" id="placementModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="form" value="placement">
        <div class="modal-header"><h5 class="modal-title">Record Placement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Applicant <span class="text-danger">*</span></label>
            <select name="applicant_id" class="form-select" required>
              <option value="">— Select —</option>
              <?php foreach ($allApplicants as $a): ?>
              <option value="<?= $a['id'] ?>"><?= h($a['last_name'] . ', ' . $a['first_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Job Vacancy <span class="text-danger">*</span></label>
            <select name="job_id" class="form-select" required>
              <option value="">— Select —</option>
              <?php foreach ($allJobs as $j): ?>
              <option value="<?= $j['id'] ?>"><?= h($j['job_title'] . ' — ' . $j['company']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Employer Name</label>
              <input type="text" name="employer_name" class="form-control">
            </div>
            <div class="col-6">
              <label class="form-label">Position</label>
              <input type="text" name="position" class="form-control">
            </div>
            <div class="col-6">
              <label class="form-label">Placement Date</label>
              <input type="date" name="placement_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6">
              <label class="form-label">Transaction Type</label>
              <select name="transaction_type" class="form-select">
                <?php foreach (['Referral','Walk-in','Online','Job Fair'] as $t): ?>
                <option value="<?= $t ?>"><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Barangay</label>
              <input type="text" name="barangay" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Record Placement</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const c6 = ['#0d47a1','#1565c0','#1976d2','#1e88e5','#42a5f5','#90caf9','#bbdefb','#e3f2fd'];

new Chart(document.getElementById('employerChart'), {
  type: 'bar',
  data: { labels: <?= json_encode(array_column($topEmployers,'employer_name')) ?>, datasets: [{ data: <?= json_encode(array_column($topEmployers,'cnt')) ?>, backgroundColor: '#0d47a1', borderRadius: 6 }] },
  options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});

new Chart(document.getElementById('txChart'), {
  type: 'pie',
  data: { labels: <?= json_encode(array_column($txTypes,'transaction_type')) ?>, datasets: [{ data: <?= json_encode(array_column($txTypes,'cnt')) ?>, backgroundColor: c6 }] },
  options: { plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: { labels: <?= json_encode(array_column($monthly,'mo')) ?>, datasets: [{ label: 'Placements', data: <?= json_encode(array_column($monthly,'cnt')) ?>, borderColor: '#0d47a1', backgroundColor: 'rgba(13,71,161,.1)', fill: true, tension: 0.4 }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
