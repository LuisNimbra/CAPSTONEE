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
if (!$a) { header('Location: /applicants.php'); exit; }

$existingSkills = implode(', ', getApplicantSkills($id));
$existingCerts  = getApplicantCertifications($id);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_POST;
    if (empty(trim($f['first_name'] ?? ''))) $errors[] = 'First name is required.';
    if (empty(trim($f['last_name']  ?? ''))) $errors[] = 'Last name is required.';

    if (empty($errors)) {
        $pdo->prepare("
            UPDATE applicants SET
              first_name=?, last_name=?, email=?, phone=?, age=?, sex=?, civil_status=?, sector=?,
              address=?, barangay=?, education_level=?, course=?, school=?,
              year_graduated=?, years_experience=?, preferred_position=?, status=?
            WHERE id=?
        ")->execute([
            trim($f['first_name']), trim($f['last_name']),
            trim($f['email'] ?? ''), trim($f['phone'] ?? ''),
            (int)($f['age'] ?? 0) ?: null, $f['sex'] ?? null, $f['civil_status'] ?? null,
            $f['sector'] ?? 'None',
            trim($f['address'] ?? ''), trim($f['barangay'] ?? ''),
            $f['education_level'], trim($f['course'] ?? ''),
            trim($f['school'] ?? ''), $f['year_graduated'] ?: null,
            (float)($f['years_experience'] ?? 0),
            trim($f['preferred_position'] ?? ''), $f['status'],
            $id,
        ]);

        // Replace skills
        $pdo->prepare('DELETE FROM applicant_skills WHERE applicant_id = ?')->execute([$id]);
        $skills = array_filter(array_map('trim', explode(',', $f['skills'] ?? '')));
        $skillStmt = $pdo->prepare('INSERT INTO applicant_skills (applicant_id, skill, skill_type) VALUES (?,?,?)');
        foreach ($skills as $skill) {
            $skillStmt->execute([$id, $skill, $f['skill_type'] ?? 'Technical']);
        }

        // Replace certifications
        saveCertifications($id, $f);

        logActivity('Edit Applicant', 'Applicants', "Edited ID: $id");
        header('Location: /applicant_view.php?id=' . $id . '&updated=1');
        exit;
    }
    $a = array_merge($a, $f);
}

$eduLevels = ['Elementary','High School','Vocational','College','Post-Graduate'];
$pageTitle = 'Edit Applicant — PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Applicant</h4>
  <a href="/applicant_view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
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
      <input type="text" name="first_name" class="form-control" value="<?= h($a['first_name']) ?>" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Last Name <span class="text-danger">*</span></label>
      <input type="text" name="last_name" class="form-control" value="<?= h($a['last_name']) ?>" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" value="<?= h($a['email'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Phone</label>
      <input type="text" name="phone" class="form-control" value="<?= h($a['phone'] ?? '') ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Age</label>
      <input type="number" name="age" class="form-control" min="15" max="99" value="<?= h($a['age'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Sex</label>
      <select name="sex" class="form-select">
        <option value="">— Select —</option>
        <?php foreach (['Male','Female'] as $s): ?>
        <option value="<?= $s ?>" <?= ($a['sex'] === $s) ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Civil Status</label>
      <select name="civil_status" class="form-select">
        <option value="">— Select —</option>
        <?php foreach (['Single','Married','Widowed','Separated'] as $cs): ?>
        <option value="<?= $cs ?>" <?= ($a['civil_status'] === $cs) ? 'selected' : '' ?>><?= $cs ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Sector / Classification</label>
      <select name="sector" class="form-select">
        <?php foreach (SECTORS as $sec): ?>
        <option value="<?= $sec ?>" <?= (($a['sector'] ?? 'None') === $sec) ? 'selected' : '' ?>><?= $sec ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Address</label>
      <input type="text" name="address" class="form-control" value="<?= h($a['address'] ?? '') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Barangay</label>
      <input type="text" name="barangay" class="form-control" value="<?= h($a['barangay'] ?? '') ?>">
    </div>
  </div>

  <h6 class="fw-semibold text-primary mb-3">Education &amp; Experience</h6>
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <label class="form-label">Education Level <span class="text-danger">*</span></label>
      <select name="education_level" class="form-select" required>
        <?php foreach ($eduLevels as $el): ?>
        <option value="<?= $el ?>" <?= ($a['education_level'] === $el) ? 'selected' : '' ?>><?= $el ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Course / Degree</label>
      <input type="text" name="course" class="form-control" value="<?= h($a['course'] ?? '') ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">School</label>
      <input type="text" name="school" class="form-control" value="<?= h($a['school'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Year Graduated</label>
      <input type="number" name="year_graduated" class="form-control" min="1980" max="<?= date('Y') ?>" value="<?= h($a['year_graduated'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Years of Experience</label>
      <input type="number" name="years_experience" class="form-control" min="0" max="50" step="0.5" value="<?= h($a['years_experience']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Preferred Position</label>
      <input type="text" name="preferred_position" class="form-control" value="<?= h($a['preferred_position'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <?php foreach (['active','placed','inactive'] as $st): ?>
        <option value="<?= $st ?>" <?= ($a['status'] === $st) ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <h6 class="fw-semibold text-primary mb-3">Skills</h6>
  <div class="row g-3 mb-4">
    <div class="col-md-8">
      <label class="form-label">Skills <small class="text-muted">(comma-separated)</small></label>
      <input type="text" name="skills" class="form-control" value="<?= h($existingSkills) ?>">
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

  <h6 class="fw-semibold text-primary mb-2">Certifications &amp; Licenses</h6>
  <p class="text-muted small mb-3">TESDA NC levels, PRC licenses, industry certifications, etc.</p>
  <div id="certRows"></div>
  <button type="button" class="btn btn-outline-secondary btn-sm mb-4" onclick="addCertRow()">
    <i class="bi bi-plus-lg me-1"></i>Add Certification
  </button>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Update Applicant</button>
    <a href="/applicant_view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>
</div>

<script>
const existingCerts = <?= json_encode(array_values($existingCerts)) ?>;

function escH(s) { const d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }
function addCertRow(name='', body='', issued='', expiry='') {
    const div = document.createElement('div');
    div.className = 'row g-2 cert-row mb-2 align-items-center';
    div.innerHTML = `
        <div class="col-md-4">
          <input type="text" name="cert_name[]" class="form-control form-control-sm"
                 placeholder="Certification name (e.g. TESDA NC II, PRC License)" value="${escH(name)}">
        </div>
        <div class="col-md-3">
          <input type="text" name="cert_issuing_body[]" class="form-control form-control-sm"
                 placeholder="Issuing body (e.g. TESDA, PRC)" value="${escH(body)}">
        </div>
        <div class="col-md-2">
          <input type="date" name="cert_date_issued[]" class="form-control form-control-sm"
                 title="Date issued" value="${escH(issued)}">
        </div>
        <div class="col-md-2">
          <input type="date" name="cert_expiry_date[]" class="form-control form-control-sm"
                 title="Expiry date" value="${escH(expiry)}">
        </div>
        <div class="col-md-1">
          <button type="button" class="btn btn-outline-danger btn-sm"
                  onclick="this.closest('.cert-row').remove()" title="Remove">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>`;
    document.getElementById('certRows').appendChild(div);
}

// Pre-populate existing certifications
existingCerts.forEach(c => addCertRow(c.cert_name, c.issuing_body, c.date_issued || '', c.expiry_date || ''));
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
