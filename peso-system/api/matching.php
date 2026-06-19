<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

// Handle refer action via GET
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'refer') {
    $id  = (int)($_GET['id'] ?? 0);
    $pdo = db();
    $pdo->prepare("UPDATE recommendations SET status='referred' WHERE id=?")->execute([$id]);

    $rec = $pdo->prepare('SELECT applicant_id, job_id FROM recommendations WHERE id=?');
    $rec->execute([$id]);
    $r = $rec->fetch();

    // Record referral in referrals table for outcome tracking
    if ($r) {
        $pdo->prepare("
            INSERT INTO referrals (applicant_id, job_id, referral_date, outcome, created_by)
            VALUES (?,?,?,?,?)
        ")->execute([
            $r['applicant_id'],
            $r['job_id'],
            date('Y-m-d'),
            'Pending',
            $_SESSION['user_id'],
        ]);
    }

    logActivity('Refer Applicant', 'Matching', "Referred recommendation ID: $id, Job ID: " . ($r['job_id'] ?? 0));
    header('Location: /job_view.php?id=' . ($r['job_id'] ?? 0) . '&referred=1');
    exit;
}

// Handle JSON POST for generate
header('Content-Type: application/json');
$body = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

if ($action === 'generate') {
    $jobId = (int)($body['job_id'] ?? 0);
    $topN  = (int)($body['top_n'] ?? 10);

    if (!$jobId) { jsonResponse(['success' => false, 'error' => 'Invalid job ID'], 400); }

    $pdo = db();

    $job = $pdo->prepare('SELECT * FROM job_vacancies WHERE id = ?');
    $job->execute([$jobId]);
    $jobRow = $job->fetch();
    if (!$jobRow) { jsonResponse(['success' => false, 'error' => 'Job not found'], 404); }

    $jobSkills = getJobSkills($jobId);
    $requiredEduRank = educationRank($jobRow['required_education'] ?? '');

    $appStmt = $pdo->query("SELECT id, first_name, last_name, education_level, years_experience, age, sex FROM applicants WHERE status='active'");
    $appList = $appStmt->fetchAll();

    // Build feature vectors and call Flask ML API
    $features = [];
    foreach ($appList as $app) {
        $appSkills      = getApplicantSkills($app['id']);
        $skillScore     = skillMatchScore($appSkills, $jobSkills);
        $matchedSkills  = array_intersect(array_map('strtolower', $appSkills), array_map('strtolower', $jobSkills));
        $missingSkills  = array_diff(array_map('strtolower', $jobSkills), array_map('strtolower', $appSkills));
        $eduRank        = educationRank($app['education_level']);
        $eduMeetsReq    = $eduRank >= $requiredEduRank ? 1 : 0;
        $expMeetsReq    = $app['years_experience'] >= $jobRow['required_experience'] ? 1 : 0;

        $features[] = [
            'applicant_id'      => $app['id'],
            'first_name'        => $app['first_name'],
            'last_name'         => $app['last_name'],
            'education_encoded' => $eduRank,
            'edu_meets_req'     => $eduMeetsReq,
            'years_experience'  => (float)$app['years_experience'],
            'exp_meets_req'     => $expMeetsReq,
            'skill_match_score' => $skillScore,
            'skill_count'       => count($appSkills),
            'matched_skills'    => array_values($matchedSkills),
            'missing_skills'    => array_values($missingSkills),
            'age'               => (int)($app['age'] ?? 25),
            'sex_encoded'       => $app['sex'] === 'Female' ? 1 : 0,
        ];
    }

    // Try ML API; fall back to weighted scoring if unavailable
    $mlResult = mlApiCall('/predict', ['job_id' => $jobId, 'applicants' => $features]);

    $algorithm = 'Weighted Score';
    $accuracy   = 0.0;
    $scores     = [];

    if ($mlResult && isset($mlResult['predictions'])) {
        $algorithm = $mlResult['model'] ?? 'ML Model';
        $accuracy  = $mlResult['accuracy'] ?? 0.0;
        foreach ($mlResult['predictions'] as $pred) {
            $scores[$pred['applicant_id']] = (float)$pred['score'];
        }
    } else {
        // Fallback: weighted scoring (education 20%, exp 30%, skills 50%)
        foreach ($features as $f) {
            $eduScore = $f['education_encoded'] / 5;
            $expScore = min($f['years_experience'] / max($jobRow['required_experience'], 1), 1.0);
            $scores[$f['applicant_id']] = round(0.2 * $eduScore + 0.3 * $expScore + 0.5 * $f['skill_match_score'], 4);
        }
    }

    // Sort and rank
    arsort($scores);
    $ranked  = array_slice($scores, 0, $topN, true);
    $featureMap = array_column($features, null, 'applicant_id');

    // Clear old recommendations for this job
    $pdo->prepare('DELETE FROM recommendations WHERE job_id = ?')->execute([$jobId]);

    $rank = 1;
    $insertStmt = $pdo->prepare("
        INSERT INTO recommendations (job_id, applicant_id, match_score, rank_position, algorithm_used, explanation)
        VALUES (?,?,?,?,?,?)
    ");

    $insertedIds = [];
    foreach ($ranked as $appId => $score) {
        $feat = $featureMap[$appId] ?? [];
        $explanation = json_encode([
            'matched_skills' => $feat['matched_skills'] ?? [],
            'missing_skills' => $feat['missing_skills'] ?? [],
            'edu_meets_req'  => $feat['edu_meets_req'] ?? 0,
            'exp_meets_req'  => $feat['exp_meets_req'] ?? 0,
            'skill_score'    => $feat['skill_match_score'] ?? 0,
        ]);
        $insertStmt->execute([$jobId, $appId, $score, $rank, $algorithm, $explanation]);
        $insertedIds[] = (int)$pdo->lastInsertId();
        $rank++;
    }

    // Fetch rows for HTML rendering
    $recStmt = $pdo->prepare("
        SELECT r.*, a.first_name, a.last_name, a.education_level, a.years_experience, a.barangay
        FROM recommendations r
        JOIN applicants a ON a.id = r.applicant_id
        WHERE r.id IN (" . implode(',', $insertedIds) . ")
        ORDER BY r.rank_position ASC
    ");
    $recStmt->execute();
    $recRows = $recStmt->fetchAll();

    logActivity('Generate Matches', 'Matching', "Job ID: $jobId, Algorithm: $algorithm");

    // Build HTML inline
    $html = buildResultsHtml($recRows);
    jsonResponse([
        'success'   => true,
        'count'     => count($recRows),
        'algorithm' => $algorithm,
        'accuracy'  => $accuracy ? round($accuracy * 100, 1) . '%' : 'N/A',
        'html'      => $html,
    ]);
}

function buildResultsHtml(array $rows): string {
    if (empty($rows)) return '<p class="text-muted">No matches found.</p>';
    $html = '<div class="table-responsive"><table class="table table-hover align-middle">';
    $html .= '<thead class="table-light"><tr><th>Rank</th><th>Applicant</th><th>Education</th><th>Exp.</th><th>Match Score</th><th>Key Factors</th><th>Status</th><th>Action</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $pct     = round($r['match_score'] * 100);
        $cls     = $pct >= 70 ? 'score-high' : ($pct >= 40 ? 'score-mid' : 'score-low');
        $rankN   = (int)$r['rank_position'];
        $rankCls = ['rank-1','rank-2','rank-3'][$rankN - 1] ?? 'rank-n';
        $exp     = json_decode($r['explanation'] ?? '{}', true) ?? [];
        $matched = !empty($exp['matched_skills']) ? '<small class="text-success"><i class="bi bi-check-circle me-1"></i>' . implode(', ', array_map('htmlspecialchars', $exp['matched_skills'])) . '</small>' : '';
        $missing = !empty($exp['missing_skills']) ? '<br><small class="text-danger"><i class="bi bi-x-circle me-1"></i>' . implode(', ', array_map('htmlspecialchars', $exp['missing_skills'])) . '</small>' : '';
        $html .= "<tr>
            <td><span class='badge {$rankCls}'>#$rankN</span></td>
            <td><a href='/applicant_view.php?id={$r['applicant_id']}' class='fw-semibold text-decoration-none'>" . htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) . "</a></td>
            <td>" . htmlspecialchars($r['education_level']) . "</td>
            <td>{$r['years_experience']}yr</td>
            <td><span class='score-badge {$cls}'>{$pct}%</span><div class='progress mt-1 skill-bar'><div class='progress-bar bg-primary' style='width:{$pct}%'></div></div></td>
            <td style='max-width:200px'>$matched$missing</td>
            <td><span class='badge bg-secondary'>pending</span></td>
            <td><a href='/api/matching.php?action=refer&id={$r['id']}' class='btn btn-sm btn-outline-success'>Refer</a></td>
          </tr>";
    }
    $html .= '</tbody></table></div>';
    return $html;
}

