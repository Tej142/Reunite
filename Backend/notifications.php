<?php
/**
 * Reunite Real-Time Notifications API
 * Handles fetching live notifications, unread counters, and mark-as-read status
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

if (!headers_sent()) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
global $conn;

// Helper to calculate human readable time ago
if (!function_exists('format_time_ago')) {
    function format_time_ago($timestamp_str) {
        if (empty($timestamp_str)) return 'Just now';
        $time = strtotime($timestamp_str);
        if (!$time) return 'Recently';
        $diff = time() - $time;
        if ($diff < 45) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 172800) return 'Yesterday';
        return floor($diff / 86400) . 'd ago';
    }
}

// Get user ID from session
$userId = $_SESSION['user_id'] ?? null;

$data = getRequestData();
$action = $data['action'] ?? $_GET['action'] ?? 'get';

// If not logged in, return empty state safely
if (!$userId) {
    echo json_encode([
        'success' => true,
        'logged_in' => false,
        'unread_count' => 0,
        'notifications' => []
    ]);
    exit;
}

// ── 1. Mark Notification(s) as Read ───────────────────────────
if ($action === 'mark_read') {
    $notifId = $data['notification_id'] ?? $_GET['notification_id'] ?? null;
    $markAll = !empty($data['all']) || !empty($_GET['all']) || $notifId === 'all';

    if ($conn) {
        if ($markAll) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();
            }
        } elseif ($notifId) {
            $nId = (int)$notifId;
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $nId, $userId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Notification status updated'
    ]);
    exit;
}

// ── 2. Create Notification (Internal helper or API) ───────────
if ($action === 'create') {
    $targetUserId = (int)($data['user_id'] ?? $userId);
    $title = trim($data['title'] ?? 'New Notification');
    $message = trim($data['message'] ?? '');
    $link = trim($data['link'] ?? 'search.php');
    $type = trim($data['type'] ?? 'match');
    $reportType = strtoupper(trim($data['report_type'] ?? 'LOST'));
    $reportId = !empty($data['report_id']) ? (int)$data['report_id'] : null;

    if ($conn && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, report_type, report_id, type, message, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())");
        if ($stmt) {
            $stmt->bind_param("ississs", $targetUserId, $title, $reportType, $reportId, $type, $message, $link);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            echo json_encode([
                'success' => true,
                'notification_id' => $newId,
                'message' => 'Notification created successfully'
            ]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Failed to create notification']);
    exit;
}

// ── 3. Fetch Notifications for Logged-In User ──────────────────
$notifications = [];
$unreadCount = 0;

if ($conn) {
    // Unread count
    $stmtCount = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE (user_id = ? OR user_id = 0) AND is_read = 0");
    if ($stmtCount) {
        $stmtCount->bind_param("i", $userId);
        $stmtCount->execute();
        $cntRes = $stmtCount->get_result();
        if ($cntRow = $cntRes->fetch_assoc()) {
            $unreadCount = (int)$cntRow['cnt'];
        }
        $stmtCount->close();
    }

    // List latest 25 notifications
    $stmtList = $conn->prepare("SELECT notification_id, user_id, title, report_type, report_id, type, message, link, is_read, created_at FROM notifications WHERE (user_id = ? OR user_id = 0) ORDER BY notification_id DESC LIMIT 25");
    if ($stmtList) {
        $stmtList->bind_param("i", $userId);
        $stmtList->execute();
        $listRes = $stmtList->get_result();
        while ($row = $listRes->fetch_assoc()) {
            $nType = $row['type'] ?: 'match';
            $icon = $nType;

            // Strip any legacy emojis from title and message
            $emojiRegex = '/[\x{1F300}-\x{1FAFF}\x{1F000}-\x{1F2FF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200D}]/u';
            $cleanTitle = trim(preg_replace('/\s+/', ' ', preg_replace($emojiRegex, '', $row['title'] ?? '')));
            $cleanMessage = trim(preg_replace('/\s+/', ' ', preg_replace($emojiRegex, '', $row['message'] ?? '')));

            $notifications[] = [
                'id' => (int)$row['notification_id'],
                'title' => $cleanTitle ?: ($nType === 'match' ? 'New AI Match Detected!' : 'Notification'),
                'message' => $cleanMessage,
                'link' => !empty($row['link']) ? $row['link'] : 'search.php',
                'type' => $nType,
                'icon' => $icon,
                'report_type' => $row['report_type'],
                'report_id' => $row['report_id'],
                'is_read' => (int)$row['is_read'],
                'time_ago' => format_time_ago($row['created_at']),
                'created_at' => $row['created_at']
            ];
        }
        $stmtList->close();
    }
}

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'user_id' => $userId,
    'unread_count' => $unreadCount,
    'notifications' => $notifications
]);
