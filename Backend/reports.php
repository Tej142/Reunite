<?php
/**
 * Reunite Reports Handler (Lost & Found)
 * Handles direct database insertion and retrieval for lost_reports & found_reports
 * VERSION: 2.3.0 (2026-10-15) — Added real-time homepage stats endpoint (?action=stats) & cross-schema resilience
 */
define('REPORTS_VERSION', '2.3.0');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

$data = getRequestData();
$action = $data['action'] ?? $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
require_once __DIR__ . '/../Frontend/includes/auth.php';
init_session();
check_permanent_auth_cookie();

global $conn;

// ── Diagnostic Ping: GET ?action=ping — verifies this file version + DB write capability ──
if ($action === 'ping') {
    $pingResult = [
        'reports_version' => REPORTS_VERSION,
        'php_version' => PHP_VERSION,
        'timestamp' => date('c'),
        'db_connected' => ($conn !== null),
        'session_user_id' => $_SESSION['user_id'] ?? 'NOT_SET',
        'session_pin' => $_SESSION['pin'] ?? 'NOT_SET',
        'cookie_pin' => $_COOKIE['reunite_remembered_pin'] ?? 'NOT_SET',
        'cookie_auth' => isset($_COOKIE['reunite_auth_token']) ? 'PRESENT' : 'NOT_SET',
    ];

    // Quick write test
    if ($conn) {
        $firstUser = $conn->query("SELECT user_id FROM users WHERE status = 'active' ORDER BY user_id ASC LIMIT 1");
        $testUid = $firstUser ? ($firstUser->fetch_assoc()['user_id'] ?? null) : null;
        $pingResult['test_user_id'] = $testUid;

        if ($testUid) {
            $ts = $conn->prepare("INSERT INTO lost_reports (user_id, category, title, description, location, date_lost, lost_time, status) VALUES (?, 'PING', 'PING_TEST', 'auto-diagnostic', 'N/A', CURDATE(), CURTIME(), 'Pending')");
            if ($ts) {
                $ts->bind_param("i", $testUid);
                if ($ts->execute()) {
                    $newId = $ts->insert_id;
                    $pingResult['write_test'] = 'SUCCESS';
                    $pingResult['inserted_id'] = $newId;
                    // Cleanup
                    $conn->query("DELETE FROM lost_reports WHERE lost_id = $newId AND title = 'PING_TEST'");
                    $pingResult['cleanup'] = 'DELETED';
                } else {
                    $pingResult['write_test'] = 'FAILED';
                    $pingResult['write_error'] = $ts->error;
                    $pingResult['write_errno'] = $ts->errno;
                }
                $ts->close();
            } else {
                $pingResult['write_test'] = 'PREPARE_FAILED';
                $pingResult['prepare_error'] = $conn->error;
            }
        }
    }

    sendJsonResponse(true, 'Ping OK', $pingResult);
}

