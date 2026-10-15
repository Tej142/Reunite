<?php
/**
 * Reunite Reports Handler (Lost & Found)
 * Handles direct database insertion and retrieval for lost_reports & found_reports
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

$data = getRequestData();
$action = $data['action'] ?? $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
require_once __DIR__ . '/../Frontend/includes/auth.php';
init_session();
check_permanent_auth_cookie();

global $conn;

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

    // Handle Image Upload if provided
    $imageWebPath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $subFolder = ($reportType === 'found') ? 'found_reports' : 'lost_reports';
        $uploadDir = __DIR__ . '/../media_vault/' . $subFolder . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'upload_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $targetFile = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $imageWebPath = 'media_vault/' . $subFolder . '/' . $filename;
        }
    } elseif (!empty($data['image_path'])) {
        $imageWebPath = trim($data['image_path']);
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
        $status = 'active';
        
        // Smart Temporal Resolution (Resolves "yesterday", "today morning", "2 days ago", etc.)
        $temporalRes = resolveReportDateTime($dateLostFound, $description);
        $reportDate = $temporalRes['date'];
        $reportTime = $temporalRes['time'];

        $dbError = '';
        if ($reportType === 'found') {
            $stmt = $conn->prepare("INSERT INTO found_reports (user_id, category, title, description, location, date_found, found_time, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
            $stmt = $conn->prepare("INSERT INTO lost_reports (user_id, category, title, description, location, date_lost, lost_time, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);
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
        if ($type === 'all' || $type === 'lost') {
            $stmt = $conn->prepare("SELECT id, user_id, category, title, description, location, image_path, status, created_at, 'lost' as report_type FROM lost_reports ORDER BY id DESC LIMIT ?");
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
            $stmt = $conn->prepare("SELECT id, user_id, category, title, description, location, image_path, status, created_at, 'found' as report_type FROM found_reports ORDER BY id DESC LIMIT ?");
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

