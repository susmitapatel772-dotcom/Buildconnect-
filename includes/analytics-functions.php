<?php
/**
 * BuildConnect Analytics & Reporting Helper Functions
 * Provides real database aggregations for Admin, Contractor, Worker, and Client dashboards.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

/**
 * Builds a parameterized SQL snippet and params array for date range filtering.
 *
 * @param string $column Column name (e.g. 'created_at' or 'attendance_date')
 * @param string $range 'all', 'today', '7days', '30days', '90days', 'this_year', 'custom'
 * @param string|null $start Custom start date YYYY-MM-DD
 * @param string|null $end Custom end date YYYY-MM-DD
 * @return array ['sql' => string, 'params' => array]
 */
function get_analytics_date_clause($column, $range = 'all', $start = null, $end = null) {
    $sql = '';
    $params = [];

    switch ($range) {
        case 'today':
            $sql = " AND DATE({$column}) = CURRENT_DATE()";
            break;
        case '7days':
            $sql = " AND {$column} >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)";
            break;
        case '30days':
            $sql = " AND {$column} >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)";
            break;
        case '90days':
            $sql = " AND {$column} >= DATE_SUB(CURRENT_DATE(), INTERVAL 90 DAY)";
            break;
        case 'this_year':
            $sql = " AND YEAR({$column}) = YEAR(CURRENT_DATE())";
            break;
        case 'custom':
            if (!empty($start)) {
                $sql .= " AND {$column} >= ?";
                $params[] = $start;
            }
            if (!empty($end)) {
                $sql .= " AND {$column} <= ?";
                $params[] = $end . ' 23:59:59';
            }
            break;
        case 'all':
        default:
            break;
    }

    return ['sql' => $sql, 'params' => $params];
}

/**
 * Calculates user growth rate safely between current month and previous month.
 *
 * @return array ['current_month' => int, 'last_month' => int, 'growth_pct' => string|float]
 */
function get_admin_user_growth_metrics() {
    $db = getDB();

    $stmt_cur = $db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')");
    $cur_month = (int)$stmt_cur->fetchColumn();

    $stmt_last = $db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(CURRENT_DATE() - INTERVAL 1 MONTH, '%Y-%m-01') AND created_at < DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')");
    $last_month = (int)$stmt_last->fetchColumn();

    if ($last_month === 0) {
        $growth_pct = ($cur_month > 0) ? 'N/A' : '0%';
    } else {
        $diff = $cur_month - $last_month;
        $pct = round(($diff / $last_month) * 100, 1);
        $growth_pct = ($pct >= 0 ? '+' : '') . $pct . '%';
    }

    return [
        'current_month' => $cur_month,
        'last_month' => $last_month,
        'growth_pct' => $growth_pct
    ];
}

/**
 * Admin Platform Analytics Aggregations
 */
