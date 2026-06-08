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

$existingSkills = implode(', ', getJobSkills($id));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_POST;
    if (empty(trim($f['job_title'] ?? ''))) $errors[] = 'Job title is required.';
    if (empty(trim($f['company']   ?? ''))) $errors[] = 'Company name is required.';

    if (empty($errors)) {
        $pdo->prepare("
            UPDATE job_vacancies SET
              job_title=?, company=?, description=?, qualifications=?,
              required_education=?, required_experience=?,
              salary_min=?, salary_max=?, slots=?, status=?
            WHERE id=?
        ")->execute([
            trim($f['job_title']), trim($f['company']),
            trim($f['description'] ?? ''), trim($f['qualifications'] ?? ''),
            $f['required_education'] ?: null,
            (float)($f['required_experience'] ?? 0),
            $f['salary_min'] !== '' ? (float)$f['salary_min'] : null,
            $f['salary_max'] !== '' ? (float)$f['salary_max'] : null,
            (int)($f['slots'] ?? 1), $f['status'], $id,
        ]);

        $pdo->prepare('DELETE FROM job_required_skills WHERE job_id = ?')->execute([$id]);
        $skills = array_filter(array_map('trim', explode(',', $f['required_skills'] ?? '')));
        $skillStmt = $pdo->prepare('INSERT INTO job_required_skills (job_id, skill) VALUES (?,?)');
        foreach ($skills as $skill) $skillStmt->execute([$id, $skill]);

        logActivity('Edit Job', 'Jobs', "Edited job ID: $id");
        header('Location: /peso-system/job_view.php?id=' . $id . '&updated=1');
        exit;
    }
    $j = array_merge($j, $f);
}

$eduLevels = ['Elementary','High School','Vocational','College','Post-Graduate'];
$pageTitle  = 'Edit Job Vacancy — PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Job Vacancy</h4>
  <a href="/peso-system/job_view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card stat-card p-4">
<form method="POST">
  <h6 class="fw-semibold text-primary mb-3">Job Information</h6>
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <label class="form-label">Job Title <span class="text-danger">*</span></label>
      <input type="text" name="job_title" class="form-control" value="<?= h($j['job_title']) ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Company / Employer <span class="text-danger">*</span></label>
      <input type="text" name="company" class="form-control" value="<?= h($j['company']) ?>" required>
    </div>
    <div class="col-12">
      <label class="form-label">Job Description</label>
      <textarea name="description" class="form-control" rows="3"><?= h($j['description'] ?? '') ?></textarea>
    </div>
    <div class="col-12">
      <label class="form-label">Qualifications</label>
      <textarea name="qualifications" class="form-control" rows="3"><?= h($j['qualifications'] ?? '') ?></textarea>
    </div>
  </div>

  <h6 class="fw-semibold text-primary mb-3">Requirements</h6>
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <label class="form-label">Required Education</label>
      <select name="required_education" class="form-select">
        <option value="">— Any —</option>
        <?php foreach ($eduLevels as $el): ?>
        <option value="<?= $el ?>" <?= ($j['required_education'] === $el) ? 'selected' : '' ?>><?= $el ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Min. Experience (yrs)</label>
      <input type="number" name="required_experience" class="form-control" min="0" step="0.5" value="<?= h($j['required_experience']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Slots</label>
      <input type="number" name="slots" class="form-control" min="1" value="<?= h($j['slots']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Salary Min</label>
      <input type="number" name="salary_min" class="form-control" value="<?= h($j['salary_min'] ?? '') ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Salary Max</label>
      <input type="number" name="salary_max" class="form-control" value="<?= h($j['salary_max'] ?? '') ?>">
    </div>
    <div class="col-md-9">
      <label class="form-label">Required Skills <small class="text-muted">(comma-separated)</small></label>
      <input type="text" name="required_skills" class="form-control" value="<?= h($existingSkills) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <?php foreach (['active','filled','inactive'] as $st): ?>
        <option value="<?= $st ?>" <?= ($j['status'] === $st) ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Update Vacancy</button>
    <a href="/peso-system/job_view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
