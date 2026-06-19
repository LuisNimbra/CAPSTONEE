<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$statusFilter = $_GET['status'] ?? '';
$params = [];
$where  = 'WHERE 1=1';
if (in_array($statusFilter, ['active','filled','inactive'])) {
    $where .= ' AND jv.status = ?';
    $params[] = $statusFilter;
}

$jobs = db()->prepare("
    SELECT jv.*, COUNT(jrs.id) AS skill_count, COUNT(r.id) AS match_count
    FROM job_vacancies jv
    LEFT JOIN job_required_skills jrs ON jrs.job_id = jv.id
    LEFT JOIN recommendations r ON r.job_id = jv.id
    $where
    GROUP BY jv.id
    ORDER BY jv.created_at DESC
");
$jobs->execute($params);
$list = $jobs->fetchAll();

$totals = db()->query("SELECT COUNT(*) total,
    SUM(status='active') active,
    SUM(status='filled') filled,
    SUM(status='inactive') inactive
    FROM job_vacancies")->fetch();

$pageTitle = 'Job Vacancies "” PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-briefcase me-2 text-primary"></i>Job Vacancy Management</h4>
  <a href="/job_add.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Add Vacancy
  </a>
</div>

<div class="row g-2 mb-3">
  <?php foreach ([
    ['Total',    $totals['total'],    '',         'secondary'],
    ['Active',   $totals['active'],   'active',   'success'],
    ['Filled',   $totals['filled'],   'filled',   'warning'],
    ['Inactive', $totals['inactive'], 'inactive', 'danger'],
  ] as [$label, $count, $filter, $color]): ?>
  <div class="col-6 col-md-3">
    <a href="?status=<?= $filter ?>" class="text-decoration-none">
      <div class="card stat-card p-3 border-<?= $color ?> <?= $statusFilter === $filter ? 'border-2' : '' ?>">
        <div class="text-<?= $color ?> fw-bold fs-5"><?= number_format((int)$count) ?></div>
        <small class="text-muted"><?= $label ?></small>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="card stat-card p-3">
  <div class="d-flex gap-2 mb-3">
    <input type="text" id="searchInput" class="form-control form-control-sm"
           placeholder="Search by title, company…" style="max-width:300px;">
    <?php if ($statusFilter): ?>
    <a href="/jobs.php" class="btn btn-outline-secondary btn-sm">Clear filter</a>
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle" id="jobTable">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Job Title</th><th>Company</th><th>Education Req.</th>
          <th>Salary</th><th>Slots</th><th>Matches</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($list as $i => $j): ?>
      <tr>
        <td class="text-muted small"><?= $i + 1 ?></td>
        <td>
          <div class="fw-semibold"><?= h($j['job_title']) ?></div>
          <small class="text-muted"><?= $j['skill_count'] ?> required skills</small>
        </td>
        <td><?= h($j['company']) ?></td>
        <td><?= h($j['required_education'] ?? '"”') ?></td>
        <td><?= formatSalary($j['salary_min'], $j['salary_max']) ?></td>
        <td><?= $j['slots'] ?></td>
        <td><span class="badge bg-info text-dark"><?= $j['match_count'] ?></span></td>
        <td>
          <?php $sc = ['active'=>'success','filled'=>'warning','inactive'=>'secondary'][$j['status']] ?? 'secondary'; ?>
          <span class="badge bg-<?= $sc ?>"><?= ucfirst($j['status']) ?></span>
        </td>
        <td>
          <a href="/job_view.php?id=<?= $j['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
          <a href="/job_edit.php?id=<?= $j['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
          <a href="/matching.php?job_id=<?= $j['id'] ?>" class="btn btn-sm btn-outline-success" title="Generate Matches"><i class="bi bi-cpu"></i></a>
          <a href="/api/jobs.php?action=delete&id=<?= $j['id'] ?>"
             class="btn btn-sm btn-outline-danger"
             data-confirm="Delete this job vacancy? All related recommendations will also be removed." title="Delete">
            <i class="bi bi-trash"></i>
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($list)): ?>
      <tr><td colspan="9" class="text-center text-muted py-4">No job vacancies found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>initTableSearch('searchInput','jobTable');</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

