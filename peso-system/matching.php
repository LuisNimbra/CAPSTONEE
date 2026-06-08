<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = db();
$jobs       = $pdo->query("SELECT id, job_title, company FROM job_vacancies WHERE status='active' ORDER BY job_title")->fetchAll();
$applicants = $pdo->query("SELECT id, first_name, last_name, education_level, years_experience FROM applicants WHERE status='active' ORDER BY last_name")->fetchAll();

$selectedJobId = (int)($_GET['job_id'] ?? 0);
$results = [];

if ($selectedJobId) {
    $results = $pdo->prepare("
        SELECT r.*, a.first_name, a.last_name, a.education_level, a.years_experience, a.barangay,
               r.explanation
        FROM recommendations r
        JOIN applicants a ON a.id = r.applicant_id
        WHERE r.job_id = ?
        ORDER BY r.rank_position ASC
    ");
    $results->execute([$selectedJobId]);
    $results = $results->fetchAll();
}

$pageTitle = 'AI Job Matching — PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-cpu me-2 text-primary"></i>AI-Powered Job Matching</h4>
</div>

<!-- Selection interface -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-briefcase me-2 text-primary"></i>Select Job Vacancy</h6>
      <select id="jobSelect" class="form-select mb-3" onchange="updateJobPreview(this.value)">
        <option value="">— Choose a vacancy —</option>
        <?php foreach ($jobs as $job): ?>
        <option value="<?= $job['id'] ?>" <?= ($selectedJobId === $job['id']) ? 'selected' : '' ?>>
          <?= h($job['job_title']) ?> — <?= h($job['company']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <div id="jobPreview" class="border rounded p-3 bg-light" style="min-height:80px;">
        <p class="text-muted mb-0 small">Select a job vacancy to preview its details.</p>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-gear me-2 text-primary"></i>Matching Options</h6>
      <div class="mb-3">
        <label class="form-label small">Top N results to display</label>
        <input type="number" id="topN" class="form-control" value="10" min="1" max="<?= count($applicants) ?>">
      </div>
      <button id="btnGenerate" class="btn btn-success w-100 py-2" onclick="generateMatches()" disabled>
        <i class="bi bi-cpu me-2"></i>Generate Matches
      </button>
      <div id="generateStatus" class="mt-2 text-muted small"></div>
    </div>
  </div>
</div>

<!-- Results -->
<div id="resultsSection" class="<?= $results ? '' : 'd-none' ?>">
  <div class="card stat-card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-semibold mb-0"><i class="bi bi-trophy me-2 text-warning"></i>Ranked Applicants</h6>
      <div id="resultsMeta" class="text-muted small"></div>
    </div>
    <div id="resultsTable">
      <?php if ($results): ?>
      <?= renderResultsTable($results) ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
function renderResultsTable(array $rows): string {
    if (empty($rows)) return '<p class="text-muted">No matches found.</p>';
    $html  = '<div class="table-responsive">';
    $html .= '<table class="table table-hover align-middle">';
    $html .= '<thead class="table-light"><tr><th>Rank</th><th>Applicant</th><th>Education</th><th>Exp.</th><th>Match Score</th><th>Key Factors</th><th>Status</th><th>Action</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $pct    = round($r['match_score'] * 100);
        $cls    = $pct >= 70 ? 'score-high' : ($pct >= 40 ? 'score-mid' : 'score-low');
        $rankN  = (int)$r['rank_position'];
        $rankCls = ['rank-1','rank-2','rank-3'][$rankN - 1] ?? 'rank-n';
        $exp    = json_decode($r['explanation'] ?? '{}', true) ?? [];
        $expHtml = '';
        if (!empty($exp['matched_skills'])) {
            $expHtml .= '<small class="text-success"><i class="bi bi-check-circle me-1"></i>' . implode(', ', array_map('htmlspecialchars', $exp['matched_skills'])) . '</small>';
        }
        if (!empty($exp['missing_skills'])) {
            $expHtml .= '<br><small class="text-danger"><i class="bi bi-x-circle me-1"></i>' . implode(', ', array_map('htmlspecialchars', $exp['missing_skills'])) . '</small>';
        }
        $statusBadge = ['pending'=>'secondary','referred'=>'success','rejected'=>'danger'][$r['status']] ?? 'secondary';
        $referBtn = $r['status'] === 'pending'
            ? '<a href="/peso-system/api/matching.php?action=refer&id=' . $r['id'] . '" class="btn btn-sm btn-success">Refer</a>'
            : '<span class="text-muted small">' . ucfirst($r['status']) . '</span>';

        $html .= "<tr>
            <td><span class='badge {$rankCls} px-2'>#{$rankN}</span></td>
            <td>
              <a href='/peso-system/applicant_view.php?id={$r['applicant_id']}' class='fw-semibold text-decoration-none'>
                " . htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) . "
              </a><br>
              <small class='text-muted'>" . htmlspecialchars($r['barangay'] ?? '') . "</small>
            </td>
            <td>" . htmlspecialchars($r['education_level']) . "</td>
            <td>{$r['years_experience']}yr</td>
            <td>
              <span class='score-badge {$cls}'>{$pct}%</span>
              <div class='progress mt-1 skill-bar'><div class='progress-bar bg-primary' style='width:{$pct}%'></div></div>
            </td>
            <td style='max-width:200px'>{$expHtml}</td>
            <td><span class='badge bg-{$statusBadge}'>" . ucfirst($r['status']) . "</span></td>
            <td>{$referBtn}</td>
          </tr>";
    }
    $html .= '</tbody></table></div>';
    return $html;
}
?>

<script>
const jobData = <?= json_encode(array_column(
    array_map(fn($j) => array_merge($j, ['skills' => getJobSkills($j['id'])]), $jobs),
    null, 'id'
)) ?>;

function updateJobPreview(jobId) {
    const btn = document.getElementById('btnGenerate');
    const preview = document.getElementById('jobPreview');
    if (!jobId) { preview.innerHTML = '<p class="text-muted mb-0 small">Select a job vacancy to preview.</p>'; btn.disabled = true; return; }
    const job = jobData[jobId];
    if (!job) return;
    preview.innerHTML = `
      <strong>${job.job_title}</strong> &mdash; ${job.company}<br>
      <span class="badge bg-secondary me-1">Skills required: ${job.skills ? job.skills.length : 0}</span>
      <div class="mt-2">${job.skills && job.skills.length ? job.skills.map(s => `<span class="badge bg-success bg-opacity-10 text-success me-1">${s}</span>`).join('') : '<span class="text-muted small">None listed</span>'}</div>
    `;
    btn.disabled = false;
}

function generateMatches() {
    const jobId = document.getElementById('jobSelect').value;
    const topN  = document.getElementById('topN').value;
    if (!jobId) { alert('Please select a job vacancy.'); return; }

    document.getElementById('btnGenerate').disabled = true;
    document.getElementById('generateStatus').textContent = 'Running ML model…';

    fetch('/peso-system/api/matching.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'generate', job_id: parseInt(jobId), top_n: parseInt(topN) })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('btnGenerate').disabled = false;
        document.getElementById('generateStatus').textContent = '';
        if (data.success) {
            document.getElementById('resultsSection').classList.remove('d-none');
            document.getElementById('resultsTable').innerHTML = data.html;
            document.getElementById('resultsMeta').textContent =
                `${data.count} applicants ranked • Algorithm: ${data.algorithm} • Accuracy: ${data.accuracy}`;
            showToast('Matches generated successfully!', 'success');
        } else {
            showToast(data.error || 'Failed to generate matches.', 'danger');
        }
    })
    .catch(() => {
        document.getElementById('btnGenerate').disabled = false;
        showToast('Error connecting to ML service. Ensure the Flask API is running.', 'danger');
    });
}

// Pre-select from URL param
const urlJobId = <?= $selectedJobId ?: 'null' ?>;
if (urlJobId) updateJobPreview(urlJobId);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
