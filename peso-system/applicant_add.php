<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_POST;
    $required = ['first_name','last_name','education_level'];
    foreach ($required as $r) {
        if (empty(trim($f[$r] ?? ''))) $errors[] = "Field '$r' is required.";
    }

    if (empty($errors)) {
        $pdo = db();
        $stmt = $pdo->prepare("
            INSERT INTO applicants
              (first_name, last_name, email, phone, age, sex, civil_status,
               address, barangay, education_level, course, school, year_graduated,
               years_experience, preferred_position, status, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            trim($f['first_name']), trim($f['last_name']),
            trim($f['email'] ?? ''), trim($f['phone'] ?? ''),
            (int)($f['age'] ?? 0), $f['sex'] ?? null, $f['civil_status'] ?? null,
            trim($f['address'] ?? ''), trim($f['barangay'] ?? ''),
            $f['education_level'], trim($f['course'] ?? ''),
            trim($f['school'] ?? ''), $f['year_graduated'] ?: null,
            (float)($f['years_experience'] ?? 0),
            trim($f['preferred_position'] ?? ''), 'active',
            $_SESSION['user_id'],
        ]);
        $applicantId = (int)$pdo->lastInsertId();

        // Skills
        $skills = array_filter(array_map('trim', explode(',', $f['skills'] ?? '')));
        $skillStmt = $pdo->prepare('INSERT INTO applicant_skills (applicant_id, skill, skill_type) VALUES (?,?,?)');
        foreach ($skills as $skill) {
            $skillStmt->execute([$applicantId, $skill, $f['skill_type'] ?? 'Technical']);
        }

        logActivity('Add Applicant', 'Applicants', "Added: {$f['first_name']} {$f['last_name']}");
        header('Location: /peso-system/applicants.php?added=1');
        exit;
    }
}

$pageTitle = 'Add Applicant — PESO CSJDM DSS';
$eduLevels = ['Elementary','High School','Vocational','College','Post-Graduate'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-person-plus me-2 text-primary"></i>Add Applicant</h4>
  <a href="/peso-system/applicants.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card stat-card p-4">
<form method="POST">

  <h6 class="fw-semibold text-primary mb-3">Personal Information</h6>
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <label class="form-label">First Name <span class="text-danger">*</span></label>
      <input type="text" name="first_name" class="form-control" value="<?= h($_POST['first_name'] ?? '') ?>" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Last Name <span class="text-danger">*</span></label>
      <input type="text" name="last_name" class="form-control" value="<?= h($_POST['last_name'] ?? '') ?>" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?= h($_POST['email'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Phone</label>
      <input type="text" name="phone" class="form-control" value="<?= h($_POST['phone'] ?? '') ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Age</label>
      <input type="number" name="age" class="form-control" min="15" max="99" value="<?= h($_POST['age'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Sex</label>
      <select name="sex" class="form-select">
        <option value="">— Select —</option>
        <?php foreach (['Male','Female'] as $s): ?>
        <option value="<?= $s ?>" <?= (($_POST['sex'] ?? '') === $s) ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Civil Status</label>
      <select name="civil_status" class="form-select">
        <option value="">— Select —</option>
        <?php foreach (['Single','Married','Widowed','Separated'] as $cs): ?>
        <option value="<?= $cs ?>" <?= (($_POST['civil_status'] ?? '') === $cs) ? 'selected' : '' ?>><?= $cs ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Address</label>
      <input type="text" name="address" class="form-control" value="<?= h($_POST['address'] ?? '') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Barangay</label>
      <input type="text" name="barangay" class="form-control" value="<?= h($_POST['barangay'] ?? '') ?>">
    </div>
  </div>

  <h6 class="fw-semibold text-primary mb-3">Education & Experience</h6>
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <label class="form-label">Education Level <span class="text-danger">*</span></label>
      <select name="education_level" class="form-select" required>
        <option value="">— Select —</option>
        <?php foreach ($eduLevels as $el): ?>
        <option value="<?= $el ?>" <?= (($_POST['education_level'] ?? '') === $el) ? 'selected' : '' ?>><?= $el ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Course / Degree</label>
      <input type="text" name="course" class="form-control" value="<?= h($_POST['course'] ?? '') ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">School / Institution</label>
      <input type="text" name="school" class="form-control" value="<?= h($_POST['school'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Year Graduated</label>
      <input type="number" name="year_graduated" class="form-control" min="1980" max="<?= date('Y') ?>" value="<?= h($_POST['year_graduated'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Years of Experience</label>
      <input type="number" name="years_experience" class="form-control" min="0" max="50" step="0.5" value="<?= h($_POST['years_experience'] ?? '0') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Preferred Position</label>
      <input type="text" name="preferred_position" class="form-control" value="<?= h($_POST['preferred_position'] ?? '') ?>">
    </div>
  </div>

  <h6 class="fw-semibold text-primary mb-3">Skills</h6>
  <div class="row g-3 mb-4">
    <div class="col-md-8">
      <label class="form-label">Skills <small class="text-muted">(comma-separated)</small></label>
      <input type="text" name="skills" class="form-control"
             placeholder="e.g. PHP Programming, Communication, MS Office"
             value="<?= h($_POST['skills'] ?? '') ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Skill Type</label>
      <select name="skill_type" class="form-select">
        <option value="Technical">Technical</option>
        <option value="Interpersonal">Interpersonal</option>
        <option value="Other">Other</option>
      </select>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Applicant</button>
    <a href="/peso-system/applicants.php" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
