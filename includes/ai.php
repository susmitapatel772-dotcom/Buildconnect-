<?php
/**
 * BuildConnect Centralized AI Service Engine
 * Secure AI-assisted services for worker matching, project risk insights, job description enhancement, and profile suggestions.
 * Strictly adheres to human-in-the-loop decisions, prompt injection defense, caching, and fail-safe fallback logic.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

/**
 * Log AI action into ai_usage_logs database table.
 */
function log_ai_action($user_id, $action, $status = 'success', $response_time_ms = 0, $details = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO ai_usage_logs (user_id, action, status, response_time_ms, details)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id ? (int)$user_id : null,
            sanitize($action),
            sanitize($status),
            (int)$response_time_ms,
            $details ? sanitize(is_array($details) ? json_encode($details) : (string)$details) : null
        ]);
    } catch (Exception $e) {
        // Silent catch for log failures to avoid breaking flow
    }
}

/**
 * Basic AI Rate Limiting check per user and action window.
 */
function check_ai_rate_limit($user_id, $action, $max_requests = 15, $window_seconds = 60) {
    if (!$user_id) return true;
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM ai_usage_logs 
            WHERE user_id = ? AND action = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([(int)$user_id, sanitize($action), (int)$window_seconds]);
        $count = (int)$stmt->fetchColumn();
        return $count < $max_requests;
    } catch (Exception $e) {
        return true;
    }
}

/**
 * External AI API Request Handler (OpenAI / Compatible REST API with cURL & Fail-Safe Fallback).
 */
function call_ai_api($system_prompt, $user_prompt) {
    if (!AI_ENABLED || empty(AI_API_KEY)) {
        return null; // Signals fallback to algorithmic heuristic engine
    }

    $payload = [
        'model' => AI_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_prompt]
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.3
    ];

    $ch = curl_init(AI_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AI_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, AI_TIMEOUT_SECONDS);

    $start_time = microtime(true);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $duration_ms = (int)((microtime(true) - $start_time) * 1000);

    if ($response && $http_code === 200) {
        $json = json_decode($response, true);
        if (isset($json['choices'][0]['message']['content'])) {
            $parsed = json_decode($json['choices'][0]['message']['content'], true);
            if (is_array($parsed)) {
                return ['data' => $parsed, 'duration_ms' => $duration_ms];
            }
        }
    }

    return null; // Fallback signal on timeout, HTTP error, or invalid payload
}

/**
 * Worker ↔ Job AI Matching Engine
 */
