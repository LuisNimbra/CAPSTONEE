<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$statusFilter = $_GET['status'] ?? '';
$params = [];
$where  = 'WHERE 1=1';
if (in_array($statusFilter, ['active','placed','inactive'])) {
    $where .= ' AND a.status = ?';
    $params[] = $statusFilter;
}

$applicants = db()->prepare("
    SELECT a.*, COUNT(s.id) AS skill_count
    FROM applicants a
    LEFT JOIN applicant_skills s ON s.applicant_id = a.id
    $where
    GROUP BY a.id
    ORDER BY a.created_at DESC
");
$applicants->execute($params);
$list = $applicants->fetchAll();

$totals = db()->query("SELECT
    COUNT(*) total,
    SUM(status='active') active,
    SUM(status='placed') placed,
    SUM(status='inactive') inactive
    FROM applicants")->fetch();

$pageTitle = 'Applicants — PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-people me-2 text-primary"></i>Applicant Management</h4>
  <a href="/peso-system/applicant_add.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Add Applicant
  </a>
</div>

<!-- Summary cards -->
<div class="row g-2 mb-3">
  <?php foreach ([
    ['Total',    $totals['total'],    '',         'secondary'],
    ['Active',   $totals['active'],   'active',   'success'],
    ['Placed',   $totals['placed'],   'placed',   'warning'],
    ['Inactive', $totals['inactive'], 'inactive', 'danger'],
  ] as [$label, $count, $filter, $color]): ?>
  <div class="col-6 col-md-3">
    <a href="?status=<?= $filter ?>" class="text-decoration-none">
      <div class="card stat-card p-3 border-<?= $color ?> <?= $statusFilter === $filter ? "border-2" : "" ?>">
        <div class="text-<?= $color ?> fw-bold fs-5"><?= number_format((int)$count) ?></div>
        <small class="text-muted"><?= $label ?></small>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="card stat-card p-3">
  <div class="d-flex gap-2 mb-3">
    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search by name, email or skills…" style="max-width:300px;">
    <?php if ($statusFilter): ?>
    <a href="/peso-system/applicants.php" class="btn btn-outline-secondary btn-sm">Clear filter</a>
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle" id="applicantTable">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Name</th><th>Education</th><th>Experience</th>
          <th>Skills</th><th>Preferred Position</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($list as $i => $a): ?>
      <tr>
        <td class="text-muted small"><?= $i + 1 ?></td>
        <td>
          <div class="fw-semibold"><?= h($a['first_name'] . ' ' . $a['last_name']) ?></div>
          <small class="text-muted"><?= h($a['email'] ?? '') ?></small>
        </td>
        <td>
          <div><?= h($a['education_level']) ?></div>
          <small class="text-muted"><?= h($a['course'] ?? '') ?></small>
        </td>
        <td><?= $a['years_experience'] ?> yr<?= $a['years_experience'] != 1 ? 's' : '' ?></td>
        <td><span class="badge bg-primary"><?= $a['skill_count'] ?> skills</span></td>
        <td><?= h($a['preferred_position'] ?? '—') ?></td>
        <td>
          <?php $sc = ['active'=>'success','placed'=>'warning','inactive'=>'secondary'][$a['status']] ?? 'secondary'; ?>
          <span class="badge bg-<?= $sc ?>"><?= ucfirst($a['status']) ?></span>
        </td>
        <td>
          <a href="/peso-system/applicant_view.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
          <a href="/peso-system/applicant_edit.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
          <a href="/peso-system/api/applicants.php?action=delete&id=<?= $a['id'] ?>"
             class="btn btn-sm btn-outline-danger"
             data-confirm="Delete this applicant? This cannot be undone." title="Delete">
            <i class="bi bi-trash"></i>
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($list)): ?>
      <tr><td colspan="8" class="text-center text-muted py-4">No applicants found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>initTableSearch('searchInput','applicantTable');</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
