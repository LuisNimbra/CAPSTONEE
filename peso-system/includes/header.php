<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="/peso-system/assets/css/style.css">
</head>
<body>

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary px-3 fixed-top shadow-sm" style="z-index:1050;">
  <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/peso-system/dashboard.php">
    <i class="bi bi-briefcase-fill fs-5"></i>
    <span>PESO CSJDM DSS</span>
  </a>
  <div class="ms-auto d-flex align-items-center gap-3">
    <span class="text-white-50 small d-none d-md-inline">
      <i class="bi bi-person-circle me-1"></i><?= h(currentUser()['full_name'] ?? 'Staff') ?>
    </span>
    <a href="/peso-system/logout.php" class="btn btn-outline-light btn-sm">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </div>
</nav>

<div class="d-flex" style="padding-top:56px;">
<?php require_once __DIR__ . '/sidebar.php'; ?>
<main class="flex-grow-1 p-4" style="min-height:calc(100vh - 56px); background:#f4f6fb;">