function get_admin_analytics_data($filters = []) {
    $db = getDB();
    $range = $filters['date_range'] ?? 'all';
    $start = $filters['start_date'] ?? null;
    $end = $filters['end_date'] ?? null;
    $city = $filters['city'] ?? '';
    $status = $filters['status'] ?? '';

    $u_clause = get_analytics_date_clause('created_at', $range, $start, $end);
    $p_clause = get_analytics_date_clause('created_at', $range, $start, $end);
    $j_clause = get_analytics_date_clause('created_at', $range, $start, $end);
    $a_clause = get_analytics_date_clause('applied_at', $range, $start, $end);
    $c_clause = get_analytics_date_clause('created_at', $range, $start, $end);
    $att_clause = get_analytics_date_clause('attendance_date', $range, $start, $end);

    // Platform KPIs
    $kpis = [];

    // User counts
    $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active, SUM(CASE WHEN role = 'worker' THEN 1 ELSE 0 END) as workers, SUM(CASE WHEN role = 'contractor' THEN 1 ELSE 0 END) as contractors, SUM(CASE WHEN role = 'client' THEN 1 ELSE 0 END) as clients FROM users WHERE 1=1" . $u_clause['sql']);
    $stmt->execute($u_clause['params']);
    $u_stats = $stmt->fetch();

    $kpis['total_users'] = (int)($u_stats['total'] ?? 0);
    $kpis['active_users'] = (int)($u_stats['active'] ?? 0);
    $kpis['total_workers'] = (int)($u_stats['workers'] ?? 0);
    $kpis['total_contractors'] = (int)($u_stats['contractors'] ?? 0);
    $kpis['total_clients'] = (int)($u_stats['clients'] ?? 0);

    // Verified Workers
    $kpis['verified_workers'] = (int)$db->query("SELECT COUNT(*) FROM workers WHERE verification_status = 'approved'")->fetchColumn();

    // Projects
    $p_city_sql = '';
    $p_city_params = [];
    if (!empty($city)) {
        $p_city_sql = " AND city = ?";
        $p_city_params[] = $city;
    }
    if (!empty($status)) {
        $p_city_sql .= " AND status = ?";
        $p_city_params[] = $status;
    }
    $p_combined_params = array_merge($p_clause['params'], $p_city_params);
    $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status IN ('active', 'in_progress') THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed FROM projects WHERE 1=1" . $p_clause['sql'] . $p_city_sql);
    $stmt->execute($p_combined_params);
    $p_stats = $stmt->fetch();

    $kpis['total_projects'] = (int)($p_stats['total'] ?? 0);
    $kpis['active_projects'] = (int)($p_stats['active'] ?? 0);
    $kpis['completed_projects'] = (int)($p_stats['completed'] ?? 0);

    // Jobs
    $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status IN ('published', 'open') THEN 1 ELSE 0 END) as open_jobs, SUM(CASE WHEN status = 'filled' THEN 1 ELSE 0 END) as filled_jobs, SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_jobs FROM jobs WHERE 1=1" . $j_clause['sql']);
    $stmt->execute($j_clause['params']);
    $j_stats = $stmt->fetch();

    $kpis['total_jobs'] = (int)($j_stats['total'] ?? 0);
    $kpis['open_jobs'] = (int)($j_stats['open_jobs'] ?? 0);
    $kpis['filled_jobs'] = (int)($j_stats['filled_jobs'] ?? 0);
    $kpis['closed_jobs'] = (int)($j_stats['closed_jobs'] ?? 0);

    // Job Applications
    $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted, SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted, SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected, SUM(CASE WHEN status = 'withdrawn' THEN 1 ELSE 0 END) as withdrawn FROM job_applications WHERE 1=1" . $a_clause['sql']);
    $stmt->execute($a_clause['params']);
    $a_stats = $stmt->fetch();

    $kpis['total_applications'] = (int)($a_stats['total'] ?? 0);
    $kpis['shortlisted_applications'] = (int)($a_stats['shortlisted'] ?? 0);
    $kpis['accepted_applications'] = (int)($a_stats['accepted'] ?? 0);
    $kpis['rejected_applications'] = (int)($a_stats['rejected'] ?? 0);
    $kpis['withdrawn_applications'] = (int)($a_stats['withdrawn'] ?? 0);
    $kpis['acceptance_rate'] = ($kpis['total_applications'] > 0) ? round(($kpis['accepted_applications'] / $kpis['total_applications']) * 100, 1) : 0;

    // Contracts
    $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled, SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected, SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired FROM contracts WHERE 1=1" . $c_clause['sql']);
    $stmt->execute($c_clause['params']);
    $c_stats = $stmt->fetch();

    $kpis['total_contracts'] = (int)($c_stats['total'] ?? 0);
    $kpis['active_contracts'] = (int)($c_stats['active'] ?? 0);
    $kpis['completed_contracts'] = (int)($c_stats['completed'] ?? 0);
    $kpis['contract_completion_rate'] = ($kpis['total_contracts'] > 0) ? round(($kpis['completed_contracts'] / $kpis['total_contracts']) * 100, 1) : 0;

    // Attendance
    $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late, SUM(CASE WHEN status = 'incomplete' THEN 1 ELSE 0 END) as incomplete, SUM(hours_worked) as total_hours FROM attendance WHERE 1=1" . $att_clause['sql']);
    $stmt->execute($att_clause['params']);
    $att_stats = $stmt->fetch();

    $kpis['total_attendance_records'] = (int)($att_stats['total'] ?? 0);
    $kpis['present_records'] = (int)($att_stats['present'] ?? 0);
    $kpis['late_records'] = (int)($att_stats['late'] ?? 0);
    $kpis['incomplete_records'] = (int)($att_stats['incomplete'] ?? 0);
    $kpis['total_work_hours'] = (float)($att_stats['total_hours'] ?? 0);
    $kpis['avg_work_hours'] = ($kpis['total_attendance_records'] > 0) ? round($kpis['total_work_hours'] / $kpis['total_attendance_records'], 1) : 0;

    // Reviews
    $stmt = $db->query("SELECT COUNT(*) as total, AVG(rating) as avg_rating, SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as r5, SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as r4, SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as r3, SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as r2, SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as r1 FROM reviews WHERE status = 'published'");
    $r_stats = $stmt->fetch();

    $kpis['total_reviews'] = (int)($r_stats['total'] ?? 0);
    $kpis['avg_platform_rating'] = round((float)($r_stats['avg_rating'] ?? 5.0), 2);
    $kpis['ratings_breakdown'] = [
        5 => (int)($r_stats['r5'] ?? 0),
        4 => (int)($r_stats['r4'] ?? 0),
        3 => (int)($r_stats['r3'] ?? 0),
        2 => (int)($r_stats['r2'] ?? 0),
        1 => (int)($r_stats['r1'] ?? 0)
    ];

    // Project Status Distribution
    $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM projects WHERE 1=1" . $p_clause['sql'] . " GROUP BY status");
    $stmt->execute($p_clause['params']);
    $projects_by_status = $stmt->fetchAll();

    // Projects by City
    $stmt = $db->prepare("SELECT city, COUNT(*) as count FROM projects WHERE 1=1" . $p_clause['sql'] . " GROUP BY city ORDER BY count DESC LIMIT 10");
    $stmt->execute($p_clause['params']);
    $projects_by_city = $stmt->fetchAll();

    // Top Rated Workers
    $top_workers = $db->query("
        SELECT u.id, u.name, u.avatar, w.trade_title, w.rating_avg, w.reviews_count, w.city
        FROM workers w
        JOIN users u ON w.user_id = u.id
        WHERE w.reviews_count > 0 AND u.status = 'active'
        ORDER BY w.rating_avg DESC, w.reviews_count DESC
        LIMIT 5
    ")->fetchAll();

    // Top Rated Contractors
    $top_contractors = $db->query("
        SELECT u.id, u.name, u.avatar, c.company_name, c.rating_avg, c.city
        FROM contractors c
        JOIN users u ON c.user_id = u.id
        WHERE u.status = 'active'
        ORDER BY c.rating_avg DESC, c.id DESC
        LIMIT 5
    ")->fetchAll();

    return [
        'kpis' => $kpis,
        'user_growth' => get_admin_user_growth_metrics(),
        'projects_by_status' => $projects_by_status,
        'projects_by_city' => $projects_by_city,
        'top_workers' => $top_workers,
        'top_contractors' => $top_contractors
    ];
}

/**
 * Contractor Analytics Aggregations
 */
function get_contractor_analytics_data($contractor_user_id, $filters = []) {
    $db = getDB();
    $contractor_user_id = (int)$contractor_user_id;

    $range = $filters['date_range'] ?? 'all';
    $start = $filters['start_date'] ?? null;
    $end = $filters['end_date'] ?? null;
    $project_filter_id = !empty($filters['project_id']) ? (int)$filters['project_id'] : 0;
    $status_filter = $filters['status'] ?? '';

    $p_clause = get_analytics_date_clause('created_at', $range, $start, $end);
    $j_clause = get_analytics_date_clause('created_at', $range, $start, $end);

    // KPIs
    $kpis = [];

    // Contractor Projects
    $p_sql = "SELECT COUNT(*) as total, SUM(CASE WHEN status IN ('active', 'in_progress') THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed FROM projects WHERE contractor_id = ?";
    $p_params = [$contractor_user_id];
    if ($project_filter_id > 0) {
        $p_sql .= " AND id = ?";
        $p_params[] = $project_filter_id;
    }
    if (!empty($status_filter)) {
        $p_sql .= " AND status = ?";
        $p_params[] = $status_filter;
    }
    $stmt = $db->prepare($p_sql);
    $stmt->execute($p_params);
    $p_stats = $stmt->fetch();

    $kpis['total_projects'] = (int)($p_stats['total'] ?? 0);
    $kpis['active_projects'] = (int)($p_stats['active'] ?? 0);
    $kpis['completed_projects'] = (int)($p_stats['completed'] ?? 0);

    // Active Workers (distinct workers in project_members or active contracts for contractor)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT user_id) 
        FROM project_members 
        WHERE project_id IN (SELECT id FROM projects WHERE contractor_id = ?) AND status = 'active'
    ");
    $stmt->execute([$contractor_user_id]);
    $kpis['active_workers'] = (int)$stmt->fetchColumn();

    // Jobs & Applications
    $stmt = $db->prepare("
        SELECT 
            COUNT(j.id) as total_jobs,
            SUM(CASE WHEN j.status IN ('published', 'open') THEN 1 ELSE 0 END) as open_jobs,
            COUNT(ja.id) as total_applications,
            SUM(CASE WHEN ja.status = 'accepted' THEN 1 ELSE 0 END) as hired_workers
        FROM jobs j
        LEFT JOIN job_applications ja ON j.id = ja.job_id
        WHERE j.contractor_id = ?" . ($project_filter_id > 0 ? " AND j.project_id = {$project_filter_id}" : "")
    );
    $stmt->execute([$contractor_user_id]);
    $j_stats = $stmt->fetch();

    $kpis['total_jobs'] = (int)($j_stats['total_jobs'] ?? 0);
    $kpis['open_jobs'] = (int)($j_stats['open_jobs'] ?? 0);
    $kpis['total_applications'] = (int)($j_stats['total_applications'] ?? 0);
    $kpis['hired_workers'] = (int)($j_stats['hired_workers'] ?? 0);

    // Contracts
    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM contracts
        WHERE contractor_id = ?" . ($project_filter_id > 0 ? " AND project_id = {$project_filter_id}" : "")
    );
    $stmt->execute([$contractor_user_id]);
    $c_stats = $stmt->fetch();

    $kpis['active_contracts'] = (int)($c_stats['active'] ?? 0);
    $kpis['completed_contracts'] = (int)($c_stats['completed'] ?? 0);

    // Detailed Project Performance List
    $proj_sql = "
        SELECT p.*,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) as tasks_count,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status IN ('completed', 'done')) as completed_tasks_count,
            (SELECT COUNT(*) FROM milestones m WHERE m.project_id = p.id) as milestones_count,
            (SELECT COUNT(*) FROM milestones m WHERE m.project_id = p.id AND m.status = 'completed') as completed_milestones_count,
            (SELECT COUNT(DISTINCT pm.user_id) FROM project_members pm WHERE pm.project_id = p.id AND pm.status = 'active') as workers_count,
            (SELECT COALESCE(SUM(a.hours_worked), 0) FROM attendance a WHERE a.project_id = p.id) as attendance_hours
        FROM projects p
        WHERE p.contractor_id = ?
    ";
    $proj_params = [$contractor_user_id];
    if ($project_filter_id > 0) {
        $proj_sql .= " AND p.id = ?";
        $proj_params[] = $project_filter_id;
    }
    if (!empty($status_filter)) {
        $proj_sql .= " AND p.status = ?";
        $proj_params[] = $status_filter;
    }
    $proj_sql .= " ORDER BY p.progress_percent DESC, p.end_date ASC";
    $stmt = $db->prepare($proj_sql);
    $stmt->execute($proj_params);
    $project_performance = $stmt->fetchAll();

    // Job Performance List
    $job_sql = "
        SELECT j.id, j.title, j.spots_available, j.spots_filled, j.status,
            (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id) as apps_count,
            (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id AND ja.status = 'shortlisted') as shortlisted_count,
            (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id AND ja.status = 'accepted') as accepted_count,
            (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id AND ja.status = 'rejected') as rejected_count
        FROM jobs j
        WHERE j.contractor_id = ?
    ";
    $job_params = [$contractor_user_id];
    if ($project_filter_id > 0) {
        $job_sql .= " AND j.project_id = ?";
        $job_params[] = $project_filter_id;
    }
    $job_sql .= " ORDER BY j.id DESC";
    $stmt = $db->prepare($job_sql);
    $stmt->execute($job_params);
    $job_performance = $stmt->fetchAll();

    // Workforce Analytics Summary
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT t.id) as total_tasks,
            SUM(CASE WHEN t.status IN ('completed', 'done') THEN 1 ELSE 0 END) as tasks_completed,
            SUM(CASE WHEN t.due_date < CURRENT_DATE() AND t.status NOT IN ('completed', 'done') THEN 1 ELSE 0 END) as tasks_overdue
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        WHERE p.contractor_id = ?" . ($project_filter_id > 0 ? " AND p.id = {$project_filter_id}" : "")
    );
    $stmt->execute([$contractor_user_id]);
    $task_stats = $stmt->fetch();

    $workforce_analytics = [
        'active_workers' => $kpis['active_workers'],
        'tasks_assigned' => (int)($task_stats['total_tasks'] ?? 0),
        'tasks_completed' => (int)($task_stats['tasks_completed'] ?? 0),
        'tasks_overdue' => (int)($task_stats['tasks_overdue'] ?? 0),
    ];

    return [
        'kpis' => $kpis,
        'project_performance' => $project_performance,
        'job_performance' => $job_performance,
        'workforce_analytics' => $workforce_analytics
    ];
}