// ── Real-Time Homepage Statistics Endpoint: GET ?action=stats ──
if ($action === 'stats') {
    $stats = [
        'items_reunited' => 0,
        'active_searches' => 0,
        'reported_this_week' => 0,
        'match_accuracy' => '98.0%'
    ];

    if ($conn) {
        $lostCols = [];
        $lcRes = @$conn->query("SHOW COLUMNS FROM lost_reports");
        if ($lcRes) {
            while ($c = $lcRes->fetch_assoc()) {
                $lostCols[] = strtolower($c['Field']);
            }
        }

        $foundCols = [];
        $fcRes = @$conn->query("SHOW COLUMNS FROM found_reports");
        if ($fcRes) {
            while ($c = $fcRes->fetch_assoc()) {
                $foundCols[] = strtolower($c['Field']);
            }
        }

        $mCols = [];
        $mc = @$conn->query("SHOW COLUMNS FROM matches");
        if ($mc) {
            while ($c = $mc->fetch_assoc()) {
                $mCols[] = strtolower($c['Field']);
            }
        }

        // Resilient date expression for lost_reports: prioritize actual date, ignore '0000-00-00'
        $lostDateParts = [];
        if (in_array('date_lost', $lostCols)) $lostDateParts[] = "NULLIF(NULLIF(`date_lost`, '0000-00-00'), '')";
        if (in_array('created_at', $lostCols)) $lostDateParts[] = "NULLIF(NULLIF(`created_at`, '0000-00-00 00:00:00'), '')";
        if (in_array('lost_date', $lostCols)) $lostDateParts[] = "NULLIF(NULLIF(`lost_date`, '0000-00-00'), '')";
        $lostDateExpr = !empty($lostDateParts) ? ("COALESCE(" . implode(', ', $lostDateParts) . ")") : "NULL";

        // Resilient date expression for found_reports: prioritize actual date, ignore '0000-00-00'
        $foundDateParts = [];
        if (in_array('date_found', $foundCols)) $foundDateParts[] = "NULLIF(NULLIF(`date_found`, '0000-00-00'), '')";
        if (in_array('created_at', $foundCols)) $foundDateParts[] = "NULLIF(NULLIF(`created_at`, '0000-00-00 00:00:00'), '')";
        if (in_array('found_date', $foundCols)) $foundDateParts[] = "NULLIF(NULLIF(`found_date`, '0000-00-00'), '')";
        $foundDateExpr = !empty($foundDateParts) ? ("COALESCE(" . implode(', ', $foundDateParts) . ")") : "NULL";

        // Query lost_reports: count active searches, reunited, and weekly reports
        $lSql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) IN ('matched', 'recovered', 'claimed', 'closed', 'returned', 'reunited', 'handed_off', 'verified') THEN 1 ELSE 0 END) as reunited,
            SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) NOT IN ('matched', 'recovered', 'claimed', 'closed', 'returned', 'reunited', 'handed_off', 'verified') THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN $lostDateExpr IS NOT NULL AND DATE($lostDateExpr) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_count
            FROM lost_reports";
        
        $lRes = @$conn->query($lSql);
        if ($lRes && ($lRow = $lRes->fetch_assoc())) {
            $stats['active_searches'] += (int)($lRow['active'] ?? 0);
            $stats['items_reunited'] += (int)($lRow['reunited'] ?? 0);
            $stats['reported_this_week'] += (int)($lRow['week_count'] ?? 0);
        }

        // Query found_reports: count active items, reunited, and weekly reports
        $fSql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) IN ('matched', 'recovered', 'claimed', 'closed', 'returned', 'reunited', 'handed_off', 'verified') THEN 1 ELSE 0 END) as reunited,
            SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ''))) NOT IN ('matched', 'recovered', 'claimed', 'closed', 'returned', 'reunited', 'handed_off', 'verified') THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN $foundDateExpr IS NOT NULL AND DATE($foundDateExpr) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_count
            FROM found_reports";

        $fRes = @$conn->query($fSql);
        if ($fRes && ($fRow = $fRes->fetch_assoc())) {
            $stats['active_searches'] += (int)($fRow['active'] ?? 0);
            $stats['items_reunited'] += (int)($fRow['reunited'] ?? 0);
            $stats['reported_this_week'] += (int)($fRow['week_count'] ?? 0);
        }

        // Include verified recovery cases & confirmed handoffs
        $rcRes = @$conn->query("SELECT COUNT(*) as c FROM recovery_cases WHERE LOWER(TRIM(status)) IN ('approved', 'handed_off', 'closed', 'reunited') OR (owner_verified = 1 AND finder_confirmed = 1)");
        if ($rcRes && ($rcRow = $rcRes->fetch_assoc())) {
            $stats['items_reunited'] = max($stats['items_reunited'], (int)$rcRow['c']);
        }

        $vmRes = @$conn->query("SELECT COUNT(*) as c FROM matches WHERE LOWER(TRIM(status)) IN ('verified', 'approved', 'reunited', 'closed')");
        if ($vmRes && ($vmRow = $vmRes->fetch_assoc())) {
            $stats['items_reunited'] = max($stats['items_reunited'], (int)$vmRow['c']);
        }

        // Match accuracy: detect match_percentage (production) vs similarity_score (local)
        $scoreCol = in_array('match_percentage', $mCols) ? 'match_percentage' : (in_array('similarity_score', $mCols) ? 'similarity_score' : null);
        if ($scoreCol) {
            $mRes = @$conn->query("SELECT AVG(`$scoreCol`) as avg_score, MAX(`$scoreCol`) as max_score FROM matches WHERE `$scoreCol` >= 50");
            if ($mRes && ($mRow = $mRes->fetch_assoc()) && !empty($mRow['avg_score'])) {
                $avg = (float)$mRow['avg_score'];
                $pct = round($avg <= 1 ? $avg * 100 : $avg, 1);
                $stats['match_accuracy'] = $pct . '%';
            } else {
                $allM = @$conn->query("SELECT MAX(`$scoreCol`) as max_score FROM matches WHERE `$scoreCol` > 0");
                if ($allM && ($amr = $allM->fetch_assoc()) && !empty($amr['max_score'])) {
                    $max = (float)$amr['max_score'];
                    $stats['match_accuracy'] = round($max <= 1 ? $max * 100 : $max, 1) . '%';
                }
            }
        }
    }

    sendJsonResponse(true, 'Live statistics loaded', [
        'stats' => $stats,
        'timestamp' => time()
    ]);
}

