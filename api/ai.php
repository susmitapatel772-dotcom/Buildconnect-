<?php
/**
 * BuildConnect Centralized AI API Endpoint
 * Handles secure AJAX actions for worker matching, job description assistance, project risk insights, task risk, and profile improvement.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required to access AI services.']);
    exit();
}

$user = get_logged_user();
$action = sanitize($_REQUEST['action'] ?? '');

if (!check_ai_rate_limit($user['id'], $action)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'AI request limit reached for this minute. Please wait a moment before trying again.']);
    exit();
}

try {
    switch ($action) {
        case 'match_workers':
            if ($user['role'] !== ROLE_CONTRACTOR) {
                throw new Exception("Only contractors can request worker AI matching.");
            }
            $job_id = (int)($_REQUEST['job_id'] ?? 0);
            $force_refresh = !empty($_REQUEST['refresh']);

            $matches = ai_match_workers_for_job($job_id, $user['id'], $force_refresh);
            echo json_encode(['success' => true, 'action' => $action, 'matches' => $matches]);
            break;

        case 'improve_job_description':
            if ($user['role'] !== ROLE_CONTRACTOR) {
                throw new Exception("Only contractors can request job description assistance.");
            }
            $title = sanitize($_REQUEST['title'] ?? '');
            $description = sanitize($_REQUEST['description'] ?? '');
            $skills = sanitize($_REQUEST['skills'] ?? '');

            $result = ai_improve_job_description($title, $description, $skills);
            echo json_encode(['success' => true, 'action' => $action, 'data' => $result]);
            break;

        case 'project_insights':
        case 'generate_project_summary':
            $project_id = (int)($_REQUEST['project_id'] ?? 0);
            $is_admin = ($user['role'] === ROLE_ADMIN);

            $insights = ai_analyze_project_insights($project_id, $user['id'], $is_admin);
            echo json_encode(['success' => true, 'action' => $action, 'insights' => $insights]);
            break;

        case 'task_risk':
            $task_id = (int)($_REQUEST['task_id'] ?? 0);
            $risk = ai_analyze_task_risk($task_id, $user['id']);
            echo json_encode(['success' => true, 'action' => $action, 'risk' => $risk]);
            break;

        case 'worker_profile_assistance':
            if ($user['role'] !== ROLE_WORKER) {
                throw new Exception("Only workers can request profile assistance.");
            }
            $suggestions = ai_suggest_worker_profile_improvements($user['id']);
            echo json_encode(['success' => true, 'action' => $action, 'data' => $suggestions]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid or unspecified AI action.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit();