/**
 * Worker Analytics Aggregations
 */
function get_worker_analytics_data($worker_user_id, $filters = []) {
    $db = getDB();
    $worker_user_id = (int)$worker_user_id;

    $range = $filters['date_range'] ?? '30days';
    $start = $filters['start_date'] ?? null;
    $end = $filters['end_date'] ?? null;
    $project_filter_id = !empty($filters['project_id']) ? (int)$filters['project_id'] : 0;

    $att_clause = get_analytics_date_clause('attendance_date', $range, $start, $end);

    // KPIs
    $kpis = [];

    // Assigned projects
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT pm.project_id) as total,
            SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM project_members pm
        JOIN projects p ON pm.project_id = p.id
        WHERE pm.user_id = ? AND pm.status = 'active'
    ");
    $stmt->execute([$worker_user_id]);
    $p_stats = $stmt->fetch();

    $kpis['assigned_projects'] = (int)($p_stats['total'] ?? 0);
    $kpis['completed_projects'] = (int)($p_stats['completed'] ?? 0);

    // Tasks assigned to worker
    $t_sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('pending', 'todo', 'in_progress', 'review', 'blocked') THEN 1 ELSE 0 END) as active_tasks,
            SUM(CASE WHEN status IN ('completed', 'done') THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN due_date < CURRENT_DATE() AND status NOT IN ('completed', 'done') THEN 1 ELSE 0 END) as overdue_tasks
        FROM tasks
        WHERE assigned_to_worker_id = ?
    ";
    $t_params = [$worker_user_id];
    if ($project_filter_id > 0) {
        $t_sql .= " AND project_id = ?";
        $t_params[] = $project_filter_id;
    }
    $stmt = $db->prepare($t_sql);
    $stmt->execute($t_params);
    $t_stats = $stmt->fetch();

    $kpis['total_tasks'] = (int)($t_stats['total'] ?? 0);
    $kpis['active_tasks'] = (int)($t_stats['active_tasks'] ?? 0);
    $kpis['completed_tasks'] = (int)($t_stats['completed_tasks'] ?? 0);
    $kpis['overdue_tasks'] = (int)($t_stats['overdue_tasks'] ?? 0);
    $kpis['task_completion_rate'] = ($kpis['total_tasks'] > 0) ? round(($kpis['completed_tasks'] / $kpis['total_tasks']) * 100, 1) : 0;

    // Attendance
    $att_sql = "
        SELECT 
            COUNT(*) as days_present,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(hours_worked) as total_hours
        FROM attendance
        WHERE worker_id = ?" . $att_clause['sql'];
    $att_params = array_merge([$worker_user_id], $att_clause['params']);
    if ($project_filter_id > 0) {
        $att_sql .= " AND project_id = ?";
        $att_params[] = $project_filter_id;
    }
    $stmt = $db->prepare($att_sql);
    $stmt->execute($att_params);
    $att_stats = $stmt->fetch();

    $kpis['attendance_days'] = (int)($att_stats['days_present'] ?? 0);
    $kpis['late_days'] = (int)($att_stats['late_days'] ?? 0);
    $kpis['total_hours'] = (float)($att_stats['total_hours'] ?? 0);
    $kpis['avg_daily_hours'] = ($kpis['attendance_days'] > 0) ? round($kpis['total_hours'] / $kpis['attendance_days'], 1) : 0;

    // Contracts & Ratings
    $stmt = $db->prepare("SELECT rating_avg, reviews_count FROM workers WHERE user_id = ?");
    $stmt->execute([$worker_user_id]);
    $w_profile = $stmt->fetch();

    $kpis['rating_avg'] = round((float)($w_profile['rating_avg'] ?? 5.0), 2);
    $kpis['reviews_count'] = (int)($w_profile['reviews_count'] ?? 0);

    // Contracts
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM contracts WHERE worker_id = ?
    ");
    $stmt->execute([$worker_user_id]);
    $c_stats = $stmt->fetch();

    $kpis['total_contracts'] = (int)($c_stats['total'] ?? 0);
    $kpis['active_contracts'] = (int)($c_stats['active'] ?? 0);
    $kpis['completed_contracts'] = (int)($c_stats['completed'] ?? 0);

    // Attendance Trend (Group by attendance_date for chart)
    $trend_sql = "
        SELECT attendance_date, SUM(hours_worked) as hours, status
        FROM attendance
        WHERE worker_id = ?" . $att_clause['sql'];
    $trend_params = array_merge([$worker_user_id], $att_clause['params']);
    if ($project_filter_id > 0) {
        $trend_sql .= " AND project_id = ?";
        $trend_params[] = $project_filter_id;
    }
    $trend_sql .= " GROUP BY attendance_date ORDER BY attendance_date ASC LIMIT 30";
    $stmt = $db->prepare($trend_sql);
    $stmt->execute($trend_params);
    $attendance_trend = $stmt->fetchAll();

    // Task status breakdown
    $t_break_sql = "SELECT status, COUNT(*) as count FROM tasks WHERE assigned_to_worker_id = ? GROUP BY status";
    $stmt = $db->prepare($t_break_sql);
    $stmt->execute([$worker_user_id]);
    $task_status_breakdown = $stmt->fetchAll();

    return [
        'kpis' => $kpis,
        'attendance_trend' => $attendance_trend,
        'task_status_breakdown' => $task_status_breakdown
    ];
}

