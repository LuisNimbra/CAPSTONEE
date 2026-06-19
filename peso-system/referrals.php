<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = db();

// ── Handle outcome update ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'outcome') {
    $rid = (int)($_POST['referral_id'] ?? 0);
    if ($rid) {
        $outcome = $_POST['outcome'] ?? 'Pending';
        $pdo->prepare("
            UPDATE referrals SET
              outcome      = ?,
              outcome_date = ?,
              notes        = ?
            WHERE id = ?
        ")->execute([
            $outcome,
            $_POST['outcome_date'] ?: null,
            trim($_POST['notes'] ?? ''),
            $rid,
        ]);
        logActivity('Update Referral Outcome', 'Referrals', "Referral ID: $rid — $outcome");
    }
    header('Location: /referrals.php?updated=1');
    exit;
}

// ── Filters ───────────────────────────────────────────────────────
$outcomeFilter = $_GET['outcome'] ?? '';
$where  = '1=1';
$params = [];
if (in_array($outcomeFilter, ['Pending','Hired','Declined','No-Show','Withdrew'])) {
    $where .= ' AND r.outcome = ?';
    $params[] = $outcomeFilter;
}

// ── Queries ───────────────────────────────────────────────────────
$summary = $pdo->query("
    SELECT outcome, COUNT(*) cnt FROM referrals GROUP BY outcome ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_KEY_PAIR);

$totalRef    = array_sum($summary);
$pendingRef  = $summary['Pending'] ?? 0;
$hiredRef    = $summary['Hired']   ?? 0;
$declinedRef = ($summary['Declined'] ?? 0) + ($summary['No-Show'] ?? 0) + ($summary['Withdrew'] ?? 0);
$convRate    = $totalRef > 0 ? round($hiredRef / $totalRef * 100, 1) : 0;