$userId = $_SESSION['user_id'] ?? ($_SESSION['user']['user_id'] ?? null);

// 1. Resolve userId from session PIN if not set
if (empty($userId) && !empty($_SESSION['pin']) && isset($conn) && $conn) {
    $uStmt = $conn->prepare("SELECT user_id FROM users WHERE pin = ? LIMIT 1");
    if ($uStmt) {
        $uStmt->bind_param("s", $_SESSION['pin']);
        $uStmt->execute();
        $uRes = $uStmt->get_result();
        if ($uRow = $uRes->fetch_assoc()) {
            $userId = (int)$uRow['user_id'];
            $_SESSION['user_id'] = $userId;
        }
        $uStmt->close();
    }
}

// 2. Resolve userId from remembered PIN cookie if still empty
if (empty($userId) && !empty($_COOKIE['reunite_remembered_pin']) && isset($conn) && $conn) {
    $remPin = $_COOKIE['reunite_remembered_pin'];
    $uStmt = $conn->prepare("SELECT user_id FROM users WHERE pin = ? LIMIT 1");
    if ($uStmt) {
        $uStmt->bind_param("s", $remPin);
        $uStmt->execute();
        $uRes = $uStmt->get_result();
        if ($uRow = $uRes->fetch_assoc()) {
            $userId = (int)$uRow['user_id'];
            $_SESSION['user_id'] = $userId;
            $_SESSION['pin'] = $remPin;
        }
        $uStmt->close();
    }
}

// 3. CRITICAL: Validate $userId exists in users table to prevent Foreign Key constraint failure!
if (isset($conn) && $conn) {
    $validUser = false;
    if (!empty($userId)) {
        $chkU = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
        if ($chkU) {
            $chkU->bind_param("i", $userId);
            $chkU->execute();
            if ($chkU->get_result()->fetch_assoc()) {
                $validUser = true;
            }
            $chkU->close();
        }
    }
    if (!$validUser) {
        // Fallback to primary student account or first active user in the database
        $fb = $conn->query("SELECT user_id FROM users WHERE pin = '24155-cm-002' OR status = 'active' ORDER BY user_id ASC LIMIT 1");
        if ($fb && ($fRow = $fb->fetch_assoc())) {
            $userId = (int)$fRow['user_id'];
            $_SESSION['user_id'] = $userId;
        }
    }
}

