<?php
require_once __DIR__ . '/../config/db.php';

function educationRank(string $level): int {
    return match($level) {
        'Elementary'   => 1,
        'High School'  => 2,
        'Vocational'   => 3,
        'College'      => 4,
        'Post-Graduate'=> 5,
        default        => 0,
    };
}

function getApplicantSkills(int $applicantId): array {
    $stmt = db()->prepare('SELECT skill FROM applicant_skills WHERE applicant_id = ?');
    $stmt->execute([$applicantId]);
    return array_column($stmt->fetchAll(), 'skill');
}

function getJobSkills(int $jobId): array {
    $stmt = db()->prepare('SELECT skill FROM job_required_skills WHERE job_id = ?');
    $stmt->execute([$jobId]);
    return array_column($stmt->fetchAll(), 'skill');
}

function skillMatchScore(array $applicantSkills, array $jobSkills): float {
    if (empty($jobSkills)) return 0.0;
    $applicantLower = array_map('strtolower', $applicantSkills);
    $matched = 0;
    foreach ($jobSkills as $js) {
        if (in_array(strtolower($js), $applicantLower, true)) $matched++;
    }
    return round($matched / count($jobSkills), 4);
}

function mlApiCall(string $endpoint, array $payload): ?array {
    $ch = curl_init(ML_API_URL . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || $response === false) return null;
    return json_decode($response, true);
}

function mlApiGet(string $endpoint): ?array {
    $ch = curl_init(ML_API_URL . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || $response === false) return null;
    return json_decode($response, true);
}

function formatSalary(?float $min, ?float $max): string {
    if (!$min && !$max) return 'Negotiable';
    if ($min && $max)   return '₱' . number_format($min) . ' – ₱' . number_format($max);
    return '₱' . number_format($min ?: $max);
}

function timeSince(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
