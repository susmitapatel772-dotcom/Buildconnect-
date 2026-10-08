<?php
/**
 * BuildConnect JSON Analytics API
 * Secure endpoint serving structured aggregation data for dynamic charts & filters.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/analytics-functions.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user = get_logged_user();
$filters = [
    'date_range' => sanitize($_GET['date_range'] ?? 'all'),
    'start_date' => sanitize($_GET['start_date'] ?? ''),
    'end_date' => sanitize($_GET['end_date'] ?? ''),
    'project_id' => (int)($_GET['project_id'] ?? 0),
    'city' => sanitize($_GET['city'] ?? ''),
    'status' => sanitize($_GET['status'] ?? '')
];

try {
    $data = [];

    switch ($user['role']) {
        case ROLE_ADMIN:
            $data = get_admin_analytics_data($filters);
            break;

        case ROLE_CONTRACTOR:
            $data = get_contractor_analytics_data($user['id'], $filters);
            break;

        case ROLE_WORKER:
            $data = get_worker_analytics_data($user['id'], $filters);
            break;

        case ROLE_CLIENT:
            $data = get_client_analytics_data($user['id'], $filters);
            break;

        default:
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            exit();
    }

    echo json_encode(['success' => true, 'role' => $user['role'], 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load analytics right now.']);
}
exit();
