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
$userId = $_SESSION['user_id'] ?? 1;

global $conn;

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
        $subFolder = ($type === 'found') ? 'found_reports' : 'lost_reports';
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

        if ($reportType === 'found') {
            $stmt = $conn->prepare("INSERT INTO found_reports (user_id, category, title, description, location, date_found, found_time, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issssssss", $userId, $category, $title, $description, $location, $reportDate, $reportTime, $imageWebPath, $status);
                $stmt->execute();
                $insertedId = $stmt->insert_id;
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO lost_reports (user_id, category, title, description, location, date_lost, lost_time, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issssssss", $userId, $category, $title, $description, $location, $reportDate, $reportTime, $imageWebPath, $status);
                $stmt->execute();
                $insertedId = $stmt->insert_id;
                $stmt->close();
            }
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
    }

    $formattedReportId = ($reportType === 'found' ? 'RF-' : 'RL-') . str_pad($insertedId ?: rand(100, 999), 5, '0', STR_PAD_LEFT);

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