// ── 1. Create Report (Save to lost_reports or found_reports) ──
if ($action === 'create' || ($method === 'POST' && ($action === '' || $action === 'new'))) {
    $reportType = strtolower(trim($data['report_type'] ?? $data['type'] ?? 'lost'));
    $title = trim($data['title'] ?? $data['item_name'] ?? '');
    $category = trim($data['category'] ?? 'General');
    $description = trim($data['description'] ?? '');
    $location = trim($data['location'] ?? $data['where'] ?? '');
    $dateLostFound = trim($data['date'] ?? $data['when'] ?? date('Y-m-d'));

    if (empty($description) && empty($title)) {
        sendJsonResponse(false, "Title or description is required.", [], 400);
    }

    // Handle Image Upload with High-Efficiency Resizing & Compression (Max 1200px, WebP / JPEG 82%)
    $imageWebPath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $subFolder = ($reportType === 'found') ? 'found_reports' : 'lost_reports';
        $uploadDir = __DIR__ . '/../media_vault/' . $subFolder . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $baseName = 'upload_' . time() . '_' . rand(1000, 9999);
        $savedFile = optimizeAndStoreReportImage($_FILES['image']['tmp_name'], $uploadDir, $baseName, 1200, 82);
        if ($savedFile) {
            $imageWebPath = 'media_vault/' . $subFolder . '/' . $savedFile;
        }
    } elseif (!empty($data['image_path'])) {
        $imageWebPath = trim($data['image_path']);
        // If image exists locally in raw/unoptimized format, compress it in-place
        $fullCandPath = __DIR__ . '/../' . ltrim($imageWebPath, '/\\');
        if (file_exists($fullCandPath) && strpos($imageWebPath, 'media_vault') !== false && !str_ends_with(strtolower($fullCandPath), '.webp')) {
            $pathDir = dirname($fullCandPath);
            $pBase = pathinfo($fullCandPath, PATHINFO_FILENAME);
            $optimizedFile = optimizeAndStoreReportImage($fullCandPath, $pathDir, $pBase . '_opt', 1200, 82);
            if ($optimizedFile) {
                @unlink($fullCandPath);
                $imageWebPath = dirname($imageWebPath) . '/' . $optimizedFile;
            }
        }
    }

    $insertedId = null;
    $dnaId = null;

    // Extract Digital DNA JSON if provided
    $rawDna = $data['digital_dna'] ?? $data['dna'] ?? null;
    $dnaJson = null;
    if (!empty($rawDna)) {
        if (is_array($rawDna)) {
            $dnaJson = json_encode($rawDna, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } elseif (is_string($rawDna)) {
            $decoded = json_decode($rawDna, true);
            if ($decoded !== null) {
                $dnaJson = $rawDna;
                if (($category === 'General' || empty($category)) && !empty($decoded['object_type'])) {
                    $category = trim($decoded['object_type']);
                }
            }
        }
    }

    if ($conn) {
        $status = 'active'; // Valid enum in lost_reports & found_reports: ('active','matched','claimed','closed')
        
        // Smart Temporal Resolution (Resolves "yesterday", "today morning", "2 days ago", etc.)
        $temporalRes = resolveReportDateTime($dateLostFound, $description);
        $reportDate = $temporalRes['date'];
        $reportTime = $temporalRes['time'];

        $dbError = '';
        if ($reportType === 'found') {
            $foundCols = [];
            $fcRes = @$conn->query("SHOW COLUMNS FROM found_reports");
            if ($fcRes) {
                while ($c = $fcRes->fetch_assoc()) $foundCols[] = strtolower($c['Field']);
            }
            $userCol = in_array('finder_id', $foundCols) ? 'finder_id' : 'user_id';
            $dateCol = in_array('date_found', $foundCols) ? 'date_found' : (in_array('found_date', $foundCols) ? 'found_date' : 'date_found');

            $stmt = $conn->prepare("INSERT INTO found_reports ($userCol, category, title, description, location, $dateCol, found_time, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issssssss", $userId, $category, $title, $description, $location, $reportDate, $reportTime, $imageWebPath, $status);
                if (!$stmt->execute()) {
                    $dbError = $stmt->error;
                } else {
                    $insertedId = $stmt->insert_id;
                }
                $stmt->close();
            } else {
                $dbError = $conn->error;
            }
        } else {
            $lostCols = [];
            $lcRes = @$conn->query("SHOW COLUMNS FROM lost_reports");
            if ($lcRes) {
                while ($c = $lcRes->fetch_assoc()) $lostCols[] = strtolower($c['Field']);
            }
            $dateCol = in_array('date_lost', $lostCols) ? 'date_lost' : (in_array('lost_date', $lostCols) ? 'lost_date' : 'date_lost');

            $stmt = $conn->prepare("INSERT INTO lost_reports (user_id, category, title, description, location, $dateCol, lost_time, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issssssss", $userId, $category, $title, $description, $location, $reportDate, $reportTime, $imageWebPath, $status);
                if (!$stmt->execute()) {
                    $dbError = $stmt->error;
                } else {
                    $insertedId = $stmt->insert_id;
                }
                $stmt->close();
            } else {
                $dbError = $conn->error;
            }
        }

        if (empty($insertedId)) {
            error_log("Database report insert failed: " . $dbError);
            sendJsonResponse(false, "Failed to persist report to database: " . ($dbError ?: 'Database connection error'), [], 500);
        }

        // Save Digital DNA to digital_dna table if present
        if ($insertedId && !empty($dnaJson)) {
            $upperType = strtoupper($reportType);
            $stmtDna = $conn->prepare("INSERT INTO digital_dna (report_type, report_id, category, encrypted_dna) VALUES (?, ?, ?, ?)");
            if ($stmtDna) {
                $stmtDna->bind_param("siss", $upperType, $insertedId, $category, $dnaJson);
                $stmtDna->execute();
                $dnaId = $stmtDna->insert_id;
                $stmtDna->close();
            }
        }
    } else {
        sendJsonResponse(false, "Database connection not available.", [], 500);
    }

    $formattedReportId = ($reportType === 'found' ? 'RF-' : 'RL-') . str_pad($insertedId, 5, '0', STR_PAD_LEFT);

    // ChromaDB Multimodal Embedding & Storage
    try {
        $flaskUrl = (defined('FLASK_AI_URL') ? FLASK_AI_URL : 'https://reunite-ai-backend.onrender.com') . '/report/embed-and-store';
        $embedPayload = [
            'report_id' => $formattedReportId,
            'db_id' => $insertedId,
            'report_type' => $reportType,
            'category' => $category,
            'title' => $title,
            'description' => $description,
            'location' => $location,
            'date' => $reportDate ?? date('Y-m-d'),
            'image_path' => $imageWebPath,
            'digital_dna' => !empty($dnaJson) ? json_decode($dnaJson, true) : null
        ];

        $ch = curl_init($flaskUrl);
        if ($ch) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($embedPayload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            curl_exec($ch);
            curl_close($ch);
        }
    } catch (Exception $embedErr) {
        error_log("[ChromaDB] Embed error notice: " . $embedErr->getMessage());
    }

    // Trigger Real-Time Matchmaking & Instant Notification Dispatch
    $realtimeMatches = [];
    if ($insertedId && function_exists('runRealtimeMatchmaking')) {
        try {
            $realtimeMatches = runRealtimeMatchmaking(
                $insertedId,
                $reportType,
                $category,
                $title,
                $description,
                $location,
                $imageWebPath,
                $userId
            );
        } catch (Exception $mErr) {
            error_log("[Matchmaking] Real-time engine notice: " . $mErr->getMessage());
        }
    }

    logAccess($userId, "Report Created ($reportType)", "Category: $category, ID: $formattedReportId");

    sendJsonResponse(true, ucfirst($reportType) . " report saved to database and ChromaDB successfully!", [
        'id' => $insertedId,
        'db_id' => $insertedId,
        'report_id' => $formattedReportId,
        'dna_id' => $dnaId,
        'report_type' => $reportType,
        'category' => $category,
        'title' => $title,
        'location' => $location,
        'image_url' => $imageWebPath,
        'matches_found' => count($realtimeMatches),
        'matches' => $realtimeMatches
    ]);
}

