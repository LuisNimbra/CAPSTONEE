<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /peso-system/login.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function logActivity(string $action, string $module, string $details = ''): void {
    try {
        require_once __DIR__ . '/../config/db.php';
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (user_id, action, module, details, ip_address)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $module,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Throwable) {}
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid request token.');
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
