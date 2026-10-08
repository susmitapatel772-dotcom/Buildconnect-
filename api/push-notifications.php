<?php
/**
 * BuildConnect Web Push Notifications API Endpoint
 * Handles subscription saving, unread polling, and test push triggers
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User session required.']);
    exit;
}

$user = get_logged_user();
$db = getDB();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'poll_unread') {
        $since = (int)($_GET['since'] ?? (time() - 60));
        
        try {
            $stmt = $db->prepare("
                SELECT id, title, message, type, link, created_at 
                FROM notifications 
                WHERE user_id = ? AND UNIX_TIMESTAMP(created_at) >= ?
                ORDER BY id DESC LIMIT 5
            ");
            $stmt->execute([$user['id'], $since]);
            $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'notifications' => $notifs,
                'server_time' => time()
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid GET action.']);
    exit;
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;

    $action = $data['action'] ?? '';

    if ($action === 'subscribe') {
        $endpoint = trim(sanitize($data['endpoint'] ?? ''));
        $user_agent = trim(sanitize($data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? ''));

        if (empty($endpoint)) {
            echo json_encode(['success' => false, 'message' => 'Push endpoint token is required.']);
            exit;
        }

        try {
            // Upsert subscription
            $checkStmt = $db->prepare("SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ?");
            $checkStmt->execute([$user['id'], $endpoint]);
            
            if (!$checkStmt->fetch()) {
                $stmt = $db->prepare("
                    INSERT INTO push_subscriptions (user_id, endpoint, user_agent, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$user['id'], $endpoint, $user_agent]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Push subscription stored successfully on localhost.',
                'endpoint' => $endpoint
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'test_push') {
        try {
            $title = "🔔 Localhost Web Push Alert";
            $message = "Test Push Notification delivered successfully to " . htmlspecialchars($user['name']) . " on localhost!";
            $link = "notifications.php";

            $notif_id = create_notification($user['id'], $title, $message, 'SYSTEM', $link);

            echo json_encode([
                'success' => true,
                'message' => 'Test push notification created and dispatched!',
                'notification' => [
                    'id' => $notif_id,
                    'title' => $title,
                    'message' => $message,
                    'link' => $link,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Push creation failed: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid POST action.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
