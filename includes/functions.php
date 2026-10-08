<?php
/**
 * BuildConnect Common Utility Functions
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

// Configure session parameters before session start
if (session_status() === PHP_SESSION_NONE) {
    $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (ob_get_level() === 0) {
    ob_start();
}

/**
 * Sanitize text input safely
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

/**
 * Alias for HTML escaping
 */
function e($string) {
    return sanitize($string);
}

/**
 * Format currency amount in Indian Rupees (₹)
 */
function format_inr($amount, $decimals = 0) {
    return format_currency($amount, '₹', $decimals);
}

/**
 * Redirect to specified path
 */
function redirect($path) {
    if (ob_get_level() > 0) {
        @ob_end_clean();
    }
    $url = (strpos($path, 'http') === 0) ? $path : BASE_URL . '/' . ltrim($path, '/');
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    } else {
        echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
        exit();
    }
}

/**
 * CSRF Token Generator
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden CSRF form field
 */
function csrf_field() {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set session flash message
 */
function set_flash_message($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type'    => $type
    ];
}

/**
 * Retrieve and clear session flash message
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Format currency in Indian Rupee (INR - ₹) with Indian digit grouping
 */
function format_currency($amount, $symbol = '₹', $decimals = 2) {
    if ($symbol === '$') {
        $symbol = '₹';
    }
    $amount = (float)$amount;
    $is_negative = $amount < 0;
    $amount = abs($amount);

    $parts = explode('.', number_format($amount, $decimals, '.', ''));
    $integer_part = $parts[0];
    $decimal_part = isset($parts[1]) && $decimals > 0 ? '.' . $parts[1] : '';

    if (strlen($integer_part) > 3) {
        $last_three = substr($integer_part, -3);
        $remaining = substr($integer_part, 0, -3);
        $remaining_formatted = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $remaining);
        $formatted_integer = $remaining_formatted . ',' . $last_three;
    } else {
        $formatted_integer = $integer_part;
    }

    return ($is_negative ? '-' : '') . $symbol . $formatted_integer . $decimal_part;
}

function formatCurrencyINR($amount, $decimals = 2) {
    return format_currency($amount, '₹', $decimals);
}

/**
 * Format date display
 */
function format_date($date_string, $format = 'M d, Y') {
    if (!$date_string) return 'N/A';
    return date($format, strtotime($date_string));
}

/**
 * Format date & time display
 */
function format_datetime($datetime_string, $format = 'M d, Y h:i A') {
    if (!$datetime_string) return 'N/A';
    return date($format, strtotime($datetime_string));
}

/**
 * Log System & User Activity
 */
function log_activity($user_id, $action, $details = '', $entity_type = null, $entity_id = null) {
    try {
        $db = getDB();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            sanitize($action),
            $entity_type ? sanitize($entity_type) : null,
            $entity_id ? (int)$entity_id : null,
            sanitize($details),
            $ip_address
        ]);
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

function logActivity($user_id, $action, $details = '', $entity_type = null, $entity_id = null) {
    log_activity($user_id, $action, $details, $entity_type, $entity_id);
}

/**
 * Return Bootstrap badge class for status values
 */
