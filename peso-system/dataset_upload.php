<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo     = db();
$message = '';
$msgType = 'info';

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    $file = $_FILES['dataset'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Upload failed. Please select a valid CSV file.';
        $msgType = 'danger';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $message = 'Only CSV files are accepted. Please save your Excel file as CSV first.';
        $msgType = 'warning';
    } else {
        $destName = date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $destPath = UPLOAD_DIR . $destName;
        move_uploaded_file($file['tmp_name'], $destPath);

        // Parse CSV and import into applicants table
        $handle   = fopen($destPath, 'r');
        $headers  = fgetcsv($handle);
        $headers  = array_map('strtolower', array_map('trim', $headers));
        $count    = 0;
        $errors   = 0;

        $insertStmt = $pdo->prepare("
            INSERT IGNORE INTO applicants
              (first_name, last_name, email, phone, age, sex, civil_status, barangay,
               education_level, course, school, year_graduated, years_experience,
               preferred_position, status, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);
            try {
                $insertStmt->execute([
                    trim($data['first_name'] ?? $data['firstname'] ?? ''),
                    trim($data['last_name']  ?? $data['lastname']  ?? ''),
                    trim($data['email'] ?? ''),
                    trim($data['phone'] ?? $data['contact'] ?? ''),
                    (int)($data['age'] ?? 0),
                    $data['sex'] ?? null,
                    $data['civil_status'] ?? $data['civilstatus'] ?? null,
                    trim($data['barangay'] ?? ''),
                    $data['education_level'] ?? $data['education'] ?? 'High School',
                    trim($data['course'] ?? ''),
                    trim($data['school'] ?? ''),
                    ($data['year_graduated'] ?? $data['yeargradated'] ?? null) ?: null,
                    (float)($data['years_experience'] ?? $data['experience'] ?? 0),
                    trim($data['preferred_position'] ?? $data['position'] ?? ''),
                    'active', $_SESSION['user_id'],
                ]);
                $count++;
            } catch (Throwable) { $errors++; }
        }
        fclose($handle);

        $pdo->prepare("INSERT INTO dataset_uploads (filename, original_name, record_count, uploaded_by, status) VALUES (?,?,?,?,?)")
            ->execute([$destName, $file['name'], $count, $_SESSION['user_id'], 'processed']);

        logActivity('Upload Dataset', 'ML', "Imported $count records from {$file['name']}");
        $message = "Dataset imported: $count records added" . ($errors ? ", $errors skipped." : ".");
        $msgType = 'success';
    }
}

// Handle ML training trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'train') {
    $result = mlApiCall('/train', [
        'db_host' => DB_HOST, 'db_name' => DB_NAME,
        'db_user' => DB_USER, 'db_pass' => DB_PASS,
    ]);
    if ($result && ($result['success'] ?? false)) {
        // Store model info
        $pdo->prepare("UPDATE ml_models SET is_active=0")->execute();
        $pdo->prepare("INSERT INTO ml_models (model_name, accuracy, precision_score, recall_score, f1_score, is_active) VALUES (?,?,?,?,?,1)")
            ->execute([
                $result['model'], $result['accuracy'] ?? 0,
                $result['precision'] ?? 0, $result['recall'] ?? 0, $result['f1'] ?? 0,
            ]);
        logActivity('Train ML Model', 'ML', "Model: {$result['model']}, Accuracy: " . round(($result['accuracy'] ?? 0) * 100, 1) . '%');
        $message = "Model trained successfully! Best algorithm: {$result['model']} (Accuracy: " . round(($result['accuracy'] ?? 0) * 100, 1) . "%)";
        $msgType = 'success';
    } else {
        $message = 'ML training failed. Ensure the Flask API is running at ' . ML_API_URL;
        $msgType = 'danger';
    }
}

