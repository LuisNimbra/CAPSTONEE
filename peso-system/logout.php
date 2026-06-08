<?php
require_once __DIR__ . '/includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
logActivity('Logout', 'Auth', 'User logged out');
$_SESSION = [];
session_destroy();
header('Location: /peso-system/login.php');
exit;
