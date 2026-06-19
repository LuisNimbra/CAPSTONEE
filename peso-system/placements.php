<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = db();

// ── Handle new placement ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'placement') {
    $errors = [];
    $appId  = (int)($_POST['applicant_id'] ?? 0);
    $jobId  = (int)($_POST['job_id'] ?? 0);
    if (!$appId || !$jobId) {
        $errors[] = 'Applicant and job are required.';
    } else {
        $pdo->prepare("
            INSERT INTO placements
              (applicant_id, job_id, employer_name, position, placement_date,
               transaction_type, employer_confirmation, barangay, created_by)
            VALUES (?,?,?,?,?,?,?,?,?)
        ")->execute([
            $appId, $jobId,
            trim($_POST['employer_name'] ?? ''), trim($_POST['position'] ?? ''),
            $_POST['placement_date'] ?: date('Y-m-d'),
            $_POST['transaction_type'] ?? 'Referral',
            $_POST['employer_confirmation'] ?? 'Pending',
            trim($_POST['barangay'] ?? ''),
            $_SESSION['user_id'],
        ]);
        $pdo->prepare("UPDATE applicants SET status='placed' WHERE id=?")->execute([$appId]);
        logActivity('Record Placement', 'Placements', "Applicant ID: $appId, Job ID: $jobId");
        header('Location: /placements.php?placed=1');
        exit;
    }
}

// ── Handle employer confirmation update ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'confirm') {
    $pid = (int)($_POST['placement_id'] ?? 0);
    if ($pid) {
        $pdo->prepare("
            UPDATE placements SET
              employer_confirmation = ?,
              employer_report_date  = ?,
              employer_remarks      = ?
            WHERE id = ?
        ")->execute([
            $_POST['employer_confirmation'],
            $_POST['employer_report_date'] ?: null,
            trim($_POST['employer_remarks'] ?? ''),
            $pid,
        ]);
        logActivity('Update Employer Confirmation', 'Placements',
            "Placement ID: $pid — " . $_POST['employer_confirmation']);
    }
    header('Location: /placements.php?confirmed=1');
    exit;
}

// ── Queries ───────────────────────────────────────────────────────
$topEmployers = $pdo->query("SELECT employer_name, COUNT(*) cnt FROM placements GROUP BY employer_name ORDER BY cnt DESC LIMIT 8")->fetchAll();
$topPositions = $pdo->query("SELECT position, COUNT(*) cnt FROM placements GROUP BY position ORDER BY cnt DESC LIMIT 8")->fetchAll();

