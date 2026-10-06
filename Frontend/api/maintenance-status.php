<?php
/**
 * Public Maintenance Status API
 * GET /Frontend/api/maintenance-status.php
 * Returns: { enabled, message, eta, support_email }
 * No auth required — public endpoint.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../Backend/config/config.php';

$result = [
    'enabled'       => false,
    'message'       => null,
    'eta'           => null,
    'eta_iso'       => null,
    'support_email' => null,
];

// ── 1. Read from DB (authoritative) ─────────────────────────
if ($conn) {
    $row = null;
    $q = $conn->query("SELECT enabled, message, eta, support_email FROM maintenance_settings ORDER BY id DESC LIMIT 1");
    if ($q && $row = $q->fetch_assoc()) {
        $result['enabled']       = (bool)(int)$row['enabled'];
        $result['message']       = $row['message'] ?: null;
        $result['support_email'] = $row['support_email'] ?: null;
        if (!empty($row['eta'])) {
            // Return human-readable and ISO
            $result['eta']     = date('M j, Y · H:i T', strtotime($row['eta']));
            $result['eta_iso'] = date('c', strtotime($row['eta']));
        }
    }
}

// ── 2. Fallback: check maintenance.json if DB is down ────────
if (!$conn || !isset($row)) {
    $jsonFile = dirname(__DIR__, 2) . '/Backend/config/maintenance.json';
    if (file_exists($jsonFile)) {
        $data = @json_decode(file_get_contents($jsonFile), true);
        if ($data) {
            $result['enabled'] = !empty($data['maintenance_mode']);
            $result['message'] = $data['message'] ?? null;
            if (!empty($data['eta'])) {
                $result['eta']     = date('M j, Y · H:i T', strtotime($data['eta']));
                $result['eta_iso'] = date('c', strtotime($data['eta']));
            }
        }
    }
}

// Set 503 if maintenance is on
if ($result['enabled']) {
    http_response_code(503);
    $retryAfter = 30;
    if (!empty($result['eta_iso'])) {
        $diff = strtotime($result['eta_iso']) - time();
        if ($diff > 0) $retryAfter = min($diff, 3600);
    }
    header('Retry-After: ' . $retryAfter);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
exit;