// Upload history
$uploads = $pdo->query("
    SELECT du.*, u.full_name
    FROM dataset_uploads du
    LEFT JOIN users u ON u.id = du.uploaded_by
    ORDER BY du.uploaded_at DESC LIMIT 20
")->fetchAll();

// ML model history
$models = $pdo->query("SELECT * FROM ml_models ORDER BY trained_at DESC LIMIT 10")->fetchAll();

$mlStatus = mlApiGet('/status');

$pageTitle = 'Dataset & ML &mdash; PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-cloud-upload me-2 text-primary"></i>Dataset Upload &amp; ML Training</h4>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show">
  <i class="bi bi-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
  <?= h($message) ?> <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <!-- Upload form -->
  <div class="col-md-6">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>Upload Applicant Dataset</h6>
      <p class="text-muted small mb-3">Upload a CSV file exported from Excel. Required columns: <code>first_name, last_name, education_level</code>. Optional: <code>email, phone, age, sex, civil_status, barangay, course, school, year_graduated, years_experience, preferred_position</code>.</p>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload">
        <div class="mb-3">
          <label class="form-label">Select CSV File</label>
          <input type="file" name="dataset" class="form-control" accept=".csv" required>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-upload me-2"></i>Upload &amp; Import
        </button>
      </form>
    </div>
  </div>

  <!-- ML training -->
  <div class="col-md-6">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-cpu me-2 text-primary"></i>Train ML Model</h6>
      <p class="text-muted small mb-3">Trains Na&iuml;ve Bayes, Decision Tree, and K-Nearest Neighbors models using 10-Fold Cross Validation. The best-performing model is saved and used for applicant ranking.</p>

      <?php if ($mlStatus): ?>
      <div class="alert alert-success py-2 mb-3">
        <small><i class=”bi bi-check-circle me-1”></i>Flask API is online &mdash; <strong><?= h($mlStatus['model'] ?? 'No model') ?></strong>
        <?php if (isset($mlStatus['accuracy'])): ?> | Accuracy: <?= round($mlStatus['accuracy'] * 100, 1) ?>%<?php endif; ?></small>
      </div>
      <?php else: ?>
      <div class="alert alert-warning py-2 mb-3">
        <small><i class="bi bi-exclamation-triangle me-1"></i>Flask API is offline. Start it with: <code>start_ml_api.bat</code></small>
      </div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="action" value="train">
        <button type="submit" class="btn btn-success <?= $mlStatus ? '' : 'disabled' ?>">
          <i class="bi bi-play-fill me-2"></i>Train / Retrain Model
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ML model history -->
<?php if ($models): ?>
<div class="card stat-card p-3 mb-3">
  <h6 class="fw-semibold mb-3"><i class="bi bi-bar-chart-steps me-2 text-primary"></i>ML Model Performance History</h6>
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr><th>Model</th><th>Accuracy</th><th>Precision</th><th>Recall</th><th>F1-Score</th><th>Trained</th><th>Active</th></tr></thead>
      <tbody>
      <?php foreach ($models as $m): ?>
      <tr class="<?= $m['is_active'] ? 'table-success' : '' ?>">
        <td class="fw-semibold"><?= h($m['model_name']) ?></td>
        <td><?= round($m['accuracy'] * 100, 1) ?>%</td>
        <td><?= round($m['precision_score'] * 100, 1) ?>%</td>
        <td><?= round($m['recall_score'] * 100, 1) ?>%</td>
        <td><?= round($m['f1_score'] * 100, 1) ?>%</td>
        <td class="text-muted small"><?= date('M d, Y H:i', strtotime($m['trained_at'])) ?></td>
        <td><?= $m['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Archived</span>' ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Upload history -->
<div class="card stat-card p-3">
  <h6 class="fw-semibold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i>Upload History</h6>
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead><tr><th>File</th><th>Records</th><th>Status</th><th>Uploaded By</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($uploads as $u): ?>
      <tr>
        <td><?= h($u['original_name']) ?></td>
        <td><?= $u['record_count'] ?></td>
        <td><span class="badge bg-<?= $u['status'] === 'processed' ? 'success' : 'secondary' ?>"><?= ucfirst($u['status']) ?></span></td>
        <td><?= h($u['full_name'] ?? '&mdash;') ?></td>
        <td class="text-muted small"><?= date('M d, Y H:i', strtotime($u['uploaded_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($uploads)): ?><tr><td colspan="5" class="text-center text-muted">No uploads yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

