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
        $uploadDir = __DIR__ . '/../AI_Module/temp_uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'upload_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $targetFile = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $imageWebPath = 'AI_Module/temp_uploads/' . $filename;
        }
    }

    $insertedId = null;
    if ($conn) {
        $status = 'active';
        if ($reportType === 'found') {
            $stmt = $conn->prepare("INSERT INTO found_reports (user_id, category, description, location, image_path, status) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("isssss", $userId, $category, $description, $location, $imageWebPath, $status);
                $stmt->execute();
                $insertedId = $stmt->insert_id;
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO lost_reports (user_id, category, description, location, status) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issss", $userId, $category, $description, $location, $status);
                $stmt->execute();
                $insertedId = $stmt->insert_id;
                $stmt->close();
            }
        }
    }

    logAccess($userId, "Report Created ($reportType)", "Category: $category");

    sendJsonResponse(true, ucfirst($reportType) . " report saved to database successfully!", [
        'id' => $insertedId,
        'report_type' => $reportType,
        'category' => $category,
        'location' => $location,
        'image_url' => $imageWebPath
    ]);
}

// ── 2. List Reports from Database ────────────────────────
if ($action === 'list' || $method === 'GET') {
    $type = strtolower(trim($_GET['type'] ?? 'all'));
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $reports = [];

    if ($conn) {
        if ($type === 'all' || $type === 'lost') {
            $stmt = $conn->prepare("SELECT id, user_id, category, description, location, status, created_at, 'lost' as report_type FROM lost_reports ORDER BY id DESC LIMIT ?");
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
            $stmt = $conn->prepare("SELECT id, user_id, category, description, location, image_path, status, created_at, 'found' as report_type FROM found_reports ORDER BY id DESC LIMIT ?");
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
