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
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return max(1, floor($diff / 60)) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 172800) return 'Yesterday';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M d', $time);
    }
}

// Get user ID or Admin ID from session
$userId = $_SESSION['user_id'] ?? null;
$isAdmin = !empty($_SESSION['reunite_admin_auth']) || !empty($_SESSION['admin_logged_in']);
$adminId = $_SESSION['admin_id'] ?? null;
$adminRole = $_SESSION['role'] ?? null;
$adminCampusId = $_SESSION['admin_campus_id'] ?? null;

$userCampusId = null;
if ($userId && $conn) {
    $uStmt = $conn->prepare("SELECT campus_id FROM users WHERE user_id = ? LIMIT 1");
    if ($uStmt) {
        $uStmt->bind_param("i", $userId);
        $uStmt->execute();
        $uRes = $uStmt->get_result();
        if ($uRow = $uRes->fetch_assoc()) {
            $userCampusId = !empty($uRow['campus_id']) ? (int)$uRow['campus_id'] : null;
        }
        $uStmt->close();
    }
} elseif ($isAdmin && $adminCampusId) {
    $userCampusId = (int)$adminCampusId;
}

$data = getRequestData();
$action = $data['action'] ?? $_GET['action'] ?? 'get';

// If not logged in as user or admin, return empty state safely
if (!$userId && !$isAdmin) {
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
    $link = trim($data['link'] ?? '');
    $type = trim($data['type'] ?? 'match');
    $reportType = strtoupper(trim($data['report_type'] ?? 'LOST'));
    $reportId = !empty($data['report_id']) ? (int)$data['report_id'] : 0;

    if (empty($link) && $reportId > 0) {
        $link = 'item.php?type=' . strtolower($reportType) . '&id=' . $reportId;
    }

    if ($conn && !empty($message)) {
        if (function_exists('insertSystemNotification')) {
            $newId = insertSystemNotification($targetUserId, $title, $message, $link, $reportType, $reportId, $type);
            if ($newId) {
                echo json_encode([
                    'success' => true,
                    'notification_id' => $newId,
                    'message' => 'Notification created successfully'
                ]);
                exit;
            }
        } else {
            $nowStr = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, report_type, report_id, type, message, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, ?)");
            if ($stmt) {
                $stmt->bind_param("isisss", $targetUserId, $reportType, $reportId, $type, $message, $nowStr);
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
    }

    echo json_encode(['success' => false, 'error' => 'Failed to create notification']);
    exit;
}

// ── 3. Fetch Notifications for Logged-In User ──────────────────
$notifications = [];
$unreadCount = 0;

if ($conn) {
    // Check if campus_id column exists
    $hasCampusCol = false;
    $cCols = @$conn->query("SHOW COLUMNS FROM notifications LIKE 'campus_id'");
    if ($cCols && $cCols->num_rows > 0) {
        $hasCampusCol = true;
    }

    if ($isAdmin && !$userId) {
        // Administrator notification view
        if ($userCampusId && $hasCampusCol) {
            $stmtCount = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE (campus_id = ? OR (campus_id IS NULL AND user_id = 0)) AND is_read = 0");
            if ($stmtCount) {
                $stmtCount->bind_param("i", $userCampusId);
                $stmtCount->execute();
                if ($cntRow = $stmtCount->get_result()->fetch_assoc()) $unreadCount = (int)$cntRow['cnt'];
                $stmtCount->close();
            }

            $stmtList = $conn->prepare("SELECT * FROM notifications WHERE (campus_id = ? OR (campus_id IS NULL AND user_id = 0)) ORDER BY notification_id DESC LIMIT 25");
            if ($stmtList) {
                $stmtList->bind_param("i", $userCampusId);
                $stmtList->execute();
                $listRes = $stmtList->get_result();
            }
        } else {
            // Master admin sees global notifications
            $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM notifications WHERE is_read = 0");
            if ($cntRes && $cntRow = $cntRes->fetch_assoc()) $unreadCount = (int)$cntRow['cnt'];
            $listRes = $conn->query("SELECT * FROM notifications ORDER BY notification_id DESC LIMIT 25");
        }
    } else {
        // Student user notification view
        if ($hasCampusCol && $userCampusId) {
            $stmtCount = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE (user_id = ? OR (user_id = 0 AND (campus_id IS NULL OR campus_id = ?))) AND is_read = 0");
            if ($stmtCount) {
                $stmtCount->bind_param("ii", $userId, $userCampusId);
                $stmtCount->execute();
                if ($cntRow = $stmtCount->get_result()->fetch_assoc()) $unreadCount = (int)$cntRow['cnt'];
                $stmtCount->close();
            }

            $stmtList = $conn->prepare("SELECT * FROM notifications WHERE (user_id = ? OR (user_id = 0 AND (campus_id IS NULL OR campus_id = ?))) ORDER BY notification_id DESC LIMIT 25");
            if ($stmtList) {
                $stmtList->bind_param("ii", $userId, $userCampusId);
                $stmtList->execute();
                $listRes = $stmtList->get_result();
            }
        } else {
            $stmtCount = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE (user_id = ? OR user_id = 0) AND is_read = 0");
            if ($stmtCount) {
                $stmtCount->bind_param("i", $userId);
                $stmtCount->execute();
                if ($cntRow = $stmtCount->get_result()->fetch_assoc()) $unreadCount = (int)$cntRow['cnt'];
                $stmtCount->close();
            }

            $stmtList = $conn->prepare("SELECT * FROM notifications WHERE (user_id = ? OR user_id = 0) ORDER BY notification_id DESC LIMIT 25");
            if ($stmtList) {
                $stmtList->bind_param("i", $userId);
                $stmtList->execute();
                $listRes = $stmtList->get_result();
            }
        }
    }

    if (isset($listRes) && $listRes) {
        while ($row = $listRes->fetch_assoc()) {
            $nType = $row['type'] ?: 'match';
            $icon = $nType;

            // Strip any legacy emojis from title and message
            $emojiRegex = '/[\x{1F300}-\x{1FAFF}\x{1F000}-\x{1F2FF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200D}]/u';
            $cleanTitle = trim(preg_replace('/\s+/', ' ', preg_replace($emojiRegex, '', $row['title'] ?? '')));
            $cleanMessage = trim(preg_replace('/\s+/', ' ', preg_replace($emojiRegex, '', $row['message'] ?? '')));

            // Resolve smart link directly to item.php if report_id is present
            $resolvedLink = !empty($row['link']) ? $row['link'] : '';
            $repId = (int)($row['report_id'] ?? 0);
            $rType = strtolower($row['report_type'] ?? 'found');

            if (empty($resolvedLink)) {
                // If this is a match notification, dynamically find the exact match and found item!
                if ($nType === 'match' || stripos($cleanTitle . ' ' . $cleanMessage, 'match') !== false) {
                    $extractedPct = null;
                    if (preg_match('/(\d+(?:\.\d+)?)\s*%/i', $cleanMessage, $pctMatches)) {
                        $extractedPct = (float)$pctMatches[1];
                    }

                    $matchedRow = null;
                    if ($repId > 0) {
                        $targetCol = ($rType === 'found') ? 'found_id' : 'lost_id';
                        if ($extractedPct !== null) {
                            $mStmt = $conn->prepare("SELECT match_id, found_id, lost_id FROM matches WHERE `$targetCol` = ? AND (ROUND(match_percentage) = ROUND(?) OR ROUND(match_percentage) = FLOOR(?) OR ROUND(match_percentage) = CEIL(?)) ORDER BY match_id DESC LIMIT 1");
                            if ($mStmt) {
                                $mStmt->bind_param("iddd", $repId, $extractedPct, $extractedPct, $extractedPct);
                                $mStmt->execute();
                                $mRes = $mStmt->get_result();
                                if ($mRes && ($rowM = $mRes->fetch_assoc())) {
                                    $matchedRow = $rowM;
                                }
                                $mStmt->close();
                            }
                        }

                        if (!$matchedRow) {
                            $mStmt = $conn->prepare("SELECT match_id, found_id, lost_id FROM matches WHERE `$targetCol` = ? ORDER BY match_id DESC LIMIT 1");
                            if ($mStmt) {
                                $mStmt->bind_param("i", $repId);
                                $mStmt->execute();
                                $mRes = $mStmt->get_result();
                                if ($mRes && ($rowM = $mRes->fetch_assoc())) {
                                    $matchedRow = $rowM;
                                }
                                $mStmt->close();
                            }
                        }
                    }

                    if ($matchedRow) {
                        $foundTargetId = (int)($matchedRow['found_id'] ?? 0);
                        $matchTargetId = (int)($matchedRow['match_id'] ?? 0);
                        if ($foundTargetId > 0) {
                            $resolvedLink = "item.php?type=found&id={$foundTargetId}" . ($matchTargetId > 0 ? "&match_id={$matchTargetId}" : "");
                        }
                    }
                }

                if (empty($resolvedLink) && $repId > 0) {
                    $resolvedLink = "item.php?type={$rType}&id={$repId}";
                }
                if (empty($resolvedLink)) {
                    $resolvedLink = 'search.php';
                }
            }

            $notifications[] = [
                'id' => (int)($row['notification_id'] ?? $row['id'] ?? 0),
                'title' => $cleanTitle ?: ($nType === 'match' ? 'Potential Match Detected!' : 'Notification'),
                'message' => $cleanMessage,
                'link' => $resolvedLink,
                'type' => $nType,
                'icon' => $icon,
                'report_type' => $row['report_type'] ?? 'LOST',
                'report_id' => $repId,
                'is_read' => (int)($row['is_read'] ?? 0),
                'time_ago' => format_time_ago($row['created_at'] ?? ''),
                'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s')
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