$monthly = $pdo->query("
    SELECT DATE_FORMAT(placement_date,'%b %Y') mo, DATE_FORMAT(placement_date,'%Y-%m') ym, COUNT(*) cnt
    FROM placements WHERE placement_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY ym, mo ORDER BY ym
")->fetchAll();

$txTypes = $pdo->query("SELECT transaction_type, COUNT(*) cnt FROM placements GROUP BY transaction_type ORDER BY cnt DESC")->fetchAll();

// Confirmation summary
$confirmSummary = $pdo->query("
    SELECT employer_confirmation, COUNT(*) cnt
    FROM placements GROUP BY employer_confirmation ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_KEY_PAIR);

$totalPlacements = array_sum($confirmSummary);
$awaitingCount   = $confirmSummary['Pending'] ?? 0;
$hiredCount      = $confirmSummary['Hired'] ?? 0;

$confirmFilter = $_GET['confirm'] ?? '';
$where = '1=1';
$params = [];
if (in_array($confirmFilter, ['Pending','Hired','Declined','No-Show','Under Evaluation'])) {
    $where .= ' AND p.employer_confirmation = ?';
    $params[] = $confirmFilter;
}

$recent = $pdo->prepare("
    SELECT p.*, a.first_name, a.last_name
    FROM placements p JOIN applicants a ON a.id = p.applicant_id
    WHERE $where
    ORDER BY p.placement_date DESC LIMIT 50
");
$recent->execute($params);
$recent = $recent->fetchAll();

$allApplicants = $pdo->query("SELECT id, first_name, last_name FROM applicants WHERE status='active' ORDER BY last_name")->fetchAll();
$allJobs       = $pdo->query("SELECT id, job_title, company FROM job_vacancies WHERE status='active' ORDER BY job_title")->fetchAll();

$pageTitle = 'Placement Analytics — PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-award me-2 text-primary"></i>Placement Analytics</h4>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-secondary btn-sm no-print" onclick="exportTableExcel('placementsTable','Placements')">
      <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
    </button>
    <button class="btn btn-primary btn-sm no-print" data-bs-toggle="modal" data-bs-target="#placementModal">
      <i class="bi bi-plus-lg me-1"></i>Record Placement
    </button>
  </div>
</div>

<?php if (isset($_GET['placed'])): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>Placement recorded successfully. <button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (isset($_GET['confirmed'])): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>Employer confirmation updated. <button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Confirmation Status Summary -->
<div class="row g-2 mb-3">
  <?php
  $confCards = [
    ['Total Placements',       $totalPlacements, '',                   'secondary'],
    ['Employer Confirmed Hired',$hiredCount,     'Hired',              'success'],
    ['Awaiting Confirmation',  $awaitingCount,   'Pending',            'warning'],
    ['Declined / No-Show',     ($confirmSummary['Declined'] ?? 0) + ($confirmSummary['No-Show'] ?? 0), '', 'danger'],
  ];
  foreach ($confCards as [$label,$val,$filter,$color]):
  ?>
  <div class="col-6 col-md-3">
    <a href="<?= $filter ? '?confirm='.urlencode($filter) : '/placements.php' ?>" class="text-decoration-none">
      <div class="card stat-card p-3 border-<?= $color ?> <?= $confirmFilter === $filter ? 'border-2' : '' ?>">
        <div class="text-<?= $color ?> fw-bold fs-5"><?= number_format((int)$val) ?></div>
        <small class="text-muted"><?= $label ?></small>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

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
      <h6 class="fw-semibold mb-3"><i class="bi bi-patch-check me-2 text-primary"></i>Employer Confirmation Status</h6>
      <canvas id="confirmChart" height="200"></canvas>
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

<!-- Placements table -->
<div class="card stat-card p-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>
      Placements
      <?php if ($confirmFilter): ?><span class="badge bg-secondary ms-2"><?= h($confirmFilter) ?></span><?php endif; ?>
    </h6>
    <?php if ($confirmFilter): ?>
    <a href="/placements.php" class="btn btn-sm btn-outline-secondary no-print">
      <i class="bi bi-x-lg me-1"></i>Clear filter
    </a>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-sm" id="placementsTable">
      <thead class="table-light">
        <tr>
          <th>Applicant</th><th>Position</th><th>Employer</th>
          <th>Type</th><th>Date</th><th>Barangay</th>
          <th>Employer Confirmation</th><th class="no-print">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($recent as $p):
        $confKey = str_replace(' ', '-', $p['employer_confirmation'] ?? 'Pending');
        $confirmLabels = ['Pending'=>'secondary','Hired'=>'success','Declined'=>'danger','No-Show'=>'info','Under Evaluation'=>'warning'];
        $confColor = $confirmLabels[$p['employer_confirmation']] ?? 'secondary';
      ?>
      <tr>
        <td class="fw-semibold"><?= h($p['first_name'] . ' ' . $p['last_name']) ?></td>
        <td><?= h($p['position']) ?></td>
        <td><?= h($p['employer_name']) ?></td>
        <td><span class="badge bg-info text-dark"><?= h($p['transaction_type']) ?></span></td>
        <td><?= $p['placement_date'] ? date('M d, Y', strtotime($p['placement_date'])) : '—' ?></td>
        <td><?= h($p['barangay'] ?? '—') ?></td>
        <td>
          <span class="badge bg-<?= $confColor ?>">
            <?= h($p['employer_confirmation'] ?? 'Pending') ?>
          </span>
          <?php if ($p['employer_report_date']): ?>
          <div class="text-muted" style="font-size:.7rem;"><?= date('M d, Y', strtotime($p['employer_report_date'])) ?></div>
          <?php endif; ?>
        </td>
        <td class="no-print">
          <button class="btn btn-sm btn-outline-primary"
                  onclick="openConfirmModal(<?= $p['id'] ?>, '<?= h($p['employer_confirmation'] ?? 'Pending') ?>',
                                           '<?= h($p['employer_report_date'] ?? '') ?>',
                                           <?= json_encode($p['employer_remarks'] ?? '') ?>)"
                  title="Update employer report">
            <i class="bi bi-pencil-square"></i>
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($recent)): ?><tr><td colspan="8" class="text-center text-muted">No placements found.</td></tr><?php endif; ?>
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
              <?php foreach ($allApplicants as $ap): ?>
              <option value="<?= $ap['id'] ?>"><?= h($ap['last_name'] . ', ' . $ap['first_name']) ?></option>
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
            <div class="col-6">
              <label class="form-label">Employer Confirmation</label>
              <select name="employer_confirmation" class="form-select">
                <?php foreach (['Pending','Hired','Declined','No-Show','Under Evaluation'] as $ec): ?>
                <option value="<?= $ec ?>"><?= $ec ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
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