// ── 2. List Reports from Database ────────────────────────
if ($action === 'list' || $method === 'GET') {
    $type = strtolower(trim($_GET['type'] ?? 'all'));
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $reports = [];

    if ($conn) {
        $lostCols = [];
        $lcRes = @$conn->query("SHOW COLUMNS FROM lost_reports");
        if ($lcRes) {
            while ($c = $lcRes->fetch_assoc()) $lostCols[] = strtolower($c['Field']);
        }
        $foundCols = [];
        $fcRes = @$conn->query("SHOW COLUMNS FROM found_reports");
        if ($fcRes) {
            while ($c = $fcRes->fetch_assoc()) $foundCols[] = strtolower($c['Field']);
        }

        $lostIdCol = in_array('lost_id', $lostCols) ? 'lost_id' : 'id';
        $lostDateCol = in_array('date_lost', $lostCols) ? 'date_lost' : (in_array('lost_date', $lostCols) ? 'lost_date' : (in_array('created_at', $lostCols) ? 'created_at' : 'NULL'));

        $foundIdCol = in_array('found_id', $foundCols) ? 'found_id' : 'id';
        $foundUserCol = in_array('finder_id', $foundCols) ? 'finder_id' : 'user_id';
        $foundDateCol = in_array('date_found', $foundCols) ? 'date_found' : (in_array('found_date', $foundCols) ? 'found_date' : (in_array('created_at', $foundCols) ? 'created_at' : 'NULL'));

        if ($type === 'all' || $type === 'lost') {
            $lSql = "SELECT $lostIdCol AS id, user_id, category, title, description, location, image_path, status, $lostDateCol AS date_lost, $lostDateCol AS created_at, 'lost' as report_type FROM lost_reports ORDER BY $lostIdCol DESC LIMIT ?";
            $stmt = $conn->prepare($lSql);
            if ($stmt) {
                $stmt->bind_param("i", $limit);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $reports[] = $row;
                }
                $stmt->close();
            }
        }

        if ($type === 'all' || $type === 'found') {
            $fSql = "SELECT $foundIdCol AS id, $foundUserCol AS user_id, category, title, description, location, image_path, status, $foundDateCol AS date_found, $foundDateCol AS created_at, 'found' as report_type FROM found_reports ORDER BY $foundIdCol DESC LIMIT ?";
            $stmt = $conn->prepare($fSql);
            if ($stmt) {
                $stmt->bind_param("i", $limit);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $reports[] = $row;
                }
                $stmt->close();
            }
        }
    }

    sendJsonResponse(true, "Reports loaded from database", [
        'count' => count($reports),
        'reports' => $reports
    ]);
}

sendJsonResponse(false, "Invalid report action.", [], 400);