function get_status_badge_class($status) {
    switch (strtolower((string)$status)) {
        case 'active':
        case 'approved':
        case 'accepted':
        case 'completed':
        case 'present':
        case 'done':
            return 'bg-success';
        case 'in_progress':
        case 'open':
        case 'shortlisted':
        case 'planning':
            return 'bg-primary';
        case 'pending':
        case 'todo':
        case 'on_hold':
        case 'review':
            return 'bg-warning text-dark';
        case 'rejected':
        case 'closed':
        case 'cancelled':
        case 'suspended':
        case 'blocked':
        case 'delayed':
        case 'overdue':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

/**
 * Return Bootstrap badge class for priority levels
 */
function get_priority_badge_class($priority) {
    switch (strtolower((string)$priority)) {
        case 'critical':
        case 'urgent':
            return 'bg-danger text-white fw-bold';
        case 'high':
            return 'bg-warning text-dark fw-bold';
        case 'medium':
            return 'bg-info text-dark';
        case 'low':
        default:
            return 'bg-secondary text-light';
    }
}

/**
 * Check deadline status (Upcoming, Due Today, Overdue, Completed)
 */
function get_deadline_status($due_date, $status) {
    if (in_array(strtolower((string)$status), ['completed', 'done'])) {
        return ['label' => 'Completed', 'class' => 'text-success', 'badge' => 'bg-success'];
    }
    if (empty($due_date)) {
        return ['label' => 'No Deadline', 'class' => 'text-muted', 'badge' => 'bg-secondary'];
    }

    $today = date('Y-m-d');
    $due = date('Y-m-d', strtotime($due_date));

    if ($due < $today) {
        return ['label' => 'Overdue (' . format_date($due_date) . ')', 'class' => 'text-danger fw-bold', 'badge' => 'bg-danger'];
    } elseif ($due === $today) {
        return ['label' => 'Due Today', 'class' => 'text-warning fw-bold', 'badge' => 'bg-warning text-dark'];
    } else {
        return ['label' => 'Due ' . format_date($due_date), 'class' => 'text-muted', 'badge' => 'bg-dark border border-secondary text-light'];
    }
}

/**
 * Calculate and synchronize Milestone progress based on linked tasks
 */
function calculateMilestoneProgress($milestone_id) {
    if (!$milestone_id) return 0;
    try {
        $db = getDB();

        // Calculate average task progress for this milestone
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_tasks, AVG(progress_percent) as avg_progress 
            FROM tasks 
            WHERE milestone_id = ?
        ");
        $stmt->execute([(int)$milestone_id]);
        $row = $stmt->fetch();

        if ($row && $row['total_tasks'] > 0) {
            $progress = round((float)$row['avg_progress']);
            $progress = max(0, min(100, $progress));

            // Determine milestone status
            $ms_stmt = $db->prepare("SELECT target_date, status FROM milestones WHERE id = ?");
            $ms_stmt->execute([(int)$milestone_id]);
            $ms = $ms_stmt->fetch();

            $new_status = $ms['status'] ?? 'pending';
            if ($progress == 100) {
                $new_status = 'completed';
            } elseif ($progress > 0 && $new_status !== 'completed') {
                $new_status = 'in_progress';
            }
            if ($new_status !== 'completed' && !empty($ms['target_date']) && strtotime($ms['target_date']) < strtotime(date('Y-m-d'))) {
                $new_status = 'delayed';
            }

            $up_stmt = $db->prepare("UPDATE milestones SET progress_percent = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $up_stmt->execute([$progress, $new_status, (int)$milestone_id]);
            return $progress;
        } else {
            $ms_stmt = $db->prepare("SELECT progress_percent FROM milestones WHERE id = ?");
            $ms_stmt->execute([(int)$milestone_id]);
            return (int)$ms_stmt->fetchColumn();
        }
    } catch (Exception $e) {
        error_log("calculateMilestoneProgress failed: " . $e->getMessage());
        return 0;
    }
}

/**
 * Calculate and synchronize Project progress based on milestones & tasks
 */
function calculateProjectProgress($project_id) {
    if (!$project_id) return 0;
    try {
        $db = getDB();

        // Task progress average
        $t_stmt = $db->prepare("SELECT COUNT(*) as t_count, AVG(progress_percent) as t_avg FROM tasks WHERE project_id = ?");
        $t_stmt->execute([(int)$project_id]);
        $t_row = $t_stmt->fetch();

        // Milestone progress average
        $m_stmt = $db->prepare("SELECT COUNT(*) as m_count, AVG(progress_percent) as m_avg FROM milestones WHERE project_id = ?");
        $m_stmt->execute([(int)$project_id]);
        $m_row = $m_stmt->fetch();

        $has_tasks = ($t_row && $t_row['t_count'] > 0);
        $has_milestones = ($m_row && $m_row['m_count'] > 0);

        $progress = 0;
        if ($has_tasks && $has_milestones) {
            $progress = round(((float)$t_row['t_avg'] + (float)$m_row['m_avg']) / 2);
        } elseif ($has_tasks) {
            $progress = round((float)$t_row['t_avg']);
        } elseif ($has_milestones) {
            $progress = round((float)$m_row['m_avg']);
        } else {
            $p_stmt = $db->prepare("SELECT progress_percent FROM projects WHERE id = ?");
            $p_stmt->execute([(int)$project_id]);
            return (int)$p_stmt->fetchColumn();
        }

        $progress = max(0, min(100, $progress));

        $new_status = 'in_progress';
        if ($progress == 100) {
            $new_status = 'completed';
        }

        $up_stmt = $db->prepare("UPDATE projects SET progress_percent = ?, updated_at = NOW() WHERE id = ?");
        $up_stmt->execute([$progress, (int)$project_id]);

        return $progress;
    } catch (Exception $e) {
        error_log("calculateProjectProgress failed: " . $e->getMessage());
        return 0;
    }
}

/**
 * Format work duration into readable "8h 32m" style
 */
function format_work_hours($hours) {
    $total_minutes = round((float)$hours * 60);
    if ($total_minutes <= 0) return '0h 0m';
    $h = floor($total_minutes / 60);
    $m = $total_minutes % 60;
    return "{$h}h {$m}m";
}

/**
 * Automatically expire attendance sessions older than expires_at timestamp
 */
function expire_old_attendance_sessions() {
    try {
        $db = getDB();
        $db->exec("UPDATE attendance_sessions SET status = 'expired' WHERE status = 'active' AND expires_at <= NOW()");
    } catch (Exception $e) {
        error_log("expire_old_attendance_sessions failed: " . $e->getMessage());
    }
}

/**
 * Automatically mark past un-checked-out attendance as 'incomplete'
 */
function update_incomplete_past_attendance() {
    try {
        $db = getDB();
        $db->exec("UPDATE attendance SET status = 'incomplete' WHERE check_out_time IS NULL AND attendance_date < CURRENT_DATE()");
    } catch (Exception $e) {
        error_log("update_incomplete_past_attendance failed: " . $e->getMessage());
    }
}

/**
 * Generate unique human-readable contract number (e.g. BC-2026-000001)
 */
function generate_contract_number($db = null) {
    if (!$db) {
        $db = getDB();
    }
    $year = date('Y');
    do {
        $stmt = $db->query("SELECT MAX(id) as max_id FROM contracts");
        $max_id = (int)$stmt->fetchColumn();
        $next = $max_id + 1;
        $num = 'BC-' . $year . '-' . str_pad($next, 6, '0', STR_PAD_LEFT);

        $chk = $db->prepare("SELECT COUNT(*) FROM contracts WHERE contract_number = ?");
        $chk->execute([$num]);
        $exists = (int)$chk->fetchColumn();
        if (!$exists) {
            return $num;
        }
        $next++;
    } while ($exists);
    return 'BC-' . $year . '-' . str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Calculate detailed user rating summary from published reviews
 */
function calculate_user_rating_summary($user_id) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_reviews,
            COALESCE(AVG(rating), 5.0) as avg_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as star_5,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as star_4,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as star_3,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as star_2,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as star_1
        FROM reviews
        WHERE reviewee_id = ? AND status = 'published'
    ");
    $stmt->execute([(int)$user_id]);
    $row = $stmt->fetch();

    $total = (int)($row['total_reviews'] ?? 0);
    $avg = round((float)($row['avg_rating'] ?? 5.0), 2);
    if ($total == 0) {
        $avg = 5.00;
    }

    $star5 = (int)($row['star_5'] ?? 0);
    $star4 = (int)($row['star_4'] ?? 0);
    $star3 = (int)($row['star_3'] ?? 0);
    $star2 = (int)($row['star_2'] ?? 0);
    $star1 = (int)($row['star_1'] ?? 0);

    return [
        'avg_rating' => $avg,
        'total_reviews' => $total,
        'star_5_count' => $star5,
        'star_4_count' => $star4,
        'star_3_count' => $star3,
        'star_2_count' => $star2,
        'star_1_count' => $star1,
        'star_5_pct' => $total > 0 ? round(($star5 / $total) * 100) : 0,
        'star_4_pct' => $total > 0 ? round(($star4 / $total) * 100) : 0,
        'star_3_pct' => $total > 0 ? round(($star3 / $total) * 100) : 0,
        'star_2_pct' => $total > 0 ? round(($star2 / $total) * 100) : 0,
        'star_1_pct' => $total > 0 ? round(($star1 / $total) * 100) : 0,
    ];
}

/**
 * Recalculate and update cached rating in worker or contractor tables
 */
function update_user_rating_cache($user_id) {
    try {
        $db = getDB();
        $summary = calculate_user_rating_summary($user_id);
        
        $db->prepare("UPDATE workers SET rating_avg = ?, reviews_count = ? WHERE user_id = ?")
           ->execute([$summary['avg_rating'], $summary['total_reviews'], $user_id]);

        $db->prepare("UPDATE contractors SET rating_avg = ? WHERE user_id = ?")
           ->execute([$summary['avg_rating'], $user_id]);
    } catch (Exception $e) {
        error_log("update_user_rating_cache failed: " . $e->getMessage());
    }
}

/**
 * Render star icons HTML for rating
 */
function render_star_rating($rating, $show_number = true) {
    $rating = max(1.0, min(5.0, (float)$rating));
    $html = '<div class="d-inline-flex align-items-center text-warning me-2">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $html .= '<i class="fa-solid fa-star"></i>';
        } elseif ($rating >= ($i - 0.5)) {
            $html .= '<i class="fa-solid fa-star-half-stroke"></i>';
        } else {
            $html .= '<i class="fa-regular fa-star opacity-25"></i>';
        }
    }
    $html .= '</div>';
    if ($show_number) {
        $html .= '<span class="fw-bold text-white small">' . number_format($rating, 1) . '</span>';
    }
    return $html;
}

/**
 * Check if a contract is expired based on end_date
 */
function is_contract_expired($contract) {
    if (!$contract || empty($contract['end_date'])) return false;
    if ($contract['status'] === 'completed' || $contract['status'] === 'cancelled') return false;
    return (strtotime($contract['end_date']) < strtotime(date('Y-m-d')));
}

/**
 * Create a new notification with duplicate prevention
 */
function create_notification($user_id, $title, $message, $type = 'SYSTEM', $link = null, $related_type = null, $related_id = null) {
    try {
        $db = getDB();
        $user_id = (int)$user_id;
        $title = sanitize($title);
        $message = sanitize($message);
        $type = strtoupper(sanitize($type));

        // Duplicate prevention: avoid duplicate notifications created within the last 60 seconds
        $dup_stmt = $db->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id = ? AND type = ? AND title = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
        ");
        $dup_stmt->execute([$user_id, $type, $title]);
        if ((int)$dup_stmt->fetchColumn() > 0) {
            return false;
        }

        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, related_type, related_id, link, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([
            $user_id,
            $title,
            $message,
            $type,
            $related_type ? sanitize($related_type) : null,
            $related_id ? (int)$related_id : null,
            $link ? sanitize($link) : null
        ]);

        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("create_notification failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count for a user
 */
function get_unread_notification_count($user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([(int)$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Fetch notifications for a user with pagination and filter support
 */
function get_user_notifications($user_id, $limit = 20, $offset = 0, $type_filter = null, $read_filter = null) {
    try {
        $db = getDB();
        $where = ["user_id = ?"];
        $params = [(int)$user_id];

        if (!empty($type_filter) && $type_filter !== 'all') {
            $where[] = "type = ?";
            $params[] = strtoupper(sanitize($type_filter));
        }

        if ($read_filter === 'unread') {
            $where[] = "is_read = 0";
        } elseif ($read_filter === 'read') {
            $where[] = "is_read = 1";
        }

        $where_sql = implode(" AND ", $where);
        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE {$where_sql} 
            ORDER BY id DESC 
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("get_user_notifications failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark single notification as read
 */
function mark_notification_as_read($notification_id, $user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
        return $stmt->execute([(int)$notification_id, (int)$user_id]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Mark all notifications as read for current user
 */
function mark_all_notifications_as_read($user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        return $stmt->execute([(int)$user_id]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Validate latitude (-90 to 90) and longitude (-180 to 180)
 */
function validate_coordinates($lat, $lng) {
    if (!is_numeric($lat) || !is_numeric($lng)) return false;
    $lat = (float)$lat;
    $lng = (float)$lng;
    return ($lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0);
}

/**
 * Get Google Maps API Key or empty fallback
 */
function get_google_maps_api_key() {
    return defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
}

/**
 * Safely mask sensitive identity document numbers (e.g. Aadhaar, PAN, Licence, Passport)
 */
function mask_identity_number($number) {
    $raw = trim((string)$number);
    if ($raw === '') {
        return 'N/A';
    }
    $clean = preg_replace('/[^a-zA-Z0-9]/', '', $raw);
    $len = strlen($clean);
    
    if ($len <= 4) {
        return str_repeat('X', $len);
    }
    
    $last_four = substr($clean, -4);
    
    if ($len === 12) {
        return 'XXXX-XXXX-' . $last_four;
    }
    if ($len === 10) {
        return 'XXXXXX' . $last_four;
    }
    
    return str_repeat('X', max(0, $len - 4)) . $last_four;
}





