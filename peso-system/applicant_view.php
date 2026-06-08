<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM applicants WHERE id = ?');
$stmt->execute([$id]);
$a = $stmt->fetch();
if (!$a) { header('Location: /peso-system/applicants.php'); exit; }

$skills = $pdo->prepare('SELECT * FROM applicant_skills WHERE applicant_id = ? ORDER BY skill_type, skill');
$skills->execute([$id]);
$skillList = $skills->fetchAll();

$matches = $pdo->prepare("
    SELECT r.*, jv.job_title, jv.company
    FROM recommendations r
    JOIN job_vacancies jv ON jv.id = r.job_id
    WHERE r.applicant_id = ?
    ORDER BY r.match_score DESC
    LIMIT 10
");
$matches->execute([$id]);
$matchList = $matches->fetchAll();

$pageTitle = h($a['first_name'] . ' ' . $a['last_name']) . ' — PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['updated'])): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>Applicant updated successfully. <button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="page-header">
  <h4><i class="bi bi-person-badge me-2 text-primary"></i><?= h($a['first_name'] . ' ' . $a['last_name']) ?></h4>
  <div class="d-flex gap-2">
    <a href="/peso-system/applicant_edit.php?id=<?= $id ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
    <a href="/peso-system/applicants.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <!-- Profile card -->
  <div class="col-md-4">
    <div class="card stat-card p-3 mb-3">
      <div class="text-center mb-3">
        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:64px;height:64px;font-size:1.6rem;">
          <?= strtoupper(substr($a['first_name'],0,1) . substr($a['last_name'],0,1)) ?>
        </div>
        <h5 class="mb-0"><?= h($a['first_name'] . ' ' . $a['last_name']) ?></h5>
        <?php $sc = ['active'=>'success','placed'=>'warning','inactive'=>'secondary'][$a['status']] ?? 'secondary'; ?>
        <span class="badge bg-<?= $sc ?> mt-1"><?= ucfirst($a['status']) ?></span>
      </div>
      <hr>
      <?php $info = [
        ['bi-envelope','Email',$a['email'] ?? '—'],
        ['bi-telephone','Phone',$a['phone'] ?? '—'],
        ['bi-person','Sex / Civil Status',($a['sex'] ?? '—') . ' / ' . ($a['civil_status'] ?? '—')],
        ['bi-geo-alt','Barangay',$a['barangay'] ?? '—'],
        ['bi-calendar3','Age',$a['age'] ? $a['age'] . ' years old' : '—'],
      ]; foreach ($info as [$icon,$label,$val]): ?>
      <div class="mb-2">
        <div class="text-muted small"><i class="bi <?= $icon ?> me-1"></i><?= $label ?></div>
        <div class="fw-semibold"><?= h($val) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Education -->
    <div class="card stat-card p-3">
      <h6 class="fw-semibold text-primary mb-3"><i class="bi bi-mortarboard me-2"></i>Education</h6>
      <div class="mb-2">
        <small class="text-muted">Level</small>
        <div class="fw-semibold"><?= h($a['education_level']) ?></div>
      </div>
      <div class="mb-2">
        <small class="text-muted">Course / Degree</small>
        <div class="fw-semibold"><?= h($a['course'] ?? '—') ?></div>
      </div>
      <div class="mb-2">
        <small class="text-muted">School</small>
        <div class="fw-semibold"><?= h($a['school'] ?? '—') ?></div>
      </div>
      <div class="mb-2">
        <small class="text-muted">Year Graduated</small>
        <div class="fw-semibold"><?= h($a['year_graduated'] ?? '—') ?></div>
      </div>
      <div>
        <small class="text-muted">Years of Experience</small>
        <div class="fw-semibold"><?= $a['years_experience'] ?> years</div>
      </div>
    </div>
  </div>

  <!-- Skills + Matches -->
  <div class="col-md-8">
    <div class="card stat-card p-3 mb-3">
      <h6 class="fw-semibold text-primary mb-3"><i class="bi bi-tools me-2"></i>Skills Profile</h6>
      <?php
      $grouped = [];
      foreach ($skillList as $s) $grouped[$s['skill_type']][] = $s['skill'];
      foreach ($grouped as $type => $skills):
      ?>
      <div class="mb-3">
        <small class="text-muted fw-semibold d-block mb-2"><?= h($type) ?></small>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($skills as $sk): ?>
          <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2"><?= h($sk) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($skillList)): ?>
      <p class="text-muted mb-0">No skills recorded.</p>
      <?php endif; ?>
    </div>

    <div class="card stat-card p-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-semibold text-primary mb-0"><i class="bi bi-cpu me-2"></i>ML Match Results</h6>
        <a href="/peso-system/matching.php" class="btn btn-sm btn-outline-primary">Generate Match</a>
      </div>
      <?php if ($matchList): ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover">
          <thead><tr><th>Rank</th><th>Job Title</th><th>Company</th><th>Score</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($matchList as $m): ?>
          <tr>
            <td><?php echo "<span class='badge " . (['rank-1','rank-2','rank-3'][$m['rank_position']-1] ?? 'rank-n') . " px-2'>#" . $m['rank_position'] . "</span>"; ?></td>
            <td class="fw-semibold"><?= h($m['job_title']) ?></td>
            <td><?= h($m['company']) ?></td>
            <td>
              <?php $pct = round($m['match_score'] * 100); $cls = $pct >= 70 ? 'score-high' : ($pct >= 40 ? 'score-mid' : 'score-low'); ?>
              <span class="score-badge <?= $cls ?>"><?= $pct ?>%</span>
            </td>
            <td><span class="badge bg-<?= ['pending'=>'secondary','referred'=>'success','rejected'=>'danger'][$m['status']] ?>"><?= ucfirst($m['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <p class="text-muted mb-0">No matches generated yet. Use <a href="/peso-system/matching.php">AI Job Matching</a> to generate.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