// Monthly referral trend
$monthlyTrend = $pdo->query("
    SELECT DATE_FORMAT(referral_date,'%b %Y') mo,
           DATE_FORMAT(referral_date,'%Y-%m') ym,
           SUM(outcome='Pending') pending,
           SUM(outcome='Hired')   hired,
           SUM(outcome='Declined') + SUM(outcome='No-Show') + SUM(outcome='Withdrew') negative,
           COUNT(*) total
    FROM referrals
    WHERE referral_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym, mo ORDER BY ym
")->fetchAll();

$referrals = $pdo->prepare("
    SELECT r.*,
           a.first_name, a.last_name, a.barangay, a.sector,
           jv.job_title, jv.company
    FROM referrals r
    JOIN applicants a ON a.id = r.applicant_id
    JOIN job_vacancies jv ON jv.id = r.job_id
    WHERE $where
    ORDER BY r.created_at DESC
");
$referrals->execute($params);
$list = $referrals->fetchAll();

$pageTitle = 'Referral Tracking — PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-send me-2 text-primary"></i>Referral Outcome Tracking</h4>
  <div class="d-flex gap-2 no-print">
    <button class="btn btn-outline-success btn-sm" onclick="exportTableExcel('referralsTable','Referrals')">
      <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
    </button>
    <a href="/matching.php" class="btn btn-primary btn-sm">
      <i class="bi bi-cpu me-1"></i>Go to Job Matching
    </a>
  </div>
</div>

<?php if (isset($_GET['updated'])): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>Referral outcome updated. <button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="row g-2 mb-4">
  <?php foreach ([
    ['Total Referrals',   $totalRef,    '',          'secondary'],
    ['Pending Outcome',   $pendingRef,  'Pending',   'warning'],
    ['Confirmed Hired',   $hiredRef,    'Hired',     'success'],
    ['Declined / No-Show',$declinedRef, 'Declined',  'danger'],
  ] as [$label, $val, $filter, $color]): ?>
  <div class="col-6 col-md-3">
    <a href="<?= $filter ? '?outcome='.urlencode($filter) : '/referrals.php' ?>" class="text-decoration-none">
      <div class="card stat-card p-3 border-<?= $color ?> <?= $outcomeFilter === $filter ? 'border-2' : '' ?>">
        <div class="text-<?= $color ?> fw-bold fs-5"><?= number_format((int)$val) ?></div>
        <small class="text-muted"><?= $label ?></small>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Conversion rate + trend chart -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card stat-card p-3 h-100 text-center">
      <h6 class="fw-semibold mb-3 text-primary"><i class="bi bi-arrow-repeat me-2"></i>Conversion Rate</h6>
      <div class="fs-1 fw-bold text-<?= $convRate >= 50 ? 'success' : ($convRate >= 25 ? 'warning' : 'danger') ?>">
        <?= $convRate ?>%
      </div>
      <div class="text-muted small mt-1">Referred → Hired</div>
      <div class="progress skill-bar mt-3">
        <div class="progress-bar bg-<?= $convRate >= 50 ? 'success' : ($convRate >= 25 ? 'warning' : 'danger') ?>"
             style="width:<?= $convRate ?>%"></div>
      </div>
      <?php if ($totalRef === 0): ?>
      <p class="text-muted small mt-3">No referrals yet. Use <a href="/matching.php">Job Matching</a> to refer applicants.</p>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-md-9">
    <div class="card stat-card p-3 h-100">
      <h6 class="fw-semibold mb-3"><i class="bi bi-graph-up me-2 text-primary"></i>Monthly Referral Trend (Last 6 Months)</h6>
      <?php if ($monthlyTrend): ?>
      <canvas id="trendChart" height="110"></canvas>
      <?php else: ?>
      <p class="text-muted">No referral data yet.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Referrals Table -->
<div class="card stat-card p-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0">
      <i class="bi bi-list-check me-2 text-primary"></i>All Referrals
      <?php if ($outcomeFilter): ?><span class="badge bg-secondary ms-2"><?= h($outcomeFilter) ?></span><?php endif; ?>
    </h6>
    <?php if ($outcomeFilter): ?>
    <a href="/referrals.php" class="btn btn-sm btn-outline-secondary no-print"><i class="bi bi-x-lg me-1"></i>Clear filter</a>
    <?php endif; ?>
  </div>

  <?php if (empty($list)): ?>
  <div class="text-center text-muted py-5">
    <i class="bi bi-send fs-1 d-block mb-2 opacity-25"></i>
    No referrals found. When you click <strong>Refer</strong> on the
    <a href="/matching.php">Job Matching</a> page, referrals are logged here for outcome tracking.
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle table-sm" id="referralsTable">
      <thead class="table-light">
        <tr>
          <th>Applicant</th><th>Sector</th><th>Job Title</th><th>Company</th>
          <th>Date Referred</th><th>Outcome</th><th>Outcome Date</th><th>Notes</th>
          <th class="no-print">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($list as $ref):
        $outcomeColors = ['Pending'=>'warning','Hired'=>'success','Declined'=>'danger','No-Show'=>'info','Withdrew'=>'secondary'];
        $oc = $outcomeColors[$ref['outcome']] ?? 'secondary';
      ?>
      <tr>
        <td>
          <a href="/applicant_view.php?id=<?= $ref['applicant_id'] ?>" class="fw-semibold text-decoration-none">
            <?= h($ref['first_name'] . ' ' . $ref['last_name']) ?>
          </a>
          <?php if ($ref['barangay']): ?>
          <div class="text-muted small"><?= h($ref['barangay']) ?></div>
          <?php endif; ?>
        </td>
        <td><?= sectorBadge($ref['sector'] ?? 'None') ?></td>
        <td class="fw-semibold"><?= h($ref['job_title']) ?></td>
        <td><?= h($ref['company']) ?></td>
        <td><?= date('M d, Y', strtotime($ref['referral_date'])) ?></td>
        <td><span class="badge bg-<?= $oc ?>"><?= h($ref['outcome']) ?></span></td>
        <td><?= $ref['outcome_date'] ? date('M d, Y', strtotime($ref['outcome_date'])) : '<span class="text-muted">—</span>' ?></td>
        <td class="text-muted small" style="max-width:160px"><?= h($ref['notes'] ?? '') ?></td>
        <td class="no-print">
          <button class="btn btn-sm btn-outline-primary"
                  onclick="openOutcomeModal(<?= $ref['id'] ?>, '<?= h($ref['outcome']) ?>',
                    '<?= h($ref['outcome_date'] ?? '') ?>', <?= json_encode($ref['notes'] ?? '') ?>)"
                  title="Update outcome">
            <i class="bi bi-pencil-square"></i>
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Outcome Update Modal -->
<div class="modal fade" id="outcomeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="form" value="outcome">
        <input type="hidden" name="referral_id" id="modalReferralId">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-send me-2"></i>Update Referral Outcome</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Outcome</label>
            <select name="outcome" id="modalOutcome" class="form-select">
              <?php foreach (['Pending','Hired','Declined','No-Show','Withdrew'] as $oc): ?>
              <option value="<?= $oc ?>"><?= $oc ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Date of Outcome</label>
            <input type="date" name="outcome_date" id="modalOutcomeDate" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea name="notes" id="modalNotes" class="form-control" rows="3"
                      placeholder="e.g. Applicant was hired. Start date: April 1. Reported by employer via phone."></textarea>
          </div>
          <div class="alert alert-info small mb-0">
            <i class="bi bi-info-circle me-1"></i>
            When outcome is <strong>Hired</strong>, also record the placement in
            <a href="/placements.php">Placements</a> to keep employer confirmation data in sync.
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Outcome</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openOutcomeModal(id, outcome, date, notes) {
    document.getElementById('modalReferralId').value  = id;
    document.getElementById('modalOutcome').value     = outcome || 'Pending';
    document.getElementById('modalOutcomeDate').value = date    || '';
    document.getElementById('modalNotes').value       = notes   || '';
    new bootstrap.Modal(document.getElementById('outcomeModal')).show();
}

function exportTableExcel(tableId, sheetName) {
    const el = document.getElementById(tableId);
    if (!el) return;
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(el, {raw: true});
    XLSX.utils.book_append_sheet(wb, ws, sheetName);
    XLSX.writeFile(wb, sheetName + '_' + new Date().toISOString().slice(0,10) + '.xlsx');
}

<?php if ($monthlyTrend): ?>
new Chart(document.getElementById('trendChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($monthlyTrend,'mo')) ?>,
    datasets: [
      { label: 'Hired',     data: <?= json_encode(array_column($monthlyTrend,'hired')) ?>,    backgroundColor: '#198754', borderRadius: 4 },
      { label: 'Pending',   data: <?= json_encode(array_column($monthlyTrend,'pending')) ?>,  backgroundColor: '#ffc107', borderRadius: 4 },
      { label: 'Declined',  data: <?= json_encode(array_column($monthlyTrend,'negative')) ?>, backgroundColor: '#dc3545', borderRadius: 4 },
    ]
  },
  options: {
    plugins: { legend: { position: 'bottom' } },
    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } }
  }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
