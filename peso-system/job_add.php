<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_POST;
    if (empty(trim($f['job_title'] ?? ''))) $errors[] = 'Job title is required.';
    if (empty(trim($f['company']   ?? ''))) $errors[] = 'Company name is required.';

    if (empty($errors)) {
        $pdo = db();
        $pdo->prepare("
            INSERT INTO job_vacancies
              (job_title, company, description, qualifications, required_education,
               required_experience, salary_min, salary_max, slots, status, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            trim($f['job_title']), trim($f['company']),
            trim($f['description'] ?? ''), trim($f['qualifications'] ?? ''),
            $f['required_education'] ?: null,
            (float)($f['required_experience'] ?? 0),
            $f['salary_min'] !== '' ? (float)$f['salary_min'] : null,
            $f['salary_max'] !== '' ? (float)$f['salary_max'] : null,
            (int)($f['slots'] ?? 1), 'active', $_SESSION['user_id'],
        ]);
        $jobId = (int)$pdo->lastInsertId();

        $skills = array_filter(array_map('trim', explode(',', $f['required_skills'] ?? '')));
        $skillStmt = $pdo->prepare('INSERT INTO job_required_skills (job_id, skill) VALUES (?,?)');
        foreach ($skills as $skill) $skillStmt->execute([$jobId, $skill]);

        logActivity('Add Job', 'Jobs', "Added: {$f['job_title']}");
        header('Location: /jobs.php?added=1');
        exit;
    }
}

$eduLevels = ['Elementary','High School','Vocational','College','Post-Graduate'];
$pageTitle  = 'Add Job Vacancy "” PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-briefcase-fill me-2 text-primary"></i>Add Job Vacancy</h4>
  <a href="/jobs.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
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
      <input type="text" name="job_title" class="form-control" value="<?= h($_POST['job_title'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Company / Employer <span class="text-danger">*</span></label>
      <input type="text" name="company" class="form-control" value="<?= h($_POST['company'] ?? '') ?>" required>
    </div>
    <div class="col-md-12">
      <label class="form-label">Job Description</label>
      <textarea name="description" class="form-control" rows="3"><?= h($_POST['description'] ?? '') ?></textarea>
    </div>
    <div class="col-md-12">
      <label class="form-label">Qualifications</label>
      <textarea name="qualifications" class="form-control" rows="3"><?= h($_POST['qualifications'] ?? '') ?></textarea>
    </div>
  </div>

  <h6 class="fw-semibold text-primary mb-3">Requirements</h6>
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <label class="form-label">Required Education</label>
      <select name="required_education" class="form-select">
        <option value="">"” Any "”</option>
        <?php foreach ($eduLevels as $el): ?>
        <option value="<?= $el ?>" <?= (($_POST['required_education'] ?? '') === $el) ? 'selected' : '' ?>><?= $el ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Min. Experience (years)</label>
      <input type="number" name="required_experience" class="form-control" min="0" max="30" step="0.5" value="<?= h($_POST['required_experience'] ?? '0') ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Number of Slots</label>
      <input type="number" name="slots" class="form-control" min="1" value="<?= h($_POST['slots'] ?? '1') ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Salary Min (â‚±)</label>
      <input type="number" name="salary_min" class="form-control" min="0" value="<?= h($_POST['salary_min'] ?? '') ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Salary Max (â‚±)</label>
      <input type="number" name="salary_max" class="form-control" min="0" value="<?= h($_POST['salary_max'] ?? '') ?>">
    </div>
    <div class="col-md-12">
      <label class="form-label">Required Skills <small class="text-muted">(comma-separated)</small></label>
      <input type="text" name="required_skills" class="form-control"
             placeholder="e.g. Communication, Microsoft Office, Customer Service"
             value="<?= h($_POST['required_skills'] ?? '') ?>">
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Vacancy</button>
    <a href="/jobs.php" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