<!-- Employer Confirmation Update Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="form" value="confirm">
        <input type="hidden" name="placement_id" id="confirmPlacementId">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-building me-2"></i>Update Employer Report</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Employer Confirmation Status</label>
            <select name="employer_confirmation" id="confirmStatus" class="form-select">
              <?php foreach (['Pending','Hired','Declined','No-Show','Under Evaluation'] as $ec): ?>
              <option value="<?= $ec ?>"><?= $ec ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Date of Employer Report</label>
            <input type="date" name="employer_report_date" id="confirmDate" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Remarks / Notes</label>
            <textarea name="employer_remarks" id="confirmRemarks" class="form-control" rows="3"
                      placeholder="e.g. Employer confirmed hiring via phone call on March 15"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Report</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const c8 = ['#0d47a1','#1565c0','#1976d2','#1e88e5','#42a5f5','#90caf9','#bbdefb','#e3f2fd'];
const confirmColors = {'Pending':'#ffc107','Hired':'#198754','Declined':'#dc3545','No-Show':'#0dcaf0','Under Evaluation':'#6c757d'};

new Chart(document.getElementById('employerChart'), {
  type: 'bar',
  data: { labels: <?= json_encode(array_column($topEmployers,'employer_name')) ?>, datasets: [{ data: <?= json_encode(array_column($topEmployers,'cnt')) ?>, backgroundColor: '#0d47a1', borderRadius: 6 }] },
  options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

const confirmLabels = <?= json_encode(array_keys($confirmSummary)) ?>;
const confirmData   = <?= json_encode(array_values($confirmSummary)) ?>;
new Chart(document.getElementById('confirmChart'), {
  type: 'doughnut',
  data: { labels: confirmLabels, datasets: [{ data: confirmData, backgroundColor: confirmLabels.map(l => confirmColors[l] || '#aaa') }] },
  options: { plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: { labels: <?= json_encode(array_column($monthly,'mo')) ?>, datasets: [{ label: 'Placements', data: <?= json_encode(array_column($monthly,'cnt')) ?>, borderColor: '#0d47a1', backgroundColor: 'rgba(13,71,161,.1)', fill: true, tension: 0.4 }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

function openConfirmModal(id, status, date, remarks) {
    document.getElementById('confirmPlacementId').value = id;
    document.getElementById('confirmStatus').value  = status || 'Pending';
    document.getElementById('confirmDate').value    = date   || '';
    document.getElementById('confirmRemarks').value = remarks || '';
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
}

function exportTableExcel(tableId, sheetName) {
    const wb  = XLSX.utils.book_new();
    const ws  = XLSX.utils.table_to_sheet(document.getElementById(tableId), {raw: true});
    XLSX.utils.book_append_sheet(wb, ws, sheetName);
    XLSX.writeFile(wb, sheetName + '_' + new Date().toISOString().slice(0,10) + '.xlsx');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
