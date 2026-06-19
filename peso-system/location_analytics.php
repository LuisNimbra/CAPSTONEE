<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$pdo = db();

// Placements per barangay
$barangayData = $pdo->query("
    SELECT barangay, COUNT(*) cnt
    FROM placements
    WHERE barangay IS NOT NULL AND barangay != ''
    GROUP BY barangay ORDER BY cnt DESC
")->fetchAll();

// Applicants per barangay
$appByBarangay = $pdo->query("
    SELECT barangay, COUNT(*) cnt
    FROM applicants
    WHERE barangay IS NOT NULL AND barangay != ''
    GROUP BY barangay ORDER BY cnt DESC LIMIT 12
")->fetchAll();

$totalBarangays = count($barangayData);
$totalPlaced    = array_sum(array_column($barangayData, 'cnt'));
$topBarangay    = $barangayData[0] ?? null;
$avgPerBarangay = $totalBarangays > 0 ? round($totalPlaced / $totalBarangays, 1) : 0;

$pageTitle = 'Location Analytics "” PESO CSJDM DSS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h4><i class="bi bi-geo-alt me-2 text-primary"></i>Location Analytics</h4>
  <div class="d-flex gap-2 align-items-center">
    <small class="text-muted">San Jose del Monte, Bulacan</small>
    <button class="btn btn-outline-success btn-sm no-print" onclick="exportTableExcel('barangayTable','Barangay_Placements')">
      <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
    </button>
    <button class="btn btn-outline-secondary btn-sm no-print" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
  </div>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
  <?php foreach ([
    ['Highest Placement Area',  $topBarangay ? h($topBarangay['barangay']) . ' (' . $topBarangay['cnt'] . ')' : '"”', 'geo-alt-fill', 'success'],
    ['Total Barangays Covered', $totalBarangays, 'map-fill', 'primary'],
    ['Total Placements',        $totalPlaced,    'award-fill','warning'],
    ['Avg. Placements / Brgy',  $avgPerBarangay, 'calculator','info'],
  ] as [$label, $val, $icon, $color]): ?>
  <div class="col-6 col-md-3">
    <div class="card stat-card p-3">
      <div class="icon-wrap bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> mb-2">
        <i class="bi bi-<?= $icon ?>"></i>
      </div>
      <div class="fs-5 fw-bold"><?= $val ?></div>
      <small class="text-muted"><?= $label ?></small>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <!-- Heatmap (simulated grid) -->
  <div class="col-md-7">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Barangay Heat Map "” Placements</h6>
      <?php if ($barangayData):
        $maxCnt = max(array_column($barangayData, 'cnt'));
      ?>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($barangayData as $b):
          $intensity = $maxCnt > 0 ? round($b['cnt'] / $maxCnt * 100) : 0;
          $alpha = max(0.15, $intensity / 100);
          $textColor = $intensity > 60 ? '#fff' : '#0d47a1';
        ?>
        <div class="heatmap-cell"
             style="background:rgba(13,71,161,<?= $alpha ?>);color:<?= $textColor ?>;
                    width:120px;height:60px;flex-direction:column;gap:2px;padding:6px;">
          <div class="small fw-semibold text-center" style="line-height:1.2;"><?= h($b['barangay']) ?></div>
          <div class="text-center" style="font-size:.75rem;"><?= $b['cnt'] ?> placed</div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-3 d-flex align-items-center gap-2">
        <small class="text-muted">Low</small>
        <div style="height:10px;width:200px;background:linear-gradient(to right,rgba(13,71,161,.15),rgba(13,71,161,1));border-radius:5px;"></div>
        <small class="text-muted">High</small>
      </div>
      <?php else: ?>
      <p class="text-muted">No placement location data yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-md-5">
    <div class="card stat-card p-3">
      <h6 class="fw-semibold mb-3"><i class="bi bi-bar-chart me-2 text-primary"></i>Applicants by Barangay</h6>
      <canvas id="appBarangayChart" height="250"></canvas>
    </div>
  </div>
</div>

<!-- Detail table -->
<div class="card stat-card p-3">
  <h6 class="fw-semibold mb-3"><i class="bi bi-table me-2 text-primary"></i>Placement Details by Barangay</h6>
  <div class="table-responsive">
    <table class="table table-sm table-hover" id="barangayTable">
      <thead><tr><th>#</th><th>Barangay</th><th>Placements</th><th>Share</th><th>Distribution</th></tr></thead>
      <tbody>
      <?php foreach ($barangayData as $i => $b):
        $share = $totalPlaced > 0 ? round($b['cnt'] / $totalPlaced * 100, 1) : 0;
      ?>
      <tr>
        <td class="text-muted"><?= $i + 1 ?></td>
        <td class="fw-semibold"><?= h($b['barangay']) ?></td>
        <td><?= $b['cnt'] ?></td>
        <td><?= $share ?>%</td>
        <td style="width:150px">
          <div class="progress skill-bar">
            <div class="progress-bar bg-primary" style="width:<?= $share ?>%"></div>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($barangayData)): ?><tr><td colspan="5" class="text-center text-muted">No data yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function exportTableExcel(tableId, sheetName) {
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(document.getElementById(tableId), {raw: true});
    XLSX.utils.book_append_sheet(wb, ws, sheetName);
    XLSX.writeFile(wb, sheetName + '_' + new Date().toISOString().slice(0,10) + '.xlsx');
}

new Chart(document.getElementById('appBarangayChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($appByBarangay,'barangay')) ?>,
    datasets: [{ label: 'Applicants', data: <?= json_encode(array_column($appByBarangay,'cnt')) ?>, backgroundColor: '#1976d2', borderRadius: 6 }]
  },
  options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
});
</script>

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

