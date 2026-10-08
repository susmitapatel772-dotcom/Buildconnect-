<?php
/**
 * BuildConnect CSV Export Engine
 * Secure server-side CSV streaming with strict role-based access & ownership validation.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/analytics-functions.php';

require_auth();

$user = get_logged_user();
$db = getDB();

$report_type = sanitize($_GET['report'] ?? 'projects');
$date_range = sanitize($_GET['date_range'] ?? 'all');
$start_date = sanitize($_GET['start_date'] ?? '');
$end_date = sanitize($_GET['end_date'] ?? '');
$project_id = (int)($_GET['project_id'] ?? 0);

$filename = "buildconnect_" . $report_type . "_" . $user['role'] . "_" . date('Ymd_His') . ".csv";

// Output CSV Headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// BOM for UTF-8 compatibility in Excel
fputs($output, "\xEF\xBB\xBF");

switch ($report_type) {
    case 'projects':
        fputcsv($output, ['Project ID', 'Title', 'Contractor', 'Client', 'City', 'State', 'Budget', 'Progress (%)', 'Status', 'Start Date', 'Target End Date', 'Created At']);

        $sql = "
            SELECT p.*, 
                u_cont.name as contractor_name, 
                u_cli.name as client_name
            FROM projects p
            LEFT JOIN users u_cont ON p.contractor_id = u_cont.id
            LEFT JOIN users u_cli ON p.client_id = u_cli.id
            WHERE 1=1
        ";
        $params = [];

        if ($user['role'] === ROLE_CONTRACTOR) {
            $sql .= " AND p.contractor_id = ?";
            $params[] = $user['id'];
        } elseif ($user['role'] === ROLE_CLIENT) {
            $sql .= " AND p.client_id = ?";
            $params[] = $user['id'];
        } elseif ($user['role'] === ROLE_WORKER) {
            $sql .= " AND p.id IN (SELECT project_id FROM project_members WHERE user_id = ? AND status = 'active')";
            $params[] = $user['id'];
        }

        if ($project_id > 0) {
            $sql .= " AND p.id = ?";
            $params[] = $project_id;
        }

        $date_clause = get_analytics_date_clause('p.created_at', $date_range, $start_date, $end_date);
        $sql .= $date_clause['sql'] . " ORDER BY p.id DESC";
        $params = array_merge($params, $date_clause['params']);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['title'],
                $row['contractor_name'] ?? 'N/A',
                $row['client_name'] ?? 'N/A',
                $row['city'],
                $row['state'],
                $row['budget'],
                $row['progress_percent'] . '%',
                strtoupper($row['status']),
                $row['start_date'] ?? 'N/A',
                $row['end_date'] ?? 'N/A',
                $row['created_at']
            ]);
        }
        break;

    case 'attendance':
        fputcsv($output, ['Attendance ID', 'Project Title', 'Worker Name', 'Worker Email', 'Attendance Date', 'Check-In', 'Check-Out', 'Hours Worked', 'QR Verified', 'Status']);

        $sql = "
            SELECT a.*, p.title as project_title, u.name as worker_name, u.email as worker_email
            FROM attendance a
            JOIN projects p ON a.project_id = p.id
            JOIN users u ON a.worker_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($user['role'] === ROLE_CONTRACTOR) {
            $sql .= " AND p.contractor_id = ?";
            $params[] = $user['id'];
        } elseif ($user['role'] === ROLE_WORKER) {
            $sql .= " AND a.worker_id = ?";
            $params[] = $user['id'];
        } elseif ($user['role'] === ROLE_CLIENT) {
            $sql .= " AND p.client_id = ?";
            $params[] = $user['id'];
        }

        if ($project_id > 0) {
            $sql .= " AND a.project_id = ?";
            $params[] = $project_id;
        }

        $date_clause = get_analytics_date_clause('a.attendance_date', $date_range, $start_date, $end_date);
        $sql .= $date_clause['sql'] . " ORDER BY a.attendance_date DESC, a.id DESC";
        $params = array_merge($params, $date_clause['params']);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['project_title'],
                $row['worker_name'],
                $row['worker_email'],
                $row['attendance_date'],
                $row['check_in_time'],
                $row['check_out_time'] ?? 'N/A',
                $row['hours_worked'],
                $row['verified_by_qr'] ? 'YES' : 'NO',
                strtoupper($row['status'])
            ]);
        }
        break;

    case 'jobs':
        fputcsv($output, ['Job ID', 'Project Title', 'Contractor Name', 'Job Title', 'Trade Required', 'Pay Rate', 'Pay Type', 'City', 'Spots Available', 'Spots Filled', 'Status', 'Created At']);

        $sql = "
            SELECT j.*, p.title as project_title, u.name as contractor_name
            FROM jobs j
            JOIN projects p ON j.project_id = p.id
            JOIN users u ON j.contractor_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($user['role'] === ROLE_CONTRACTOR) {
            $sql .= " AND j.contractor_id = ?";
            $params[] = $user['id'];
        } elseif ($user['role'] === ROLE_CLIENT) {
            $sql .= " AND p.client_id = ?";
            $params[] = $user['id'];
        }

        if ($project_id > 0) {
            $sql .= " AND j.project_id = ?";
            $params[] = $project_id;
        }

        $date_clause = get_analytics_date_clause('j.created_at', $date_range, $start_date, $end_date);
        $sql .= $date_clause['sql'] . " ORDER BY j.id DESC";
        $params = array_merge($params, $date_clause['params']);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['project_title'],
                $row['contractor_name'],
                $row['title'],
                $row['trade_required'],
                $row['pay_rate'],
                strtoupper($row['pay_type']),
                $row['city'],
                $row['spots_available'],
                $row['spots_filled'],
                strtoupper($row['status']),
                $row['created_at']
            ]);
        }
        break;

    case 'applications':
        fputcsv($output, ['Application ID', 'Job Title', 'Worker Name', 'Worker Email', 'Expected Pay', 'Match Score', 'Status', 'Applied At']);

        $sql = "
            SELECT ja.*, j.title as job_title, u.name as worker_name, u.email as worker_email, j.contractor_id
            FROM job_applications ja
            JOIN jobs j ON ja.job_id = j.id
            JOIN users u ON ja.worker_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($user['role'] === ROLE_CONTRACTOR) {
            $sql .= " AND j.contractor_id = ?";
            $params[] = $user['id'];
        } elseif ($user['role'] === ROLE_WORKER) {
            $sql .= " AND ja.worker_id = ?";
            $params[] = $user['id'];
        }

        $date_clause = get_analytics_date_clause('ja.applied_at', $date_range, $start_date, $end_date);
        $sql .= $date_clause['sql'] . " ORDER BY ja.id DESC";
        $params = array_merge($params, $date_clause['params']);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['job_title'],
                $row['worker_name'],
                $row['worker_email'],
                $row['expected_pay'] ?? 'N/A',
                $row['match_score'] . '%',
                strtoupper($row['status']),
                $row['applied_at']
            ]);
        }
        break;

    case 'workforce':
    default:
        fputcsv($output, ['Worker ID', 'Name', 'Email', 'Trade Title', 'Verification', 'Rating', 'Reviews Count', 'City', 'Status']);

        $sql = "
            SELECT w.*, u.name, u.email, u.status as user_status
            FROM workers w
            JOIN users u ON w.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($user['role'] === ROLE_CONTRACTOR) {
            $sql .= " AND w.user_id IN (
                SELECT DISTINCT user_id FROM project_members WHERE project_id IN (SELECT id FROM projects WHERE contractor_id = ?)
            )";
            $params[] = $user['id'];
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['name'],
                $row['email'],
                $row['trade_title'],
                strtoupper($row['verification_status']),
                $row['rating_avg'],
                $row['reviews_count'],
                $row['city'],
                strtoupper($row['user_status'])
            ]);
        }
        break;
}

fclose($output);
exit();
