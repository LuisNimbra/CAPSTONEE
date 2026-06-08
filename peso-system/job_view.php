<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM job_vacancies WHERE id = ?');
$stmt->execute([$id]);
$j = $stmt->fetch();
if (!$j) { header('Location: /peso-system/jobs.php'); exit; }

$jobSkills = getJobSkills($id);

$matches = $pdo->prepare("
    SELECT r.*, a.first_name, a.last_name, a.education_level, a.years_experience
    FROM recommendations r
    JOIN applicants a ON a.id = r.applicant_id
    WHERE r.job_id = ?
    ORDER BY r.rank_position ASC
    LIMIT 15
");
$matches->execute([$id]);
$matchList = $matches->fetchAll();

$pageTitle = h($j['job_title']) . ' — PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['updated'])): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>Job vacancy updated. <button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="page-header">
  <h4><i class="bi bi-briefcase-fill me-2 text-primary"></i><?= h($j['job_title']) ?></h4>
  <div class="d-flex gap-2">
    <a href="/peso-system/matching.php?job_id=<?= $id ?>" class="btn btn-success btn-sm"><i class="bi bi-cpu me-1"></i>Generate Matches</a>
    <a href="/peso-system/job_edit.php?id=<?= $id ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
    <a href="/peso-system/jobs.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card stat-card p-3 mb-3">
      <h6 class="fw-semibold text-primary mb-3"><i class="bi bi-info-circle me-2"></i>Job Details</h6>
      <?php $sc = ['active'=>'success','filled'=>'warning','inactive'=>'secondary'][$j['status']] ?? 'secondary'; ?>
      <span class="badge bg-<?= $sc ?> mb-3"><?= ucfirst($j['status']) ?></span>
      <?php $details = [
        ['Company',              $j['company']],
        ['Required Education',   $j['required_education'] ?? 'Any'],
        ['Min. Experience',      $j['required_experience'] . ' years'],
        ['Salary Range',         formatSalary($j['salary_min'], $j['salary_max'])],
        ['Available Slots',      $j['slots']],
        ['Posted',               date('M d, Y', strtotime($j['created_at']))],
      ]; foreach ($details as [$label, $val]): ?>
      <div class="mb-2">
        <small class="text-muted"><?= $label ?></small>
        <div class="fw-semibold"><?= h($val) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="card stat-card p-3">
      <h6 class="fw-semibold text-primary mb-3"><i class="bi bi-check2-all me-2"></i>Required Skills</h6>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($jobSkills as $sk): ?>
        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2"><?= h($sk) ?></span>
        <?php endforeach; ?>
        <?php if (empty($jobSkills)): ?><span class="text-muted">No skills specified.</span><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card stat-card p-3 mb-3">
      <h6 class="fw-semibold text-primary mb-2"><i class="bi bi-file-text me-2"></i>Description</h6>
      <p class="mb-3"><?= nl2br(h($j['description'] ?? 'No description provided.')) ?></p>
      <h6 class="fw-semibold text-primary mb-2"><i class="bi bi-list-check me-2"></i>Qualifications</h6>
      <p class="mb-0"><?= nl2br(h($j['qualifications'] ?? 'No qualifications listed.')) ?></p>
    </div>

    <div class="card stat-card p-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-semibold text-primary mb-0"><i class="bi bi-trophy me-2"></i>Ranked Applicants</h6>
        <a href="/peso-system/matching.php?job_id=<?= $id ?>" class="btn btn-sm btn-outline-success">
          <i class="bi bi-cpu me-1"></i>Re-generate
        </a>
      </div>
      <?php if ($matchList): ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover">
          <thead><tr><th>Rank</th><th>Applicant</th><th>Education</th><th>Exp.</th><th>Score</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($matchList as $m):
            $pct = round($m['match_score'] * 100);
            $cls = $pct >= 70 ? 'score-high' : ($pct >= 40 ? 'score-mid' : 'score-low');
            $rankCls = ['rank-1','rank-2','rank-3'][$m['rank_position']-1] ?? 'rank-n';
          ?>
          <tr>
            <td><span class="badge <?= $rankCls ?>">#<?= $m['rank_position'] ?></span></td>
            <td><a href="/peso-system/applicant_view.php?id=<?= $m['applicant_id'] ?>" class="fw-semibold text-decoration-none">
              <?= h($m['first_name'] . ' ' . $m['last_name']) ?>
            </a></td>
            <td><?= h($m['education_level']) ?></td>
            <td><?= $m['years_experience'] ?>yr</td>
            <td><span class="score-badge <?= $cls ?>"><?= $pct ?>%</span></td>
            <td><span class="badge bg-<?= ['pending'=>'secondary','referred'=>'success','rejected'=>'danger'][$m['status']] ?>"><?= ucfirst($m['status']) ?></span></td>
            <td>
              <?php if ($m['status'] === 'pending'): ?>
              <a href="/peso-system/api/matching.php?action=refer&id=<?= $m['id'] ?>" class="btn btn-xs btn-outline-success btn-sm">Refer</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <p class="text-muted mb-0">No matches yet. Click <strong>Generate Matches</strong> to run the ML model.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
