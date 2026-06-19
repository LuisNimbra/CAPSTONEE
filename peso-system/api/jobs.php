<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$action = $_GET['action'] ?? '';

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    db()->prepare('DELETE FROM recommendations WHERE job_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM job_vacancies WHERE id = ?')->execute([$id]);
    logActivity('Delete Job', 'Jobs', "Deleted job ID: $id");
    header('Location: /jobs.php?deleted=1');
    exit;
}

jsonResponse(['error' => 'Unknown action'], 400);

