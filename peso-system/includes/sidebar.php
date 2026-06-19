<?php
$currentPage = basename($_SERVER['PHP_SELF']);
function isActive(array $pages): string {
    global $currentPage;
    return in_array($currentPage, $pages) ? 'active' : '';
}
?>
<nav id="sidebar" class="bg-dark text-white d-flex flex-column"
     style="width:240px;min-height:calc(100vh - 56px);position:sticky;top:56px;flex-shrink:0;">

  <ul class="nav flex-column px-2 py-3 flex-grow-1">

    <li class="nav-item">
      <a class="nav-link text-white-50 small fw-semibold text-uppercase px-2 pb-1" href="#">Main</a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= isActive(['dashboard.php']) ?>"
         href="/dashboard.php">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
      </a>
    </li>

    <li class="nav-item mt-2">
      <a class="nav-link text-white-50 small fw-semibold text-uppercase px-2 pb-1" href="#">Records</a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= isActive(['applicants.php','applicant_add.php','applicant_edit.php','applicant_view.php']) ?>"
         href="/applicants.php">
        <i class="bi bi-people me-2"></i>Applicants
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= isActive(['jobs.php','job_add.php','job_edit.php','job_view.php']) ?>"
         href="/jobs.php">
        <i class="bi bi-briefcase me-2"></i>Job Vacancies
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= isActive(['referrals.php']) ?>"
         href="/referrals.php">
        <i class="bi bi-send me-2"></i>Referrals
      </a>
    </li>

    <li class="nav-item mt-2">
      <a class="nav-link text-white-50 small fw-semibold text-uppercase px-2 pb-1" href="#">AI Matching</a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= isActive(['matching.php']) ?>"
         href="/matching.php">
        <i class="bi bi-cpu me-2"></i>Job Matching
      </a>
    </li>

    <li class="nav-item mt-2">
      <a class="nav-link text-white-50 small fw-semibold text-uppercase px-2 pb-1" href="#">Analytics</a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= isActive(['analytics.php']) ?>"
         href="/analytics.php">
        <i class="bi bi-bar-chart-line me-2"></i>Dashboard
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= isActive(['reports.php']) ?>"
         href="/reports.php">
        <i class="bi bi-file-earmark-bar-graph me-2"></i>Reports &amp; Stats
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= isActive(['placements.php']) ?>"
         href="/placements.php">
        <i class="bi bi-award me-2"></i>Placements
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= isActive(['skills_analysis.php']) ?>"
         href="/skills_analysis.php">
        <i class="bi bi-tools me-2"></i>Skills Analysis
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= isActive(['location_analytics.php']) ?>"
         href="/location_analytics.php">
        <i class="bi bi-geo-alt me-2"></i>Location Analytics
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= isActive(['demand_jobs.php']) ?>"
         href="/demand_jobs.php">
        <i class="bi bi-graph-up-arrow me-2"></i>In-Demand Jobs
      </a>
    </li>

    <li class="nav-item mt-2">
      <a class="nav-link text-white-50 small fw-semibold text-uppercase px-2 pb-1" href="#">System</a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= isActive(['activity_logs.php']) ?>"
         href="/activity_logs.php">
        <i class="bi bi-clock-history me-2"></i>Activity Logs
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= isActive(['dataset_upload.php']) ?>"
         href="/dataset_upload.php">
        <i class="bi bi-cloud-upload me-2"></i>Dataset &amp; ML
      </a>
    </li>

  </ul>

  <div class="px-3 pb-3">
    <small class="text-white-50">PESO CSJDM &copy; 2026</small>
  </div>
</nav>

