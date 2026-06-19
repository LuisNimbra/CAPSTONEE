<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$action = $_GET['action'] ?? '';

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    db()->prepare('DELETE FROM applicants WHERE id = ?')->execute([$id]);
    logActivity('Delete Applicant', 'Applicants', "Deleted ID: $id");
    header('Location: /applicants.php?deleted=1');
    exit;
}

jsonResponse(['error' => 'Unknown action'], 400);