/**
 * Client Analytics Aggregations
 */
function get_client_analytics_data($client_user_id, $filters = []) {
    $db = getDB();
    $client_user_id = (int)$client_user_id;

    $project_filter_id = !empty($filters['project_id']) ? (int)$filters['project_id'] : 0;

    // Client Projects
    $p_sql = "
        SELECT p.*, u.name as contractor_name, c.company_name as contractor_company,
            (SELECT COUNT(*) FROM milestones m WHERE m.project_id = p.id) as milestones_count,
            (SELECT COUNT(*) FROM milestones m WHERE m.project_id = p.id AND m.status = 'completed') as completed_milestones_count,
            (SELECT COUNT(*) FROM milestones m WHERE m.project_id = p.id AND m.status IN ('pending', 'in_progress')) as upcoming_milestones_count,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) as tasks_count,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status IN ('completed', 'done')) as completed_tasks_count,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status NOT IN ('completed', 'done', 'cancelled')) as pending_tasks_count
        FROM projects p
        LEFT JOIN users u ON p.contractor_id = u.id
        LEFT JOIN contractors c ON c.user_id = u.id
        WHERE p.client_id = ?
    ";
    $p_params = [$client_user_id];
    if ($project_filter_id > 0) {
        $p_sql .= " AND p.id = ?";
        $p_params[] = $project_filter_id;
    }
    $p_sql .= " ORDER BY p.id DESC";
    $stmt = $db->prepare($p_sql);
    $stmt->execute($p_params);
    $projects = $stmt->fetchAll();

    // KPIs
    $kpis = [
        'total_projects' => count($projects),
        'active_projects' => 0,
        'completed_projects' => 0,
        'on_hold_projects' => 0,
        'avg_progress_percent' => 0,
        'completed_milestones' => 0,
        'upcoming_milestones' => 0,
        'pending_tasks' => 0
    ];

    $total_progress_sum = 0;
    foreach ($projects as $p) {
        if (in_array($p['status'], ['active', 'in_progress'])) {
            $kpis['active_projects']++;
        } elseif ($p['status'] === 'completed') {
            $kpis['completed_projects']++;
        } elseif ($p['status'] === 'on_hold') {
            $kpis['on_hold_projects']++;
        }
        $total_progress_sum += (int)$p['progress_percent'];
        $kpis['completed_milestones'] += (int)$p['completed_milestones_count'];
        $kpis['upcoming_milestones'] += (int)$p['upcoming_milestones_count'];
        $kpis['pending_tasks'] += (int)$p['pending_tasks_count'];
    }

    if ($kpis['total_projects'] > 0) {
        $kpis['avg_progress_percent'] = round($total_progress_sum / $kpis['total_projects'], 1);
    }

    return [
        'kpis' => $kpis,
        'projects' => $projects
    ];
}