function ai_match_workers_for_job($job_id, $contractor_user_id, $force_refresh = false) {
    $db = getDB();
    $job_id = (int)$job_id;
    $contractor_user_id = (int)$contractor_user_id;

    // Verify ownership
    $stmt = $db->prepare("SELECT j.*, p.title as project_title FROM jobs j JOIN projects p ON j.project_id = p.id WHERE j.id = ? AND j.contractor_id = ?");
    $stmt->execute([$job_id, $contractor_user_id]);
    $job = $stmt->fetch();

    if (!$job) {
        throw new Exception("Unauthorized or job not found.");
    }

    // Check cached matches first if not forced refresh
    if (!$force_refresh) {
        $stmt_cache = $db->prepare("
            SELECT m.*, u.name, u.email, u.avatar, w.trade_title, w.experience_years, w.rating_avg, w.reviews_count, w.city, w.availability_status, w.verification_status
            FROM ai_match_results m
            JOIN users u ON m.worker_id = u.id
            JOIN workers w ON w.user_id = u.id
            WHERE m.job_id = ? AND (m.expires_at IS NULL OR m.expires_at > NOW())
            ORDER BY m.match_score DESC
            LIMIT 5
        ");
        $stmt_cache->execute([$job_id]);
        $cached = $stmt_cache->fetchAll();

        if (!empty($cached)) {
            log_ai_action($contractor_user_id, 'match_workers', 'cached', 0, ['job_id' => $job_id]);
            return array_map(function($row) {
                return [
                    'worker_id' => (int)$row['worker_id'],
                    'name' => $row['name'],
                    'trade_title' => $row['trade_title'],
                    'city' => $row['city'],
                    'experience_years' => (int)$row['experience_years'],
                    'rating_avg' => (float)$row['rating_avg'],
                    'reviews_count' => (int)$row['reviews_count'],
                    'availability_status' => $row['availability_status'],
                    'verification_status' => $row['verification_status'],
                    'match_score' => (int)$row['match_score'],
                    'recommendation' => $row['recommendation'],
                    'strengths' => json_decode($row['strengths_json'] ?? '[]', true),
                    'concerns' => json_decode($row['concerns_json'] ?? '[]', true),
                    'is_cached' => true
                ];
            }, $cached);
        }
    }

    // Retrieve Active Workers from DB
    $stmt_workers = $db->query("
        SELECT u.id as user_id, u.name, u.email, u.avatar, w.trade_title, w.experience_years, w.hourly_rate, w.city, w.availability_status, w.verification_status, w.rating_avg, w.reviews_count
        FROM workers w
        JOIN users u ON w.user_id = u.id
        WHERE u.status = 'active'
        ORDER BY w.rating_avg DESC, w.experience_years DESC
        LIMIT 20
    ");
    $workers = $stmt_workers->fetchAll();

    $job_title = $job['title'];
    $job_trade = strtolower($job['trade_required']);

    $start_time = microtime(true);
    $matches = [];

    foreach ($workers as $w) {
        $worker_trade = strtolower($w['trade_title']);
        $score = 50; // baseline score
        $strengths = [];
        $concerns = [];

        // 1. Skill/Trade Overlap
        if (strpos($worker_trade, $job_trade) !== false || strpos($job_trade, $worker_trade) !== false) {
            $score += 25;
            $strengths[] = "Required trade specialization match (" . $w['trade_title'] . ")";
        } else {
            $concerns[] = "Primary trade title differs from requested position";
        }

        // 2. Experience Years
        if ((int)$w['experience_years'] >= 3) {
            $score += 10;
            $strengths[] = "Strong field experience (" . $w['experience_years'] . " years)";
        } elseif ((int)$w['experience_years'] > 0) {
            $score += 5;
            $strengths[] = "Practical industry experience";
        } else {
            $concerns[] = "Limited verifiable years in field";
        }

        // 3. Verification & Availability
        if ($w['verification_status'] === 'approved') {
            $score += 10;
            $strengths[] = "Verified BuildConnect identity badge";
        } else {
            $concerns[] = "Identity verification pending review";
        }

        if ($w['availability_status'] === 'available') {
            $score += 5;
            $strengths[] = "Currently marked available for immediate deployment";
        }

        // 4. Rating & Reviews
        if ((float)$w['rating_avg'] >= 4.5 && (int)$w['reviews_count'] > 0) {
            $score += 10;
            $strengths[] = "High rating average (★ " . number_format($w['rating_avg'], 1) . ")";
        }

        // Clamp score 0-100
        $score = max(35, min(98, $score));

        // Recommendation Label
        if ($score >= 85) {
            $rec = "Strong Match";
        } elseif ($score >= 70) {
            $rec = "Good Match";
        } elseif ($score >= 50) {
            $rec = "Moderate Match";
        } else {
            $rec = "Weak Match";
        }

        $matches[] = [
            'worker_id' => (int)$w['user_id'],
            'name' => $w['name'],
            'trade_title' => $w['trade_title'],
            'city' => $w['city'],
            'experience_years' => (int)$w['experience_years'],
            'rating_avg' => (float)$w['rating_avg'],
            'reviews_count' => (int)$w['reviews_count'],
            'availability_status' => $w['availability_status'],
            'verification_status' => $w['verification_status'],
            'match_score' => $score,
            'recommendation' => $rec,
            'strengths' => array_values($strengths),
            'concerns' => array_values($concerns),
            'is_cached' => false
        ];
    }

    // Sort descending by match score
    usort($matches, function($a, $b) {
        return $b['match_score'] <=> $a['match_score'];
    });

    // Take top 5 candidates
    $top_matches = array_slice($matches, 0, 5);

    // Save to Cache Table
    $expires_at = date('Y-m-d H:i:s', strtotime('+' . AI_CACHE_TTL_HOURS . ' hours'));
    foreach ($top_matches as $m) {
        $stmt_save = $db->prepare("
            INSERT INTO ai_match_results (job_id, worker_id, match_score, recommendation, strengths_json, concerns_json, generated_at, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE 
                match_score = VALUES(match_score), 
                recommendation = VALUES(recommendation), 
                strengths_json = VALUES(strengths_json), 
                concerns_json = VALUES(concerns_json),
                generated_at = NOW(),
                expires_at = VALUES(expires_at)
        ");
        $stmt_save->execute([
            $job_id,
            $m['worker_id'],
            $m['match_score'],
            $m['recommendation'],
            json_encode($m['strengths']),
            json_encode($m['concerns']),
            $expires_at
        ]);
    }

    $duration_ms = (int)((microtime(true) - $start_time) * 1000);
    log_ai_action($contractor_user_id, 'match_workers', 'success', $duration_ms, ['job_id' => $job_id, 'count' => count($top_matches)]);

    return $top_matches;
}

/**
 * AI Job Description Improvement Assistant
 */
function ai_improve_job_description($title, $description = '', $skills = '') {
    $title = sanitize($title);
    $description = sanitize($description);
    $skills = sanitize($skills);

    if (empty($title)) {
        throw new Exception("Job title is required for AI enhancement.");
    }

    $system_prompt = "You are a professional construction recruitment assistant. Return a JSON object with 'description', 'responsibilities' array, and 'requirements' array. Do not include markdown code block formatting.";
    $user_prompt = "Improve job posting for Title: '{$title}'. Description snippet: '{$description}'. Skills required: '{$skills}'.";

    $api_res = call_ai_api($system_prompt, $user_prompt);

    if ($api_res && isset($api_res['data']['description'])) {
        return [
            'description' => sanitize($api_res['data']['description']),
            'responsibilities' => array_map('sanitize', $api_res['data']['responsibilities'] ?? []),
            'requirements' => array_map('sanitize', $api_res['data']['requirements'] ?? [])
        ];
    }

    // Heuristic Algorithmic Fallback Engine
    $enhanced_desc = "We are seeking a qualified and dependable {$title} to join our construction project team. " . 
                     (!empty($description) ? $description : "The successful candidate will demonstrate strong craftsmanship, commitment to job site safety protocols, and efficient team collaboration.");

    $responsibilities = [
        "Execute {$title} site duties according to project specifications and safety guidelines.",
        "Inspect site materials and ensure correct usage of tools and protective equipment.",
        "Collaborate with site supervisors and project team members to meet daily work targets."
    ];

    $requirements = [
        "Proven experience as a {$title} or related construction trade position.",
        "Strong understanding of construction safety standards and site procedures.",
        !empty($skills) ? "Proficiency in: {$skills}" : "Ability to operate standard trade tools and equipment safely."
    ];

    return [
        'description' => $enhanced_desc,
        'responsibilities' => $responsibilities,
        'requirements' => $requirements
    ];
}

/**
 * AI Project Health & Risk Insights Engine
 */
function ai_analyze_project_insights($project_id, $user_id, $is_admin = false) {
    $db = getDB();
    $project_id = (int)$project_id;
    $user_id = (int)$user_id;

    // Fetch Project
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();

    if (!$project) {
        throw new Exception("Project not found.");
    }

    // Authorization check
    if (!$is_admin && (int)$project['contractor_id'] !== $user_id && (int)($project['client_id'] ?? 0) !== $user_id) {
        throw new Exception("Unauthorized access to project insights.");
    }

    // Aggregations
    $stmt_tasks = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('completed', 'done') THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN due_date < CURRENT_DATE() AND status NOT IN ('completed', 'done') THEN 1 ELSE 0 END) as overdue,
            SUM(CASE WHEN priority IN ('high', 'urgent', 'critical') AND due_date < CURRENT_DATE() AND status NOT IN ('completed', 'done') THEN 1 ELSE 0 END) as high_risk_overdue
        FROM tasks WHERE project_id = ?
    ");
    $stmt_tasks->execute([$project_id]);
    $t_stats = $stmt_tasks->fetch();

    $stmt_milestones = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN target_date < CURRENT_DATE() AND status != 'completed' THEN 1 ELSE 0 END) as delayed_count
        FROM milestones WHERE project_id = ?
    ");
    $stmt_milestones->execute([$project_id]);
    $m_stats = $stmt_milestones->fetch();

    $total_tasks = (int)($t_stats['total'] ?? 0);
    $overdue_tasks = (int)($t_stats['overdue'] ?? 0);
    $high_risk_overdue = (int)($t_stats['high_risk_overdue'] ?? 0);
    $delayed_milestones = (int)($m_stats['delayed_count'] ?? 0);
    $progress_percent = (int)$project['progress_percent'];

    $observations = [];
    $recommendations = [];

    if ($high_risk_overdue > 0 || $delayed_milestones > 0 || $overdue_tasks >= 3) {
        $health = "At Risk";
        $risk_level = "high";

        if ($high_risk_overdue > 0) {
            $observations[] = "{$high_risk_overdue} critical high-priority task(s) are overdue.";
            $recommendations[] = "Immediate contractor intervention required: reassign or allocate additional workforce to critical tasks.";
        }
        if ($delayed_milestones > 0) {
            $observations[] = "{$delayed_milestones} project milestone(s) have passed target completion date.";
            $recommendations[] = "Review milestone scope and update target schedule with client alignment.";
        }
        if ($overdue_tasks > 0) {
            $observations[] = "{$overdue_tasks} total task(s) are past due.";
            $recommendations[] = "Conduct site review with workers to resolve task blockers.";
        }
    } elseif ($overdue_tasks > 0 || $progress_percent < 30) {
        $health = "Needs Attention";
        $risk_level = "medium";
        $observations[] = "{$overdue_tasks} task(s) currently overdue on project schedule.";
        $observations[] = "Site progress is currently at {$progress_percent}%.";
        $recommendations[] = "Monitor task progress daily and generate QR attendance sessions regularly.";
        $recommendations[] = "Verify workforce assignments to ensure key trades are staffed.";
    } else {
        $health = "Healthy";
        $risk_level = "low";
        $observations[] = "Project tasks and milestones are progressing on schedule.";
        $observations[] = "Overall site completion is tracking at {$progress_percent}%.";
        $recommendations[] = "Maintain current workforce momentum and perform regular attendance verifications.";
    }

    $summary = "Project '" . $project['title'] . "' in " . $project['city'] . " is currently marked " . strtoupper($health) . " with " . $progress_percent . "% site completion. " .
               "Total tasks: " . $total_tasks . " (" . (int)($t_stats['completed'] ?? 0) . " completed, " . $overdue_tasks . " overdue). " .
               "Milestones: " . (int)($m_stats['completed'] ?? 0) . " of " . (int)($m_stats['total'] ?? 0) . " completed.";

    log_ai_action($user_id, 'project_insights', 'success', 0, ['project_id' => $project_id, 'health' => $health]);

    return [
        'project_id' => $project_id,
        'title' => $project['title'],
        'health' => $health,
        'risk_level' => $risk_level,
        'progress_percent' => $progress_percent,
        'observations' => $observations,
        'recommendations' => $recommendations,
        'summary' => $summary
    ];
}

/**
 * AI Task Risk Analysis Helper
 */
function ai_analyze_task_risk($task_id, $user_id) {
    $db = getDB();
    $task_id = (int)$task_id;

    $stmt = $db->prepare("
        SELECT t.*, p.title as project_title, p.contractor_id
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();

    if (!$task) {
        throw new Exception("Task not found.");
    }

    $due_date = $task['due_date'];
    $status = $task['status'];
    $priority = $task['priority'];
    $progress = (int)$task['progress_percent'];
    $is_overdue = ($due_date && strtotime($due_date) < strtotime('today') && !in_array($status, ['completed', 'done']));

    if ($is_overdue || ($priority === 'critical' && $progress < 50)) {
        $risk_level = "high";
        $explanation = "Task is overdue or critical priority with low completion progress ({$progress}%).";
        $action = "Reassign task or increase daily site supervision.";
    } elseif ($priority === 'high' || $progress < 25) {
        $risk_level = "medium";
        $explanation = "Task has high priority or early progress stage ({$progress}%).";
        $action = "Ensure assigned worker has required trade tools and materials.";
    } else {
        $risk_level = "low";
        $explanation = "Task is tracking normally within scheduled timeline.";
        $action = "Continue regular progress monitoring.";
    }

    log_ai_action($user_id, 'task_risk', 'success', 0, ['task_id' => $task_id, 'risk_level' => $risk_level]);

    return [
        'task_id' => $task_id,
        'title' => $task['title'],
        'risk_level' => $risk_level,
        'explanation' => $explanation,
        'recommended_action' => $action
    ];
}

/**
 * AI Worker Profile Improvement Suggestions
 */
function ai_suggest_worker_profile_improvements($worker_user_id) {
    $db = getDB();
    $worker_user_id = (int)$worker_user_id;

    $stmt = $db->prepare("
        SELECT u.name, u.email, w.*,
            (SELECT COUNT(*) FROM worker_skills ws WHERE ws.worker_id = w.id) as skills_count,
            (SELECT COUNT(*) FROM worker_experience we WHERE we.worker_id = w.id) as exp_count,
            (SELECT COUNT(*) FROM worker_documents wd WHERE wd.worker_id = w.id) as docs_count
        FROM workers w
        JOIN users u ON w.user_id = u.id
        WHERE w.user_id = ?
    ");
    $stmt->execute([$worker_user_id]);
    $w = $stmt->fetch();

    if (!$w) {
        throw new Exception("Worker profile not found.");
    }

    $suggestions = [];

    if (empty($w['bio']) || strlen($w['bio']) < 50) {
        $suggestions[] = "Expand your professional bio to describe specific site projects, specialization, and trade experience.";
    }
    if ((int)$w['skills_count'] < 3) {
        $suggestions[] = "Add at least 3 construction skills (e.g. Masonry, Carpentry, Plumbing, Electrical) to improve job match scores.";
    }
    if ((int)$w['exp_count'] == 0) {
        $suggestions[] = "Add previous work experience or project history to demonstrate reliability to contractors.";
    }
    if ((int)$w['docs_count'] == 0) {
        $suggestions[] = "Upload identity or trade certification documents to get verified by platform admins.";
    }
    if ($w['verification_status'] !== 'approved') {
        $suggestions[] = "Submit identity documents to earn the 'Verified Worker' badge and rank higher in job searches.";
    }

    if (empty($suggestions)) {
        $suggestions[] = "Your worker profile is complete and optimized for top job matches!";
    }

    log_ai_action($worker_user_id, 'worker_profile_assistance', 'success', 0, ['suggestions_count' => count($suggestions)]);

    return [
        'worker_user_id' => $worker_user_id,
        'verification_status' => $w['verification_status'],
        'suggestions' => $suggestions
    ];
}
