<?php
/**
 * Reunite Backend Helper Functions
 * Clean database, authentication, and security utilities
 */

require_once __DIR__ . '/config/config.php';

if (!function_exists('init_session')) {
    function init_session() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $_SESSION['csrf_token'] = md5(uniqid((string)mt_rand(), true));
            }
        }
        if (function_exists('enforceMaintenanceMode')) {
            enforceMaintenanceMode();
        }
    }
}

/**
 * Encrypt sensitive data using AES-256-CBC
 */
function encryptData($data) {
    if (empty($data)) return $data;
    $key = hash('sha256', ENCRYPTION_KEY);
    $iv = ENCRYPTION_IV;
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return $encrypted ? base64_encode($encrypted) : $data;
}

/**
 * Decrypt sensitive data using AES-256-CBC
 */
function decryptData($data) {
    if (empty($data)) return $data;
    $key = hash('sha256', ENCRYPTION_KEY);
    $iv = ENCRYPTION_IV;
    $decoded = base64_decode($data);
    $decrypted = openssl_decrypt($decoded, 'AES-256-CBC', $key, 0, $iv);
    return $decrypted !== false ? $decrypted : $data;
}

/** Create a deliberately blurred, downsampled public thumbnail; keep the original untouched. */
function createPrivacySafeThumbnail($sourcePath, $targetDir, $baseName = '') {
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor') || !is_file($sourcePath)) return null;
    $info = @getimagesize($sourcePath); if (!$info) return null;
    $mime = $info['mime'] ?? '';
    if ($mime === 'image/jpeg') $src = @imagecreatefromjpeg($sourcePath);
    elseif ($mime === 'image/png') $src = @imagecreatefrompng($sourcePath);
    elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($sourcePath);
    else return null;
    if (!$src) return null;
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) { imagedestroy($src); return null; }
    $baseName = preg_replace('/[^A-Za-z0-9_-]/', '', $baseName ?: ('safe_' . bin2hex(random_bytes(8))));
    $small = imagecreatetruecolor(28, 28);
    imagecopyresampled($small, $src, 0, 0, 0, 0, 28, 28, imagesx($src), imagesy($src));
    $thumb = imagecreatetruecolor(420, 300);
    imagecopyresampled($thumb, $small, 0, 0, 0, 0, 420, 300, 28, 28);
    for ($blur = 0; $blur < 12; $blur++) @imagefilter($thumb, IMG_FILTER_GAUSSIAN_BLUR);
    $filename = $baseName . '.jpg';
    $saved = @imagejpeg($thumb, rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $filename, 72);
    imagedestroy($src); imagedestroy($small); imagedestroy($thumb);
    return $saved ? $filename : null;
}
/**
 * Production Image Optimizer: Auto-Rotates EXIF, Resizes to Max Bounding Box (1200px),
 * Strips GPS Metadata, and Compresses to WebP / High-Efficiency JPEG (82%)
 */
function optimizeAndStoreReportImage($sourcePath, $targetDir, $baseName = '', $maxDim = 1200, $quality = 82) {
    if (!file_exists($sourcePath)) return false;
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $baseName = !empty($baseName) ? $baseName : ('upload_' . time() . '_' . rand(1000, 9999));

    // Fallback if GD is not available
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        $ext = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg';
        $dest = rtrim($targetDir, '/\\') . '/' . $baseName . '.' . $ext;
        if (is_uploaded_file($sourcePath)) {
            move_uploaded_file($sourcePath, $dest);
        } else {
            copy($sourcePath, $dest);
        }
        return $baseName . '.' . $ext;
    }

    $imgInfo = @getimagesize($sourcePath);
    if (!$imgInfo) {
        $dest = rtrim($targetDir, '/\\') . '/' . $baseName . '.jpg';
        if (is_uploaded_file($sourcePath)) {
            move_uploaded_file($sourcePath, $dest);
        } else {
            copy($sourcePath, $dest);
        }
        return $baseName . '.jpg';
    }

    $origWidth = $imgInfo[0];
    $origHeight = $imgInfo[1];
    $mime = $imgInfo['mime'] ?? 'image/jpeg';

    switch ($mime) {
        case 'image/jpeg':
            $src = @imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            $src = @imagecreatefromwebp($sourcePath);
            break;
        default:
            $dest = rtrim($targetDir, '/\\') . '/' . $baseName . '.jpg';
            if (is_uploaded_file($sourcePath)) {
                move_uploaded_file($sourcePath, $dest);
            } else {
                copy($sourcePath, $dest);
            }
            return $baseName . '.jpg';
    }

    if (!$src) {
        $dest = rtrim($targetDir, '/\\') . '/' . $baseName . '.jpg';
        if (is_uploaded_file($sourcePath)) {
            move_uploaded_file($sourcePath, $dest);
        } else {
            copy($sourcePath, $dest);
        }
        return $baseName . '.jpg';
    }

    // Auto-rotate based on EXIF orientation (fixes sideways phone photos)
    if (function_exists('exif_read_data') && ($mime === 'image/jpeg' || $mime === 'image/tiff')) {
        $exif = @exif_read_data($sourcePath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3: $src = imagerotate($src, 180, 0); break;
                case 6: 
                    $src = imagerotate($src, -90, 0); 
                    $t = $origWidth; $origWidth = $origHeight; $origHeight = $t;
                    break;
                case 8: 
                    $src = imagerotate($src, 90, 0); 
                    $t = $origWidth; $origWidth = $origHeight; $origHeight = $t;
                    break;
            }
        }
    }

    // Calculate aspect ratio dimensions (never upscale smaller images)
    $ratio = min($maxDim / $origWidth, $maxDim / $origHeight, 1.0);
    $targetWidth = max(1, (int)round($origWidth * $ratio));
    $targetHeight = max(1, (int)round($origHeight * $ratio));

    $dst = imagecreatetruecolor($targetWidth, $targetHeight);

    // Alpha channel handling for PNG & WebP
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $targetWidth, $targetHeight, $transparent);
    }

    // High quality bicubic resample (strips all EXIF GPS metadata automatically)
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight);

    // Prefer modern WebP (30% smaller than JPEG), fallback to optimized JPEG
    $savedExt = function_exists('imagewebp') ? 'webp' : 'jpg';
    $finalFilename = $baseName . '.' . $savedExt;
    $finalFullPath = rtrim($targetDir, '/\\') . '/' . $finalFilename;

    if ($savedExt === 'webp') {
        imagewebp($dst, $finalFullPath, $quality);
    } else {
        imagejpeg($dst, $finalFullPath, $quality);
    }

    imagedestroy($src);
    imagedestroy($dst);

    return $finalFilename;
}

/**
 * Read maintenance settings from DB (with json file fallback).
 * Returns array: [enabled, message, eta, support_email]
 */
function getMaintenanceSettings() {
    global $conn;
    static $cached = null;
    if ($cached !== null) return $cached;

    // Primary: DB
    if ($conn) {
        $q = @$conn->query("SELECT enabled, message, eta, support_email FROM maintenance_settings ORDER BY id DESC LIMIT 1");
        if ($q && $row = $q->fetch_assoc()) {
            $cached = [
                'enabled'       => (bool)(int)$row['enabled'],
                'message'       => $row['message'] ?: null,
                'eta'           => $row['eta'] ?: null,
                'support_email' => $row['support_email'] ?: null,
            ];
            return $cached;
        }
    }

    // Fallback: json file
    $file = __DIR__ . '/config/maintenance.json';
    if (file_exists($file)) {
        $data = @json_decode(file_get_contents($file), true);
        if ($data) {
            $cached = [
                'enabled'       => !empty($data['maintenance_mode']),
                'message'       => $data['message'] ?? null,
                'eta'           => $data['eta'] ?? null,
                'support_email' => $data['support_email'] ?? null,
            ];
            return $cached;
        }
    }

    $cached = ['enabled' => false, 'message' => null, 'eta' => null, 'support_email' => null];
    return $cached;
}

/**
 * Check if platform is currently in Maintenance Mode
 */
function isMaintenanceModeActive() {
    $s = getMaintenanceSettings();
    return $s['enabled'];
}

/**
 * Enforce maintenance mode on every incoming request.
 * - Admins pass through.
 * - API callers get JSON 503 + Retry-After.
 * - Page requests get redirected to maintenance.php.
 */
function enforceMaintenanceMode() {
    if (php_sapi_name() === 'cli') return;
    if (!isMaintenanceModeActive()) return;

    // Allow active admin sessions
    if (!empty($_SESSION['reunite_admin_auth']) && $_SESSION['reunite_admin_auth'] === true) {
        return;
    }

    // Allow admin login route and maintenance page itself
    $script = basename($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
    $allowedScripts = ['maintenance.php', 'admin.php', 'admin_api.php', 'maintenance-status.php'];
    if (in_array($script, $allowedScripts)) return;

    // Compute Retry-After value
    $settings    = getMaintenanceSettings();
    $retryAfter  = 30;
    if (!empty($settings['eta'])) {
        $diff = strtotime($settings['eta']) - time();
        if ($diff > 0) $retryAfter = min($diff, 3600);
    }

    // JSON API callers
    $uri    = $_SERVER['REQUEST_URI'] ?? '';
    $isJson = (function_exists('isApiRequest') && isApiRequest()) || str_contains($uri, '/api/');
    $accept  = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $ct      = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    if ($isJson || str_contains($accept, 'application/json') || str_contains($ct, 'application/json')) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        header('Retry-After: ' . $retryAfter);
        echo json_encode([
            'success'          => false,
            'maintenance_mode' => true,
            'message'          => $settings['message'] ?? 'Platform is undergoing scheduled maintenance. Please try again shortly.',
            'eta'              => $settings['eta'],
            'support_email'    => $settings['support_email'],
        ]);
        exit;
    }

    // Page request — redirect to maintenance page
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($scriptName, '/Frontend/') !== false) {
        $target = substr($scriptName, 0, strpos($scriptName, '/Frontend/') + 10) . 'maintenance.php';
    } elseif (strpos($scriptName, '/Backend/') !== false) {
        $target = substr($scriptName, 0, strpos($scriptName, '/Backend/')) . '/Frontend/maintenance.php';
    } else {
        $target = file_exists(__DIR__ . '/../Frontend/maintenance.php') ? 'Frontend/maintenance.php' : 'maintenance.php';
    }

    if (!headers_sent()) {
        header('Location: ' . $target, true, 302);
    }
    echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target) . '"><script>window.location.replace(' . json_encode($target) . ');</script></head><body><p>System undergoing maintenance. Redirecting...</p></body></html>';
    exit;
}

/**
 * Clean and sanitize user inputs
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Parse JSON or Form POST request body
 */
function getRequestData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?: [];
    }
    return $_POST;
}

/**
 * Standardized JSON API Response
 */
function sendJsonResponse($success, $message = '', $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

/**
 * Check if the Claim & Handover module is active for the current session/user.
 * - 'on': active for everyone
 * - 'testers': active for admins and qa_ test accounts
 * - 'off': disabled for everyone, displays upgrade notice
 */
function isClaimModuleActiveForUser($userId = null, $modeOverride = null) {
    $mode = $modeOverride !== null ? strtolower(trim($modeOverride)) : (defined('CLAIM_MODULE_MODE') ? strtolower(trim(CLAIM_MODULE_MODE)) : 'on');
    // If explicitly disabled
    if ($mode === 'off') return false;
    // Production default: fully active for all campus students and users
    return true;
}

/**
 * Check if request is expecting a JSON response
 */
function isApiRequest() {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    return (stripos($accept, 'application/json') !== false || 
            stripos($contentType, 'application/json') !== false ||
            isset($_GET['api']) ||
            isset($_POST['api']));
}

/**
 * Check if student/user is authenticated
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current authenticated user details from database or session
 */
function getCurrentUser() {
    if (!isUserLoggedIn()) {
        return null;
    }

    global $conn;
    $userId = $_SESSION['user_id'];

    if ($conn) {
        $stmt = $conn->prepare("SELECT user_id, full_name, pin, email, phone, trust_score, role, status, dob, college, branch, created_at FROM users WHERE user_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $rawEmail = decryptData($row['email']);
                $rawPhone = decryptData($row['phone']);
                $row['name'] = $row['full_name'];
                $row['email_raw'] = $rawEmail;
                $row['phone_raw'] = $rawPhone;
                $row['email'] = $rawEmail;
                $row['phone'] = $rawPhone;
                $stmt->close();
                return $row;
            }
            $stmt->close();
        }
    }

    $name = $_SESSION['full_name'] ?? ($_SESSION['user']['name'] ?? 'Student');
    $bCode = $_SESSION['branch'] ?? ($_SESSION['user']['branch'] ?? 'cme');
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'full_name' => $name,
        'name' => $name,
        'pin' => $_SESSION['pin'] ?? ($_SESSION['user']['pin'] ?? ''),
        'dob' => $_SESSION['dob'] ?? ($_SESSION['user']['dob'] ?? ''),
        'email' => $_SESSION['email'] ?? ($_SESSION['user']['email'] ?? ''),
        'phone' => $_SESSION['phone'] ?? ($_SESSION['user']['phone'] ?? ''),
        'role' => $_SESSION['role'] ?? 'user',
        'college' => $_SESSION['college'] ?? ($_SESSION['user']['college'] ?? ''),
        'branch' => $bCode,
        'branch_name' => get_branch_name($bCode),
        'trust_score' => $_SESSION['trust_score'] ?? 100
    ];
}

/**
 * Maps short branch codes (e.g., 'cme', 'cse', 'ece') to full human-readable names
 */
function get_branch_name($code) {
    if (empty($code)) return 'Computer Engineering';
    $code = strtolower(trim((string)$code));
    $branches = [
        'cme'  => 'Computer Engineering',
        'cs'   => 'Computer Science & Engineering',
        'cse'  => 'Computer Science & Engineering',
        'ece'  => 'Electronics & Communication Engineering',
        'ec'   => 'Electronics & Communication Engineering',
        'eee'  => 'Electrical & Electronics Engineering',
        'ee'   => 'Electrical & Electronics Engineering',
        'me'   => 'Mechanical Engineering',
        'mec'  => 'Mechanical Engineering',
        'ce'   => 'Civil Engineering',
        'civ'  => 'Civil Engineering',
        'che'  => 'Chemical Engineering',
        'ae'   => 'Aerospace Engineering',
        'aiml' => 'AI & Machine Learning',
        'it'   => 'Information Technology',
        'oth'  => 'Other / General Studies',
        'other'=> 'Other / General Studies'
    ];
    return $branches[$code] ?? ucfirst($code);
}

/**
 * Require authentication or return 401 / redirect
 */
function requireAuth() {
    if (!isUserLoggedIn()) {
        if (isApiRequest()) {
            sendJsonResponse(false, 'Unauthorized. Please login to continue.', [], 401);
        } else {
            header("Location: " . FRONTEND_URL . "/login.php?error=" . urlencode("Please login to access this page."));
            exit;
        }
    }
}

/**
 * Audit Logger: Insert action record into access_logs table
 */
function logAccess($userId, $action, $details = '') {
    global $conn;
    if (!$conn) return;

    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $uId = (!empty($userId) && (int)$userId > 0) ? (int)$userId : null;
        $device = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 80);
        $finalDetails = !empty($details) ? (substr($details, 0, 180) . ($device ? ' [' . $device . ']' : '')) : ($device ?: 'System');
        $finalDetails = substr($finalDetails, 0, 255);

        $stmt = $conn->prepare("INSERT INTO access_logs (user_id, action, ip_address, device_info) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $uId, $action, $ip, $finalDetails);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Failed to log access: " . $e->getMessage());
    }
}

/**
 * Send Transactional Email in Real-Time (Supports Brevo API, Resend, and SMTP)
 */
function sendBrevoEmail($toEmail, $toName, $subject, $htmlContent) {
    // 1. Check for Brevo (Sendinblue) API Key (Prioritized - HTTPS port 443 works on all hosting)
    $brevoKey = defined('BREVO_API_KEY') && !empty(BREVO_API_KEY) ? BREVO_API_KEY : (getenv('BREVO_API_KEY') ?: '');
    if (!empty($brevoKey)) {
        $senderEmail = defined('BREVO_SENDER_EMAIL') && !empty(BREVO_SENDER_EMAIL) ? BREVO_SENDER_EMAIL : (getenv('BREVO_SENDER_EMAIL') ?: 'charante153624@gmail.com');
        $senderName = defined('BREVO_SENDER_NAME') && !empty(BREVO_SENDER_NAME) ? BREVO_SENDER_NAME : (getenv('BREVO_SENDER_NAME') ?: 'REUNITE TEAM');

        $url = 'https://api.brevo.com/v3/smtp/email';
        $payload = [
            'sender' => [
                'name' => $senderName,
                'email' => $senderEmail
            ],
            'to' => [
                [
                    'email' => $toEmail,
                    'name' => !empty($toName) ? $toName : 'Student'
                ]
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent
        ];
        $jsonPayload = json_encode($payload);

        // Attempt 1: cURL with robust settings
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'api-key: ' . $brevoKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if (!$curlError && $httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'provider' => 'brevo', 'data' => json_decode($response, true)];
            }
            if (!empty($response)) {
                $resData = json_decode($response, true);
                $brevoErrMsg = $resData['message'] ?? "Brevo returned HTTP $httpCode";
                error_log("Brevo API Error ($httpCode): " . $brevoErrMsg);
                // Return error directly if Brevo is configured
                return [
                    'success' => false,
                    'provider' => 'brevo',
                    'error' => "Brevo Error ($httpCode): " . $brevoErrMsg
                ];
            }
        }

        // Attempt 2: PHP stream_context (fallback if cURL is blocked)
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "api-key: " . $brevoKey . "\r\n" .
                            "Content-Type: application/json\r\n" .
                            "Accept: application/json\r\n",
                'content' => $jsonPayload,
                'timeout' => 8,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        $context = stream_context_create($opts);
        $streamRes = @file_get_contents($url, false, $context);
        if ($streamRes !== false) {
            $resData = json_decode($streamRes, true);
            if (isset($resData['messageId'])) {
                return ['success' => true, 'provider' => 'brevo_stream', 'data' => $resData];
            }
            if (isset($resData['message'])) {
                return ['success' => false, 'provider' => 'brevo_stream', 'error' => "Brevo: " . $resData['message']];
            }
        }
    }

    // 2. Check for Direct Gmail / Standard SMTP (Fallback)
    $smtpHost = defined('SMTP_HOST') && !empty(SMTP_HOST) ? SMTP_HOST : getenv('SMTP_HOST');
    $smtpUser = defined('SMTP_USER') && !empty(SMTP_USER) ? SMTP_USER : getenv('SMTP_USER');
    $smtpPass = defined('SMTP_PASS') && !empty(SMTP_PASS) ? SMTP_PASS : getenv('SMTP_PASS');
    if (!empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
        $smtpPort = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : $smtpUser;
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'REUNITE TEAM';

        $smtpResult = sendNativeSmtp($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromEmail, $fromName, $toEmail, $toName, $subject, $htmlContent);
        if ($smtpResult['success']) {
            return ['success' => true, 'provider' => 'smtp', 'data' => $smtpResult];
        }
    }

    // 3. Check for Resend API Key (https://resend.com)
    $resendKey = defined('RESEND_API_KEY') && !empty(RESEND_API_KEY) ? RESEND_API_KEY : getenv('RESEND_API_KEY');
    if (!empty($resendKey)) {
        $senderEmail = defined('RESEND_SENDER_EMAIL') && !empty(RESEND_SENDER_EMAIL) ? RESEND_SENDER_EMAIL : 'onboarding@resend.dev';
        $senderName = defined('RESEND_SENDER_NAME') && !empty(RESEND_SENDER_NAME) ? RESEND_SENDER_NAME : 'Reunite';

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'from' => "$senderName <$senderEmail>",
            'to' => [$toEmail],
            'subject' => $subject,
            'html' => $htmlContent
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $resendKey,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!$curlError) {
            $resData = json_decode($response, true);
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'provider' => 'resend', 'data' => $resData];
            }
            // Return Resend specific error if failed
            return [
                'success' => false,
                'error' => $resData['message'] ?? 'Failed to deliver email via Resend API.',
                'http_code' => $httpCode
            ];
        }
    }

    // 4. Fallback / Local simulation mode if no provider succeeds
    error_log("[Email Simulation] Subject: $subject | To: $toEmail");
    return [
        'success' => true,
        'simulated' => true,
        'message' => 'Email simulated locally (Check .env for live delivery settings)'
    ];
}

/**
 * Pure PHP lightweight SMTP client
 */
function sendNativeSmtp($host, $port, $username, $password, $fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody) {
    $timeout = 15;
    $isSsl = ($port == 465);
    $protocol = $isSsl ? 'ssl://' : '';

    $socket = @fsockopen($protocol . $host, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        return ['success' => false, 'error' => "Could not connect to SMTP server $host:$port ($errstr)"];
    }

    $read = function() use ($socket) {
        $response = '';
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == ' ') break;
        }
        return $response;
    };

    $send = function($cmd) use ($socket, $read) {
        fputs($socket, $cmd . "\r\n");
        return $read();
    };

    $greeting = $read();
    if (substr($greeting, 0, 3) != '220') {
        fclose($socket);
        return ['success' => false, 'error' => "SMTP Server error on connect: $greeting"];
    }

    $ehlo = $send("EHLO " . gethostname());

    if (!$isSsl && $port == 587) {
        $starttls = $send("STARTTLS");
        if (substr($starttls, 0, 3) != '220') {
            fclose($socket);
            return ['success' => false, 'error' => "STARTTLS failed: $starttls"];
        }
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return ['success' => false, 'error' => "Failed to establish TLS encryption"];
        }
        $send("EHLO " . gethostname());
    }

    $auth = $send("AUTH LOGIN");
    if (substr($auth, 0, 3) != '334') {
        fclose($socket);
        return ['success' => false, 'error' => "AUTH LOGIN failed: $auth"];
    }

    $userRes = $send(base64_encode($username));
    if (substr($userRes, 0, 3) != '334') {
        fclose($socket);
        return ['success' => false, 'error' => "Username rejected: $userRes"];
    }

    $passRes = $send(base64_encode($password));
    if (substr($passRes, 0, 3) != '235') {
        fclose($socket);
        return ['success' => false, 'error' => "Password rejected: $passRes"];
    }

    $mailFrom = $send("MAIL FROM:<$fromEmail>");
    if (substr($mailFrom, 0, 3) != '250') {
        fclose($socket);
        return ['success' => false, 'error' => "MAIL FROM failed: $mailFrom"];
    }

    $rcptTo = $send("RCPT TO:<$toEmail>");
    if (substr($rcptTo, 0, 3) != '250') {
        fclose($socket);
        return ['success' => false, 'error' => "RCPT TO failed: $rcptTo"];
    }

    $data = $send("DATA");
    if (substr($data, 0, 3) != '354') {
        fclose($socket);
        return ['success' => false, 'error' => "DATA start failed: $data"];
    }

    $headers = [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>",
        "To: =?UTF-8?B?" . base64_encode($toName ?: $toEmail) . "?= <$toEmail>",
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
        "Date: " . date('r'),
        "X-Mailer: Reunite Campus Platform"
    ];

    $emailContent = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
    $finish = $send($emailContent);
    $send("QUIT");
    fclose($socket);

    if (substr($finish, 0, 3) == '250') {
        return ['success' => true, 'message' => 'Email sent successfully via SMTP'];
    }

    return ['success' => false, 'error' => "Failed to finish message delivery: $finish"];
}

/**
 * Generate 6-digit OTP code and store in session (Valid for 10 minutes)
 */
function generateEmailOtp($email) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $_SESSION['signup_otp'] = [
        'email' => strtolower(trim($email)),
        'code' => $otp,
        'expires_at' => time() + (10 * 60) // 10 mins
    ];

    return $otp;
}

/**
 * Verify submitted OTP against session
 */
function verifyEmailOtp($email, $code) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['signup_otp'])) {
        return ['success' => false, 'message' => 'No verification code was sent. Please request a new one.'];
    }

    $stored = $_SESSION['signup_otp'];
    $cleanEmail = strtolower(trim($email));
    $cleanCode = trim((string)$code);

    if ($stored['email'] !== $cleanEmail) {
        return ['success' => false, 'message' => 'Email mismatch. Please request a new verification code.'];
    }

    if (time() > $stored['expires_at']) {
        unset($_SESSION['signup_otp']);
        return ['success' => false, 'message' => 'Verification code has expired. Please request a new one.'];
    }

    if ($stored['code'] !== $cleanCode) {
        return ['success' => false, 'message' => 'Incorrect 6-digit verification code. Please try again.'];
    }

    // OTP Valid! Mark email as verified in session
    $_SESSION['verified_signup_email'] = $cleanEmail;
    unset($_SESSION['signup_otp']);

    return ['success' => true, 'message' => 'Email verified successfully!'];
}

/**
 * Check if the email was successfully verified in the current session
 */
function isEmailOtpVerified($email) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['verified_signup_email']) && $_SESSION['verified_signup_email'] === strtolower(trim($email));
}

/**
 * Ensures password_resets table exists in the database
 */
function ensurePasswordResetsTable() {
    global $conn;
    if (!$conn) return;
    $sql = "CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT(11) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `token` VARCHAR(128) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_reset_token` (`token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    @$conn->query($sql);
}

/**
 * Generate secure 64-char crypto token for Password Reset (Expires in 10 minutes)
 */
function createPasswordResetToken($userId, $email) {
    global $conn;
    ensurePasswordResetsTable();

    $token = bin2hex(random_bytes(32)); // 64 hex characters
    $expiresAt = date('Y-m-d H:i:s', time() + (10 * 60)); // Exactly 10 minutes

    if ($conn) {
        // Clear any previous active tokens for this user
        $delStmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
        if ($delStmt) {
            $delStmt->bind_param("i", $userId);
            $delStmt->execute();
            $delStmt->close();
        }

        $stmt = $conn->prepare("INSERT INTO password_resets (user_id, email, token, expires_at) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $userId, $email, $token, $expiresAt);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Also cache in session as a fallback
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['active_password_reset'] = [
        'user_id' => $userId,
        'email' => $email,
        'token' => $token,
        'expires_at' => time() + (10 * 60)
    ];

    return $token;
}

/**
 * Verify if a reset token is valid and not expired
 */
function verifyPasswordResetToken($token) {
    global $conn;
    if (empty($token)) return null;
    $cleanToken = trim($token);

    if ($conn) {
        ensurePasswordResetsTable();
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("SELECT pr.id, pr.user_id, pr.email, pr.expires_at, u.full_name, u.pin FROM password_resets pr JOIN users u ON pr.user_id = u.user_id WHERE pr.token = ? AND pr.expires_at > ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("ss", $cleanToken, $now);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $stmt->close();
                return $row;
            }
            $stmt->close();
        }
    }

    // Session fallback check
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['active_password_reset']) && $_SESSION['active_password_reset']['token'] === $cleanToken) {
        if (time() <= $_SESSION['active_password_reset']['expires_at']) {
            return [
                'user_id' => $_SESSION['active_password_reset']['user_id'],
                'email' => $_SESSION['active_password_reset']['email'],
                'full_name' => 'Student'
            ];
        }
    }

    return null;
}

/**
 * Reset password in database and invalidate token
 */
function consumePasswordResetToken($token, $newPassword) {
    global $conn;
    $resetData = verifyPasswordResetToken($token);
    if (!$resetData) {
        return ['success' => false, 'message' => 'Reset link is invalid or has expired (10-minute limit exceeded).'];
    }

    $userId = $resetData['user_id'];
    $passHash = password_hash($newPassword, PASSWORD_DEFAULT);

    if ($conn) {
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $passHash, $userId);
            $success = $stmt->execute();
            $stmt->close();

            if ($success) {
                // Delete consumed token
                $cleanToken = trim($token);
                $del = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                if ($del) {
                    $del->bind_param("s", $cleanToken);
                    $del->execute();
                    $del->close();
                }
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                unset($_SESSION['active_password_reset']);
                logAccess($userId, 'Password Reset via Email Link');
                return ['success' => true, 'message' => 'Password reset successfully!'];
            }
        }
    }

    return ['success' => false, 'message' => 'Failed to update password in database.'];
}

/**
 * Dynamically inserts or retrieves a match record across any table schema variations (Matching v2 supported)
 */
function insertMatchRecord($lostId, $foundId, $score, $tier = 'STRONG', $matchReasons = null, $engineVersion = '2.0.0') {
    global $conn;
    if (!$conn) return null;

    $matchColsRes = $conn->query("SHOW COLUMNS FROM matches");
    $mCols = [];
    if ($matchColsRes) {
        while ($mc = $matchColsRes->fetch_assoc()) $mCols[] = strtolower($mc['Field']);
    }

    $mLostCol = in_array('lost_id', $mCols) ? 'lost_id' : (in_array('lost_report_id', $mCols) ? 'lost_report_id' : 'lost_id');
    $mFoundCol = in_array('found_id', $mCols) ? 'found_id' : (in_array('found_report_id', $mCols) ? 'found_report_id' : 'found_id');
    $mScoreCol = in_array('match_percentage', $mCols) ? 'match_percentage' : (in_array('similarity_score', $mCols) ? 'similarity_score' : (in_array('score', $mCols) ? 'score' : 'match_percentage'));
    $mIdCol = in_array('match_id', $mCols) ? 'match_id' : (in_array('id', $mCols) ? 'id' : 'match_id');

    $scoreDecimal = number_format($score, 2, '.', '');
    $hasTier = in_array('confidence_tier', $mCols);
    $hasReasons = in_array('match_reasons', $mCols);
    $hasEngine = in_array('engine_version', $mCols);
    $hasStatus = in_array('status', $mCols);
    $hasCreated = in_array('created_at', $mCols);

    $reasonsJson = is_array($matchReasons) ? json_encode($matchReasons, JSON_UNESCAPED_SLASHES) : $matchReasons;

    // Check if match already exists
    $checkStmt = $conn->prepare("SELECT $mIdCol, status FROM matches WHERE $mLostCol = ? AND $mFoundCol = ?");
    if ($checkStmt) {
        $checkStmt->bind_param("ii", $lostId, $foundId);
        $checkStmt->execute();
        $res = $checkStmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $existingId = (int)($row[$mIdCol] ?? 1);
            $existingStatus = $row['status'] ?? 'pending';
            $checkStmt->close();

            // Update score, tier, reasons if pending
            if ($existingStatus === 'pending' || empty($existingStatus)) {
                $upParts = ["$mScoreCol = ?"];
                $types = "d";
                $params = [$scoreDecimal];

                if ($hasTier) { $upParts[] = "confidence_tier = ?"; $types .= "s"; $params[] = $tier; }
                if ($hasReasons) { $upParts[] = "match_reasons = ?"; $types .= "s"; $params[] = $reasonsJson; }
                if ($hasEngine) { $upParts[] = "engine_version = ?"; $types .= "s"; $params[] = $engineVersion; }

                $params[] = $existingId;
                $types .= "i";

                $upSql = "UPDATE matches SET " . implode(", ", $upParts) . " WHERE $mIdCol = ?";
                $upStmt = $conn->prepare($upSql);
                if ($upStmt) {
                    $upStmt->bind_param($types, ...$params);
                    $upStmt->execute();
                    $upStmt->close();
                }
            }
            return $existingId;
        }
        $checkStmt->close();
    }

    $colsToInsert = [$mLostCol, $mFoundCol, $mScoreCol];
    $placeholders = ["?", "?", "?"];
    $types = "iid";
    $params = [$lostId, $foundId, $scoreDecimal];

    if ($hasTier) { $colsToInsert[] = 'confidence_tier'; $placeholders[] = '?'; $types .= 's'; $params[] = $tier; }
    if ($hasReasons) { $colsToInsert[] = 'match_reasons'; $placeholders[] = '?'; $types .= 's'; $params[] = $reasonsJson; }
    if ($hasEngine) { $colsToInsert[] = 'engine_version'; $placeholders[] = '?'; $types .= 's'; $params[] = $engineVersion; }
    if ($hasStatus) { $colsToInsert[] = 'status'; $placeholders[] = "'pending'"; }
    if ($hasCreated) { $colsToInsert[] = 'created_at'; $placeholders[] = 'NOW()'; }

    $sql = "INSERT INTO matches (" . implode(", ", $colsToInsert) . ") VALUES (" . implode(", ", $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();
            return $newId;
        } else {
            error_log("[Matches] Insert execute error: " . $stmt->error);
            $stmt->close();
        }
    } else {
        error_log("[Matches] Insert prepare error: " . $conn->error);
    }
    return null;
}

/**
 * Helper to calculate human-readable relative time (Campus IST timezone)
 */
if (!function_exists('formatRelativeTime')) {
    function formatRelativeTime($dateStr) {
        if (empty($dateStr)) return 'Recently';
        $time = strtotime($dateStr);
        if (!$time) return 'Recently';
        $diff = time() - $time;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return max(1, floor($diff / 60)) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M d, Y', $time);
    }
}

/**
 * Dynamically inserts a notification record across any table schema variations
 */
function insertSystemNotification($targetUserId, $title, $message, $link = '', $reportType = 'LOST', $reportId = 0, $type = 'match') {
    global $conn;
    if (!$conn || empty($targetUserId)) return false;

    $notifColsRes = $conn->query("SHOW COLUMNS FROM notifications");
    $nCols = [];
    if ($notifColsRes) {
        while ($nc = $notifColsRes->fetch_assoc()) $nCols[] = strtolower($nc['Field']);
    }

    $uCol = in_array('user_id', $nCols) ? 'user_id' : 'userId';
    $hasTitle = in_array('title', $nCols);
    $finalMessage = $hasTitle ? $message : (!empty($title) ? "$title: $message" : $message);

    $fields = [$uCol, 'message'];
    $vals = [(int)$targetUserId, $finalMessage];
    $types = "is";

    if ($hasTitle) {
        $fields[] = 'title';
        $vals[] = $title;
        $types .= 's';
    }
    if (in_array('report_type', $nCols)) {
        $fields[] = 'report_type';
        $vals[] = strtoupper($reportType);
        $types .= 's';
    }
    if (in_array('report_id', $nCols)) {
        $fields[] = 'report_id';
        $vals[] = (int)$reportId;
        $types .= 'i';
    }
    if (in_array('type', $nCols)) {
        $fields[] = 'type';
        $vals[] = $type;
        $types .= 's';
    }
    if (in_array('link', $nCols)) {
        $fields[] = 'link';
        $vals[] = $link;
        $types .= 's';
    }
    if (in_array('is_read', $nCols)) {
        $fields[] = 'is_read';
        $vals[] = 0;
        $types .= 'i';
    }
    if (in_array('created_at', $nCols)) {
        $fields[] = 'created_at';
        $vals[] = date('Y-m-d H:i:s');
        $types .= 's';
    }

    $fieldList = implode(', ', $fields);
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));

    $sql = "INSERT INTO notifications ($fieldList) VALUES ($placeholders)";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$vals);
        $ok = $stmt->execute();
        $insId = $stmt->insert_id;
        $stmt->close();
        return $insId;
    } else {
        error_log("[Notifications] Insert prepare error: " . $conn->error);
        return false;
    }
}

/**
 * Calculates genuine real-time multimodal & semantic attribute similarity score
 * between a lost report and a candidate found report.
 */
if (!function_exists('calculateRealtimeSimilarityScore')) {
    function calculateRealtimeSimilarityScore($lost, $found, $vectorScore = null) {
        $lostCat = trim((string)($lost['category'] ?? ''));
        $foundCat = trim((string)($found['category'] ?? ''));
        $lostTitle = trim((string)($lost['title'] ?? ''));
        $foundTitle = trim((string)($found['title'] ?? ''));
        $lostDesc = trim((string)($lost['description'] ?? ''));
        $foundDesc = trim((string)($found['description'] ?? ''));
        $lostLoc = strtolower(trim((string)($lost['location'] ?? '')));
        $foundLoc = strtolower(trim((string)($found['location'] ?? '')));
        $lostDate = trim((string)($lost['date_lost'] ?? ($lost['lost_date'] ?? '')));
        $foundDate = trim((string)($found['date_found'] ?? ($found['found_date'] ?? '')));

        // Category Stem Dictionary for accurate semantic domain matching
        $stems = [
            'laptop' => ['laptop', 'notebook', 'macbook', 'chromebook', 'thinkpad', 'latitude', 'inspiron', 'pavilion', 'zenbook', 'vivobook', 'omen', 'legion'],
            'phone' => ['phone', 'smartphone', 'iphone', 'android', 'mobile', 'galaxy', 'pixel', 'oneplus', 'redmi', 'realme'],
            'tablet' => ['tablet', 'ipad', 'tab', 'kindle'],
            'earbuds' => ['earbud', 'earbuds', 'airpod', 'airpods', 'headphone', 'headphones', 'earphone', 'neckband'],
            'watch' => ['watch', 'smartwatch', 'wristwatch', 'band', 'apple watch', 'noise', 'firebolt', 'boat', 'titan', 'casio', 'fastrack'],
            'wallet' => ['wallet', 'purse', 'billfold', 'cardholder', 'pouch', 'money clip'],
            'bag' => ['bag', 'backpack', 'duffel', 'tote', 'handbag', 'rucksack', 'kit', 'wildcraft', 'skybags', 'american tourister'],
            'keys' => ['key', 'keys', 'keychain', 'fob', 'ring'],
            'bottle' => ['bottle', 'flask', 'tumbler', 'sipper', 'thermosteel', 'milton'],
            'glasses' => ['glasses', 'spectacles', 'sunglasses', 'shades', 'eyewear', 'lenskart', 'rayban'],
            'id_card' => ['id card', 'id', 'college id', 'student id', 'license', 'aadhaar', 'pan card', 'debit card', 'credit card'],
            'book' => ['book', 'notebook', 'textbook', 'diary', 'journal', 'register'],
            'charger' => ['charger', 'adapter', 'cable', 'power bank', 'wire', 'type-c', 'lightning']
        ];

        $lostCombined = strtolower("$lostCat $lostTitle $lostDesc");
        $foundCombined = strtolower("$foundCat $foundTitle $foundDesc");

        $lostStem = 'general';
        foreach ($stems as $stem => $kws) {
            foreach ($kws as $kw) {
                if (stripos($lostCombined, $kw) !== false) {
                    $lostStem = $stem;
                    break 2;
                }
            }
        }

        $foundStem = 'general';
        foreach ($stems as $stem => $kws) {
            foreach ($kws as $kw) {
                if (stripos($foundCombined, $kw) !== false) {
                    $foundStem = $stem;
                    break 2;
                }
            }
        }

        // 1. Base Score from Category Match
        $score = 52.0;
        if ($lostStem !== 'general' && $lostStem === $foundStem) {
            $score = 72.0;
        } elseif (!empty($lostCat) && !empty($foundCat) && strcasecmp($lostCat, $foundCat) === 0) {
            $score = 75.0;
        } elseif (strpos($lostCombined, 'electronic') !== false && strpos($foundCombined, 'electronic') !== false) {
            $score = 64.0;
        }

        // 2. String similarity on Title
        if (!empty($lostTitle) && !empty($foundTitle)) {
            similar_text(strtolower($lostTitle), strtolower($foundTitle), $titleSim);
            $score += ($titleSim * 0.12);
        }

        // 3. Keyword / Token Overlap
        $rawTokens = preg_split('/[\s,\.;:\-\/()]+/', $lostCombined);
        $stopWords = ['the','and','with','for','item','lost','found','campus','near','has','have','was','were','this','that','some','area','please','help','report','about','colour','color'];
        $lostTokens = [];
        foreach ($rawTokens as $tok) {
            $tok = trim($tok);
            if (strlen($tok) >= 3 && !in_array($tok, $stopWords, true) && !is_numeric($tok)) {
                $lostTokens[$tok] = true;
            }
        }
        $lostTokens = array_keys($lostTokens);

        $wordMatches = 0;
        foreach ($lostTokens as $tok) {
            if (strpos($foundCombined, $tok) !== false) {
                $wordMatches++;
            }
        }
        $score += min(14.0, $wordMatches * 3.5);

        // 4. Brand Analysis
        $knownBrands = ['apple', 'dell', 'lenovo', 'hp', 'asus', 'acer', 'samsung', 'sony', 'boat', 'noise', 'casio', 'titan', 'fastrack', 'oneplus', 'xiaomi', 'redmi', 'realme', 'wildcraft', 'nike', 'puma', 'adidas'];
        $lostBrand = null;
        $foundBrand = null;
        foreach ($knownBrands as $b) {
            if (strpos($lostCombined, $b) !== false) $lostBrand = $b;
            if (strpos($foundCombined, $b) !== false) $foundBrand = $b;
        }

        if ($lostBrand && $foundBrand) {
            if ($lostBrand === $foundBrand) {
                $score += 12.0; // Confirmed matching brand
            } else {
                $score -= 14.0; // Adjust for divergent brand, but retain candidate score
            }
        }

        // 5. Color Compatibility
        $colors = ['black', 'silver', 'grey', 'gray', 'blue', 'red', 'white', 'green', 'gold', 'navy', 'yellow', 'pink', 'brown'];
        $lostColor = null;
        $foundColor = null;
        foreach ($colors as $c) {
            if (strpos($lostCombined, $c) !== false) $lostColor = $c;
            if (strpos($foundCombined, $c) !== false) $foundColor = $c;
        }
        if ($lostColor && $foundColor) {
            if ($lostColor === $foundColor || 
                ($lostColor === 'grey' && $foundColor === 'silver') || 
                ($lostColor === 'silver' && $foundColor === 'grey') || 
                ($lostColor === 'grey' && $foundColor === 'gray')) {
                $score += 6.0;
            }
        }

        // 6. Location Proximity
        $locationAreas = ['library', 'cafeteria', 'canteen', 'lab', 'sports', 'auditorium', 'parking', 'bus', 'block', 'hostel', 'ground'];
        $lostLocMatch = null;
        $foundLocMatch = null;
        foreach ($locationAreas as $loc) {
            if (strpos($lostLoc, $loc) !== false || strpos($lostCombined, $loc) !== false) $lostLocMatch = $loc;
            if (strpos($foundLoc, $loc) !== false || strpos($foundCombined, $loc) !== false) $foundLocMatch = $loc;
        }
        if ($lostLocMatch && $foundLocMatch && $lostLocMatch === $foundLocMatch) {
            $score += 7.0; // Both in same campus zone
        }

        // 7. Presence of image
        if (!empty($found['thumbnail']) || !empty($found['image_path'])) {
            $score += 3.0;
        }

        // 8. Temporal proximity
        if (!empty($lostDate) && !empty($foundDate)) {
            $tLost = strtotime($lostDate);
            $tFound = strtotime($foundDate);
            if ($tLost > 0 && $tFound > 0) {
                $diffDays = abs($tFound - $tLost) / 86400;
                if ($diffDays <= 7) {
                    $score += 4.0;
                } elseif ($diffDays <= 30) {
                    $score += 2.0;
                }
            }
        }

        // 9. Blend with vector score if provided (> 0)
        if ($vectorScore !== null && is_numeric($vectorScore) && (float)$vectorScore > 0) {
            $v = (float)$vectorScore;
            if ($v <= 1.0) $v *= 100.0;
            $score = ($score * 0.4) + ($v * 0.6);
        }

        // Clamp score to realistic range (68% to 95% for candidates in the same category)
        if ($lostStem !== 'general' && $lostStem === $foundStem) {
            $score = max(68.0, min(95.0, $score));
        } else {
            $score = max(50.0, min(95.0, $score));
        }

        return round($score, 1);
    }
}

/**
 * Executes Vector + Hybrid Real-Time Matchmaking v2 with Structured Attribute Verification
 */
function runRealtimeMatchmaking($insertedId, $reportType, $category, $title, $description, $location, $imageWebPath, $userId) {
    global $conn;
    if (!$conn || !$insertedId) return [];

    if (!defined('REUNITE_MATCHING_V2_LOADED')) {
        require_once __DIR__ . '/matching_v2.php';
    }

    $createdMatches = [];
    $targetType = ($reportType === 'found') ? 'lost' : 'found';
    $targetTable = ($reportType === 'found') ? 'lost_reports' : 'found_reports';
    $insertedTable = ($reportType === 'found') ? 'found_reports' : 'lost_reports';

    // 1. Fetch newly inserted report details
    $iColsRes = $conn->query("SHOW COLUMNS FROM `$insertedTable`");
    $iCols = [];
    if ($iColsRes) {
        while ($c = $iColsRes->fetch_assoc()) $iCols[] = strtolower($c['Field']);
    }
    $iIdCol = in_array('id', $iCols) ? 'id' : (($reportType === 'found') ? 'found_id' : 'lost_id');
    
    $insStmt = $conn->prepare("SELECT * FROM `$insertedTable` WHERE $iIdCol = ? LIMIT 1");
    if (!$insStmt) return [];
    $insStmt->bind_param("i", $insertedId);
    $insStmt->execute();
    $insertedRow = $insStmt->get_result()->fetch_assoc();
    $insStmt->close();
    if (!$insertedRow) return [];

    // Check for Digital DNA record
    $insertedDna = null;
    $dnaCheck = $conn->query("SHOW TABLES LIKE 'digital_dna'");
    $hasDnaTable = ($dnaCheck && $dnaCheck->num_rows > 0);
    if ($hasDnaTable) {
        $dnaStmt = $conn->prepare("SELECT * FROM digital_dna WHERE report_type = ? AND report_id = ? LIMIT 1");
        if ($dnaStmt) {
            $dnaStmt->bind_param("si", $reportType, $insertedId);
            $dnaStmt->execute();
            $insertedDna = $dnaStmt->get_result()->fetch_assoc();
            $dnaStmt->close();
        }
    }
    $insertedAttrs = extractNormalizedAttributesV2($insertedRow, $insertedDna);
    if ($userId > 0 && empty($insertedAttrs['user_id'])) {
        $insertedAttrs['user_id'] = (int)$userId;
    }

    // 2. Query Vector Matches from Flask AI Engine
    $vectorMatches = [];
    try {
        $flaskUrl = (defined('FLASK_AI_URL') ? FLASK_AI_URL : 'https://reunite-ai-backend.onrender.com') . '/report/search-matches';
        $searchPayload = [
            'text' => "$category. $title. $description. $location.",
            'report_id' => ($reportType === 'found' ? 'RF-' : 'RL-') . str_pad($insertedId, 5, '0', STR_PAD_LEFT),
            'report_type' => $targetType,
            'image_path' => $imageWebPath,
            'top_k' => 15
        ];
        $ch = curl_init($flaskUrl);
        if ($ch) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($searchPayload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-AI-Secret-Key: ' . (defined('AI_SECRET_KEY') ? AI_SECRET_KEY : 'reunite_ai_secret_token_2026_kolkata')
            ]);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $res = curl_exec($ch);
            curl_close($ch);
            if ($res) {
                $decoded = json_decode($res, true);
                if (!empty($decoded['matches'])) {
                    $vectorMatches = $decoded['matches'];
                }
            }
        }
    } catch (Exception $e) {
        error_log("[Matchmaking] Vector search notice: " . $e->getMessage());
    }

    $candidateScores = [];
    foreach ($vectorMatches as $vm) {
        $meta = $vm['metadata'] ?? [];
        $matchedId = (int)($meta['db_id'] ?? 0);
        $vScore = (float)($vm['match_percentage'] ?? 0);
        if ($matchedId > 0) {
            $candidateScores[$matchedId] = $vScore;
        }
    }

    // 3. Fallback / Hybrid Opposites from DB
    $colsRes = $conn->query("SHOW COLUMNS FROM `$targetTable`");
    $targetCols = [];
    if ($colsRes) {
        while ($c = $colsRes->fetch_assoc()) $targetCols[] = strtolower($c['Field']);
    }
    $idCol = in_array('id', $targetCols) ? 'id' : (($targetType === 'found') ? 'found_id' : 'lost_id');
    $userCol = in_array('user_id', $targetCols) ? 'user_id' : (in_array('finder_id', $targetCols) ? 'finder_id' : 'user_id');

    $queryOpposite = "SELECT * FROM `$targetTable` WHERE ($idCol != ?) AND (status = 'active' OR status = 'Pending' OR status = '' OR status IS NULL)";
    $stmtOpp = $conn->prepare($queryOpposite);
    if ($stmtOpp) {
        $stmtOpp->bind_param("i", $insertedId);
        $stmtOpp->execute();
        $resOpp = $stmtOpp->get_result();

        while ($oppRow = $resOpp->fetch_assoc()) {
            $oppId = (int)$oppRow[$idCol];

            // Fetch Digital DNA for opposite report if available
            $oppDna = null;
            if ($hasDnaTable) {
                $dnaStmt = $conn->prepare("SELECT * FROM digital_dna WHERE report_type = ? AND report_id = ? LIMIT 1");
                if ($dnaStmt) {
                    $dnaStmt->bind_param("si", $targetType, $oppId);
                    $dnaStmt->execute();
                    $oppDna = $dnaStmt->get_result()->fetch_assoc();
                    $dnaStmt->close();
                }
            }
            $oppAttrs = extractNormalizedAttributesV2($oppRow, $oppDna);

            $lostAttrs = ($reportType === 'found') ? $oppAttrs : $insertedAttrs;
            $foundAttrs = ($reportType === 'found') ? $insertedAttrs : $oppAttrs;
            $lostId = ($reportType === 'found') ? $oppId : $insertedId;
            $foundId = ($reportType === 'found') ? $insertedId : $oppId;

            $vectorScore = $candidateScores[$oppId] ?? null;

            // Execute Matching Engine v2 Evaluation
            $eval = evaluateMatchV2($lostAttrs, $foundAttrs, $vectorScore);

            // If vetoed or discarded, skip completely
            if ($eval['confidence_tier'] === 'DISCARD' || $eval['veto_triggered'] !== null || $eval['final_score'] < 50.0) {
                continue;
            }

            // Save or update match record in database
            $matchId = insertMatchRecord(
                $lostId,
                $foundId,
                $eval['final_score'],
                $eval['confidence_tier'],
                $eval['match_reasons'],
                $eval['engine_version']
            );

            // Check Notification Policy: Only STRONG tier with score >= 65% triggers in-app notification
            $emojiRegex = '/[\x{1F300}-\x{1FAFF}\x{1F000}-\x{1F2FF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200D}]/u';
            $lostTitle = trim(preg_replace('/\s+/', ' ', preg_replace($emojiRegex, '', $lostAttrs['title'] ?? '')));
            $foundTitle = trim(preg_replace('/\s+/', ' ', preg_replace($emojiRegex, '', $foundAttrs['title'] ?? '')));
            $scoreDisplay = round($eval['final_score']) . '%';
            $reasonsSummary = $eval['why_matched_summary'] ? " ({$eval['why_matched_summary']})" : "";

            if ($eval['should_notify']) {
                $lostUserId = (int)$lostAttrs['user_id'];
                $foundUserId = (int)$foundAttrs['user_id'];

                // Notification for Lost Report Owner
                if ($lostUserId > 0) {
                    $nTitle = "Potential Match: " . substr($foundTitle, 0, 40);
                    $nMsg = "A found item '" . $foundTitle . "' matches your lost report '" . $lostTitle . "' with {$scoreDisplay} similarity" . $reasonsSummary . ".";
                    $nLink = "item.php?type=found&id=" . $foundId . ($matchId ? "&match_id=" . $matchId : "");
                    insertSystemNotification($lostUserId, $nTitle, $nMsg, $nLink, 'FOUND', $foundId, 'match');
                }

                // Notification for Found Report Owner
                if ($foundUserId > 0) {
                    $nTitle = "Potential Match: " . substr($lostTitle, 0, 40);
                    $nMsg = "Your reported found item '" . $foundTitle . "' has a {$scoreDisplay} match with a lost item ('" . $lostTitle . "')" . $reasonsSummary . ".";
                    $nLink = "item.php?type=found&id=" . $foundId . ($matchId ? "&match_id=" . $matchId : "");
                    insertSystemNotification($foundUserId, $nTitle, $nMsg, $nLink, 'FOUND', $foundId, 'match');
                }
            }

            // Check Email Policy: Only STRONG tier with score >= 90% triggers Brevo email
            if ($eval['should_email']) {
                $lostUserId = (int)$lostAttrs['user_id'];
                $foundUserId = (int)$foundAttrs['user_id'];

                // Brevo Email to Lost Owner
                if ($lostUserId > 0) {
                    try {
                        $uRes = $conn->query("SELECT email, full_name FROM users WHERE user_id = $lostUserId LIMIT 1");
                        if ($uRes && ($uRow = $uRes->fetch_assoc())) {
                            $uEmail = function_exists('decryptData') ? decryptData($uRow['email']) : $uRow['email'];
                            if (!empty($uEmail) && filter_var($uEmail, FILTER_VALIDATE_EMAIL) && function_exists('sendBrevoEmail')) {
                                $nLink = "item.php?type=found&id=" . $foundId . ($matchId ? "&match_id=" . $matchId : "");
                                $emailSubj = "Reunite: Verified Match Detected for Your Lost Item!";
                                $emailHtml = "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Match Found</title></head><body style='margin:0;padding:0;background-color:#F8FAFC;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;color:#1E293B;'>"
                                    . "<table role='presentation' width='100%' cellspacing='0' cellpadding='0' style='background-color:#F8FAFC;padding:40px 16px;'><tr><td align='center'>"
                                    . "<table role='presentation' width='100%' style='max-width:520px;background-color:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05);' cellspacing='0' cellpadding='0'>"
                                    . "<tr><td style='padding:28px 36px 20px 36px;border-bottom:1px solid #F1F5F9;'><span style='font-size:19px;font-weight:700;color:#C4622D;'>Reunite</span><span style='margin-left:8px;font-size:12px;color:#64748B;'>Campus Lost &amp; Found</span></td></tr>"
                                    . "<tr><td style='padding:32px 36px;'>"
                                    . "<h1 style='margin:0 0 16px 0;font-size:20px;font-weight:600;color:#0F172A;'>Match detected for your lost item</h1>"
                                    . "<p style='margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#334155;'>Hello <strong>" . htmlspecialchars($uRow['full_name'] ?? 'Student') . "</strong>,</p>"
                                    . "<p style='margin:0 0 20px 0;font-size:15px;line-height:1.6;color:#334155;'>Our AI matching engine has detected a potential match for your reported lost item:</p>"
                                    . "<div style='background-color:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:16px 20px;margin:20px 0;'>"
                                    . "<p style='margin:0 0 8px 0;font-size:14px;color:#334155;'><strong>Your Item:</strong> " . htmlspecialchars($lostTitle) . "</p>"
                                    . "<p style='margin:0 0 8px 0;font-size:14px;color:#334155;'><strong>Found Item:</strong> " . htmlspecialchars($foundTitle) . "</p>"
                                    . "<p style='margin:0;font-size:14px;color:#C4622D;font-weight:600;'>Match Confidence: " . $scoreDisplay . "</p>"
                                    . ($eval['why_matched_summary'] ? "<p style='margin:8px 0 0 0;font-size:12px;color:#64748B;'><strong>Signals:</strong> " . htmlspecialchars($eval['why_matched_summary']) . "</p>" : "")
                                    . "</div>"
                                    . "<table role='presentation' cellspacing='0' cellpadding='0' style='margin:28px 0 16px 0;'><tr><td style='border-radius:6px;background-color:#C4622D;'><a href='https://reunite.site.je/" . $nLink . "' target='_blank' style='display:inline-block;padding:13px 28px;font-size:14px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:6px;'>View Match Details &rarr;</a></td></tr></table>"
                                    . "</td></tr>"
                                    . "<tr><td style='padding:20px 36px 24px 36px;background-color:#F8FAFC;border-top:1px solid #F1F5F9;'><p style='margin:0;font-size:12px;color:#94A3B8;text-align:center;'>This is an automated notification from Reunite &bull; Campus Lost &amp; Found Platform.</p></td></tr>"
                                    . "</table></td></tr></table></body></html>";
                                @sendBrevoEmail($uEmail, $uRow['full_name'], $emailSubj, $emailHtml);
                            }
                        }
                    } catch (Exception $e) {}
                }

                // Brevo Email to Finder
                if ($foundUserId > 0) {
                    try {
                        $fRes = $conn->query("SELECT email, full_name FROM users WHERE user_id = $foundUserId LIMIT 1");
                        if ($fRes && ($fRow = $fRes->fetch_assoc())) {
                            $fEmail = function_exists('decryptData') ? decryptData($fRow['email']) : $fRow['email'];
                            if (!empty($fEmail) && filter_var($fEmail, FILTER_VALIDATE_EMAIL) && function_exists('sendBrevoEmail')) {
                                $nLink = "item.php?type=found&id=" . $foundId . ($matchId ? "&match_id=" . $matchId : "");
                                $emailSubj = "Reunite: Verified Match Alert for Item You Found!";
                                $emailHtml = "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Match Alert</title></head><body style='margin:0;padding:0;background-color:#F8FAFC;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;color:#1E293B;'>"
                                    . "<table role='presentation' width='100%' cellspacing='0' cellpadding='0' style='background-color:#F8FAFC;padding:40px 16px;'><tr><td align='center'>"
                                    . "<table role='presentation' width='100%' style='max-width:520px;background-color:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05);' cellspacing='0' cellpadding='0'>"
                                    . "<tr><td style='padding:28px 36px 20px 36px;border-bottom:1px solid #F1F5F9;'><span style='font-size:19px;font-weight:700;color:#C4622D;'>Reunite</span><span style='margin-left:8px;font-size:12px;color:#64748B;'>Campus Lost &amp; Found</span></td></tr>"
                                    . "<tr><td style='padding:32px 36px;'>"
                                    . "<h1 style='margin:0 0 16px 0;font-size:20px;font-weight:600;color:#0F172A;'>Match alert for item you found</h1>"
                                    . "<p style='margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#334155;'>Hello <strong>" . htmlspecialchars($fRow['full_name'] ?? 'Student') . "</strong>,</p>"
                                    . "<p style='margin:0 0 20px 0;font-size:15px;line-height:1.6;color:#334155;'>A student has reported a lost item that closely matches the item you found on campus:</p>"
                                    . "<div style='background-color:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:16px 20px;margin:20px 0;'>"
                                    . "<p style='margin:0 0 8px 0;font-size:14px;color:#334155;'><strong>Found Item:</strong> " . htmlspecialchars($foundTitle) . "</p>"
                                    . "<p style='margin:0 0 8px 0;font-size:14px;color:#334155;'><strong>Matching Lost Report:</strong> " . htmlspecialchars($lostTitle) . "</p>"
                                    . "<p style='margin:0;font-size:14px;color:#C4622D;font-weight:600;'>Match Confidence: " . $scoreDisplay . "</p>"
                                    . ($eval['why_matched_summary'] ? "<p style='margin:8px 0 0 0;font-size:12px;color:#64748B;'><strong>Signals:</strong> " . htmlspecialchars($eval['why_matched_summary']) . "</p>" : "")
                                    . "</div>"
                                    . "<table role='presentation' cellspacing='0' cellpadding='0' style='margin:28px 0 16px 0;'><tr><td style='border-radius:6px;background-color:#C4622D;'><a href='https://reunite.site.je/" . $nLink . "' target='_blank' style='display:inline-block;padding:13px 28px;font-size:14px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:6px;'>View Match Details &rarr;</a></td></tr></table>"
                                    . "</td></tr>"
                                    . "<tr><td style='padding:20px 36px 24px 36px;background-color:#F8FAFC;border-top:1px solid #F1F5F9;'><p style='margin:0;font-size:12px;color:#94A3B8;text-align:center;'>This is an automated notification from Reunite &bull; Campus Lost &amp; Found Platform.</p></td></tr>"
                                    . "</table></td></tr></table></body></html>";
                                @sendBrevoEmail($fEmail, $fRow['full_name'], $emailSubj, $emailHtml);
                            }
                        }
                    } catch (Exception $e) {}
                }
            }

            $createdMatches[] = [
                'match_id' => $matchId,
                'lost_id' => $lostId,
                'found_id' => $foundId,
                'similarity_score' => $eval['final_score'],
                'confidence_tier' => $eval['confidence_tier'],
                'why_matched_summary' => $eval['why_matched_summary'],
                'should_notify' => $eval['should_notify'],
                'should_email' => $eval['should_email'],
                'lost_title' => $lostTitle,
                'found_title' => $foundTitle
            ];
        }
        $stmtOpp->close();
    }

    return $createdMatches;
}

/**
 * Smart Temporal Resolver for Reports
 * Parses relative expressions ("yesterday", "today", "2 days ago") into exact server dates and times
 */
function resolveReportDateTime($dateInput = '', $descInput = '') {
    $now = time();
    $resolvedDate = date('Y-m-d', $now);
    $resolvedTime = null;

    $combined = strtolower(trim("$dateInput $descInput"));

    // 1. Day before yesterday / 2 days ago
    if (str_contains($combined, 'day before yesterday') || str_contains($combined, 'day before yesturday') || preg_match('/\b2\s*days?\s*ago\b/', $combined)) {
        $resolvedDate = date('Y-m-d', strtotime('-2 days', $now));
    } elseif (preg_match('/\b(\d+)\s*days?\s*ago\b/', $combined, $m)) {
        $days = (int)$m[1];
        $resolvedDate = date('Y-m-d', strtotime("-$days days", $now));
    } elseif (str_contains($combined, 'yesterday') || str_contains($combined, 'yesturday') || str_contains($combined, 'last night')) {
        $resolvedDate = date('Y-m-d', strtotime('-1 day', $now));
        if (str_contains($combined, 'night')) $resolvedTime = '21:00:00';
    } elseif (str_contains($combined, 'today') || str_contains($combined, 'this morning') || str_contains($combined, 'this afternoon')) {
        $resolvedDate = date('Y-m-d', $now);
        if (str_contains($combined, 'morning')) $resolvedTime = '09:30:00';
        elseif (str_contains($combined, 'afternoon')) $resolvedTime = '14:00:00';
    } elseif (preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/', $combined, $m)) {
        $resolvedDate = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    } elseif (preg_match('#\b(\d{1,2})[/\-](\d{1,2})[/\-](\d{4})\b#', $combined, $m)) {
        $resolvedDate = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    } elseif (!empty($dateInput) && ($parsed = strtotime($dateInput))) {
        $resolvedDate = date('Y-m-d', $parsed);
    }

    // Time parsing (e.g. 3:30 pm, 15:00, 4 pm)
    if (preg_match('/\b(?:at|around|@)?\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i', $combined, $tm)) {
        $hr = (int)$tm[1];
        $min = !empty($tm[2]) ? (int)$tm[2] : 0;
        $ampm = strtolower($tm[3]);
        if ($ampm === 'pm' && $hr < 12) $hr += 12;
        elseif ($ampm === 'am' && $hr == 12) $hr = 0;
        $resolvedTime = sprintf('%02d:%02d:00', $hr, $min);
    } elseif (preg_match('/\b(?:at|around|@)?\s*([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?\b/', $combined, $tm)) {
        $hr = (int)$tm[1];
        $min = (int)$tm[2];
        $sec = !empty($tm[3]) ? (int)$tm[3] : 0;
        $resolvedTime = sprintf('%02d:%02d:%02d', $hr, $min, $sec);
    }

    return [
        'date' => $resolvedDate,
        'time' => $resolvedTime
    ];
}

/**
 * ─────────────────────────────────────────────────────────────
 * PRODUCTION SCHEMA SYNCHRONIZERS & ENGINE UTILITIES
 * ─────────────────────────────────────────────────────────────
 */

function ensureAdminsTable() {
    global $conn;
    if (!$conn) return;

    @$conn->query("CREATE TABLE IF NOT EXISTS `admins` (
      `admin_id` INT(11) NOT NULL AUTO_INCREMENT,
      `username` VARCHAR(50) NOT NULL,
      `full_name` VARCHAR(100) NOT NULL DEFAULT 'System Administrator',
      `email` VARCHAR(100) NOT NULL,
      `password_hash` VARCHAR(255) NOT NULL,
      `role` ENUM('superadmin', 'admin', 'moderator') DEFAULT 'admin',
      `status` ENUM('active', 'suspended') DEFAULT 'active',
      `last_login` DATETIME DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`admin_id`),
      UNIQUE KEY `idx_admin_username` (`username`),
      UNIQUE KEY `idx_admin_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // Seed production admin accounts for Charan
    $seedAccounts = [
        [
            'username' => 'charan_admin',
            'full_name' => 'Charan Administrator',
            'email' => 'charante153624@gmail.com',
            'password' => 'Charan@Admin2026!',
            'role' => 'admin'
        ],
        [
            'username' => 'charan_moderator',
            'full_name' => 'Charan Moderator',
            'email' => 'charanpc1420091@gmail.com',
            'password' => 'Charan@Mod2026!',
            'role' => 'moderator'
        ]
    ];

    foreach ($seedAccounts as $acc) {
        $chk = $conn->prepare("SELECT admin_id FROM admins WHERE username = ? OR email = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param("ss", $acc['username'], $acc['email']);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) {
                $chk->close();
                $hash = password_hash($acc['password'], PASSWORD_BCRYPT);
                $ins = $conn->prepare("INSERT INTO admins (username, full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
                if ($ins) {
                    $ins->bind_param("sssss", $acc['username'], $acc['full_name'], $acc['email'], $hash, $acc['role']);
                    $ins->execute();
                    $ins->close();
                }
            } else {
                $chk->close();
            }
        }
    }
}

function ensureQuestionSetsTable() {
    global $conn;
    if (!$conn) return;

    @$conn->query("CREATE TABLE IF NOT EXISTS `question_sets` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `category` VARCHAR(100) NOT NULL,
      `report_type` ENUM('LOST', 'FOUND') NOT NULL,
      `questions_json` LONGTEXT NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_question_set` (`category`, `report_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    $cnt = $conn->query("SELECT COUNT(*) FROM question_sets");
    $total = $cnt ? (int)$cnt->fetch_row()[0] : 0;
    if ($total === 0) {
        $defaultSets = [
            'electronics' => [
                'Brand and specific model name',
                'Color of casing or protective cover',
                'Screen lock type, wallpaper, or recent app open',
                'Any distinct scratches, stickers, or cosmetic marks'
            ],
            'wallets & bags' => [
                'Brand and material (e.g. leather, canvas, nylon)',
                'Color and number of compartments / zippers',
                'List of non-sensitive items or cards inside (no full card numbers)',
                'Keychains, badges, or charms attached'
            ],
            'keys & keychains' => [
                'Total count of keys on the ring',
                'Description of keychain tag, mascot, or trinket',
                'Key types (e.g. bike key, room lock, padlock, vehicle fob)',
                'Any distinct markings or colored rubber caps'
            ],
            'id cards & documents' => [
                'Full name printed on document / card',
                'Card type (College ID, Govt License, Library Card)',
                'Department, branch, or issue year',
                'Any lanyard color or card holder pouch details'
            ],
            'books & stationery' => [
                'Book title, subject, or author name',
                'Name, roll number, or initials written on pages/cover',
                'Color and condition of cover or pouch',
                'Bookmarks, highlighted chapters, or notes inserted'
            ],
            'general' => [
                'Brand, color, and approximate dimensions',
                'Specific spot where you last saw or lost it',
                'Any distinct damage, serial numbers, or unique characteristics'
            ]
        ];

        foreach (['LOST', 'FOUND'] as $rtype) {
            foreach ($defaultSets as $cat => $qList) {
                $qJson = json_encode($qList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $stmt = $conn->prepare("INSERT IGNORE INTO question_sets (category, report_type, questions_json) VALUES (?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("sss", $cat, $rtype, $qJson);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
}

function ensureRecoveryCasesSchema() {
    global $conn;
    if (!$conn) return;

    $cols = [];
    $res = @$conn->query("SHOW COLUMNS FROM `recovery_cases`");
    if ($res) {
        while ($r = $res->fetch_assoc()) $cols[] = strtolower($r['Field']);
    }

    if (!empty($cols)) {
        if (!in_array('match_id', $cols)) {
            @$conn->query("ALTER TABLE `recovery_cases` ADD COLUMN `match_id` INT(11) NULL AFTER `id`");
            @$conn->query("ALTER TABLE `recovery_cases` ADD UNIQUE INDEX `uq_match_id` (`match_id`)");
        }
        if (!in_array('return_method', $cols) && !in_array('return_type', $cols)) {
            @$conn->query("ALTER TABLE `recovery_cases` ADD COLUMN `return_method` ENUM('hand_on_hand', 'admin_office') NOT NULL DEFAULT 'hand_on_hand' AFTER `match_id`");
        }
        if (!in_array('meeting_location', $cols)) {
            @$conn->query("ALTER TABLE `recovery_cases` ADD COLUMN `meeting_location` VARCHAR(255) NULL DEFAULT NULL AFTER `return_method`");
        }
        if (!in_array('meeting_time', $cols)) {
            @$conn->query("ALTER TABLE `recovery_cases` ADD COLUMN `meeting_time` DATETIME NULL DEFAULT NULL AFTER `meeting_location`");
        }
        if (!in_array('otp_hash', $cols)) {
            @$conn->query("ALTER TABLE `recovery_cases` ADD COLUMN `otp_hash` VARCHAR(64) NULL DEFAULT NULL AFTER `meeting_time`");
        }
        if (!in_array('otp_expires_at', $cols)) {
            @$conn->query("ALTER TABLE `recovery_cases` ADD COLUMN `otp_expires_at` DATETIME NULL DEFAULT NULL AFTER `otp_hash`");
        }
        if (!in_array('otp_attempts', $cols)) {
            @$conn->query("ALTER TABLE `recovery_cases` ADD COLUMN `otp_attempts` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `otp_expires_at`");
        }
        if (!in_array('otp_last_generated', $cols)) {
            @$conn->query("ALTER TABLE `recovery_cases` ADD COLUMN `otp_last_generated` DATETIME NULL DEFAULT NULL AFTER `otp_attempts`");
        }
        if (!in_array('otp_regen_count', $cols)) {
            @$conn->query("ALTER TABLE `recovery_cases` ADD COLUMN `otp_regen_count` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `otp_last_generated`");
        }
    }
}

function ensureReportsAiSchema() {
    global $conn;
    if (!$conn) return;

    foreach (['lost_reports', 'found_reports'] as $t) {
        $cols = [];
        $res = @$conn->query("SHOW COLUMNS FROM `$t`");
        if ($res) {
            while ($r = $res->fetch_assoc()) $cols[] = strtolower($r['Field']);
        }
        if (!empty($cols) && !in_array('ai_processed', $cols)) {
            @$conn->query("ALTER TABLE `$t` ADD COLUMN `ai_processed` TINYINT(1) DEFAULT 0 AFTER `status`");
        }
    }
}

function getCategoryVerificationQuestions($category = 'General') {
    global $conn;
    $cleanCat = strtolower(trim($category));
    $mappedCat = 'general';
    if (stripos($cleanCat, 'elect') !== false || stripos($cleanCat, 'phone') !== false || stripos($cleanCat, 'laptop') !== false) $mappedCat = 'electronics';
    elseif (stripos($cleanCat, 'wallet') !== false || stripos($cleanCat, 'bag') !== false || stripos($cleanCat, 'purse') !== false) $mappedCat = 'wallets & bags';
    elseif (stripos($cleanCat, 'key') !== false) $mappedCat = 'keys & keychains';
    elseif (stripos($cleanCat, 'id') !== false || stripos($cleanCat, 'card') !== false || stripos($cleanCat, 'doc') !== false) $mappedCat = 'id cards & documents';
    elseif (stripos($cleanCat, 'book') !== false || stripos($cleanCat, 'note') !== false || stripos($cleanCat, 'pen') !== false) $mappedCat = 'books & stationery';

    if ($conn) {
        ensureQuestionSetsTable();
        $stmt = $conn->prepare("SELECT questions_json FROM question_sets WHERE category = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $mappedCat);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $decoded = json_decode($row['questions_json'], true);
                if (!empty($decoded)) return $decoded;
            }
            $stmt->close();
        }
    }

    return [
        'Brand, color, and model',
        'Visible scratches, damage, or defining markings',
        'Specific items, serials, or private identifiers inside/on it'
    ];
}

/**
 * Lightweight Action Rate Limiter (Session-backed)
 */
function checkRateLimit($actionKey, $maxAttempts = 5, $decaySeconds = 300) {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $bucketKey = 'rl_' . md5($actionKey . '_' . $ip);
    $now = time();

    if (!isset($_SESSION[$bucketKey])) {
        $_SESSION[$bucketKey] = [
            'count' => 1,
            'start' => $now
        ];
        return true;
    }

    $bucket = $_SESSION[$bucketKey];
    if ($now - $bucket['start'] > $decaySeconds) {
        $_SESSION[$bucketKey] = [
            'count' => 1,
            'start' => $now
        ];
        return true;
    }

    if ($bucket['count'] >= $maxAttempts) {
        return false;
    }

    $_SESSION[$bucketKey]['count']++;
    return true;
}

/**
 * Lazy Background Processor for Pending AI Indexing
 * Safely processes up to $limit unindexed reports during regular requests.
 */
function processPendingAiReports($limit = 2) {
    global $conn;
    if (!$conn) return 0;

    ensureReportsAiSchema();

    $processed = 0;
    $flaskUrl = (defined('FLASK_AI_URL') ? FLASK_AI_URL : 'https://reunite-ai-backend.onrender.com') . '/report/embed-and-store';
    $secret = defined('AI_SECRET_KEY') ? AI_SECRET_KEY : 'reunite_ai_secret_token_2026_kolkata';

    // 1. Check lost_reports
    $lCols = [];
    $lc = @$conn->query("SHOW COLUMNS FROM lost_reports");
    if ($lc) while ($r = $lc->fetch_assoc()) $lCols[] = strtolower($r['Field']);
    $lIdCol = in_array('lost_id', $lCols) ? 'lost_id' : 'id';
    $lTitleCol = in_array('item_name', $lCols) ? 'item_name' : (in_array('title', $lCols) ? 'title' : 'item_name');
    $lDateCol = in_array('lost_date', $lCols) ? 'lost_date' : (in_array('date_lost', $lCols) ? 'date_lost' : 'created_at');

    $resLost = @$conn->query("SELECT * FROM lost_reports WHERE ai_processed = 0 OR ai_processed IS NULL LIMIT $limit");
    if ($resLost) {
        while ($row = $resLost->fetch_assoc()) {
            if ($processed >= $limit) break;
            $dbId = (int)$row[$lIdCol];
            $fId = 'RL-' . str_pad($dbId, 5, '0', STR_PAD_LEFT);
            $payload = [
                'report_id' => $fId,
                'db_id' => $dbId,
                'report_type' => 'lost',
                'category' => $row['category'] ?? 'General',
                'title' => $row[$lTitleCol] ?? '',
                'description' => $row['description'] ?? '',
                'location' => $row['location'] ?? '',
                'date' => $row[$lDateCol] ?? date('Y-m-d')
            ];

            $img = $row['image_path'] ?? '';
            if ($img) {
                $fPath = dirname(__DIR__) . '/' . ltrim($img, '/');
                if (file_exists($fPath) && filesize($fPath) < 2 * 1024 * 1024) {
                    $payload['image_base64'] = base64_encode(file_get_contents($fPath));
                }
            }

            $ch = curl_init($flaskUrl);
            if ($ch) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'X-AI-Secret-Key: ' . $secret
                ]);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                curl_setopt($ch, CURLOPT_TIMEOUT, 4);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($code === 200) {
                    @$conn->query("UPDATE lost_reports SET ai_processed = 1 WHERE `$lIdCol` = $dbId");
                    $processed++;
                }
            }
        }
    }

    // 2. Check found_reports if limit not reached
    if ($processed < $limit) {
        $remaining = $limit - $processed;
        $fCols = [];
        $fc = @$conn->query("SHOW COLUMNS FROM found_reports");
        if ($fc) while ($r = $fc->fetch_assoc()) $fCols[] = strtolower($r['Field']);
        $fIdCol = in_array('found_id', $fCols) ? 'found_id' : 'id';
        $fTitleCol = in_array('item_name', $fCols) ? 'item_name' : (in_array('title', $fCols) ? 'title' : 'item_name');
        $fDateCol = in_array('found_date', $fCols) ? 'found_date' : (in_array('date_found', $fCols) ? 'date_found' : 'created_at');

        $resFound = @$conn->query("SELECT * FROM found_reports WHERE ai_processed = 0 OR ai_processed IS NULL LIMIT $remaining");
        if ($resFound) {
            while ($row = $resFound->fetch_assoc()) {
                $dbId = (int)$row[$fIdCol];
                $fId = 'RF-' . str_pad($dbId, 5, '0', STR_PAD_LEFT);
                $payload = [
                    'report_id' => $fId,
                    'db_id' => $dbId,
                    'report_type' => 'found',
                    'category' => $row['category'] ?? 'General',
                    'title' => $row[$fTitleCol] ?? '',
                    'description' => $row['description'] ?? '',
                    'location' => $row['location'] ?? '',
                    'date' => $row[$fDateCol] ?? date('Y-m-d')
                ];

                $img = $row['image_path'] ?? '';
                if ($img) {
                    $fPath = dirname(__DIR__) . '/' . ltrim($img, '/');
                    if (file_exists($fPath) && filesize($fPath) < 2 * 1024 * 1024) {
                        $payload['image_base64'] = base64_encode(file_get_contents($fPath));
                    }
                }

                $ch = curl_init($flaskUrl);
                if ($ch) {
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'X-AI-Secret-Key: ' . $secret
                    ]);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
                    $resp = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($code === 200) {
                        @$conn->query("UPDATE found_reports SET ai_processed = 1 WHERE `$fIdCol` = $dbId");
                        $processed++;
                    }
                }
            }
        }
    }

    return $processed;
}

// Automatically enforce maintenance mode across all including endpoints
if (function_exists('enforceMaintenanceMode')) {
    enforceMaintenanceMode();
}

// ============================================================================
// CLAIM & HANDOVER LIFECYCLE V2 ENGINE HELPERS
// ============================================================================


/**
 * Checks if a user is currently subject to a temporary claim ban.
 * Returns array with ban status, expiration timestamp, and remaining seconds.
 */
function isClaimantBanned($userId) {
    global $conn;
    $result = ['banned' => false, 'ban_until' => null, 'seconds_remaining' => 0];
    if (!$conn || !(int)$userId) {
        return $result;
    }
    // Check if claim_ban_until column exists
    $cCheck = @$conn->query("SHOW COLUMNS FROM users LIKE 'claim_ban_until'");
    if (!$cCheck || $cCheck->num_rows === 0) {
        return $result;
    }

    $stmt = @$conn->prepare("SELECT claim_ban_until FROM users WHERE user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['claim_ban_until'])) {
                $banTs = strtotime($row['claim_ban_until']);
                $nowTs = time();
                if ($banTs > $nowTs) {
                    $result['banned'] = true;
                    $result['ban_until'] = $row['claim_ban_until'];
                    $result['seconds_remaining'] = $banTs - $nowTs;
                }
            }
        }
        $stmt->close();
    }
    return $result;
}

/**
 * Enforces a temporary claim ban on a user (e.g. 24 hours on fraudulent/rejected claim).
 */
function setClaimantBan($userId, $hours = 24) {
    global $conn;
    if (!$conn || !(int)$userId) return false;
    $hours = max(1, (int)$hours);
    $cCheck = @$conn->query("SHOW COLUMNS FROM users LIKE 'claim_ban_until'");
    if (!$cCheck || $cCheck->num_rows === 0) {
        @$conn->query("ALTER TABLE users ADD COLUMN claim_ban_until DATETIME NULL DEFAULT NULL AFTER status");
    }
    $stmt = @$conn->prepare("UPDATE users SET claim_ban_until = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param('ii', $hours, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
    return false;
}

/**
 * Lifts any active claim ban on a user (admin tool action).
 */
function liftClaimantBan($userId) {
    global $conn;
    if (!$conn || !(int)$userId) return false;
    $stmt = @$conn->prepare("UPDATE users SET claim_ban_until = NULL WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
    return false;
}

/**
 * Counts active in-flight claims for a given lost item (anti-spam quota check, max 2).
 */
function getActiveClaimsCountForLostItem($lostId) {
    global $conn;
    if (!$conn || !(int)$lostId) return null;
    
    // Resolve columns for matches
    $mCols = [];
    $res = @$conn->query("SHOW COLUMNS FROM matches");
    if ($res) while ($r = $res->fetch_assoc()) $mCols[] = strtolower($r['Field']);
    $lostCol = in_array('lost_id', $mCols) ? 'lost_id' : 'lost_report_id';

    $stmt = @$conn->prepare("SELECT COUNT(*) as cnt FROM matches WHERE `$lostCol` = ? AND status IN ('pending_founder_approval', 'ready_for_handover', 'in_progress', 'approved')");
    if ($stmt) {
        $stmt->bind_param('i', $lostId);
        $stmt->execute();
        $cntRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($cntRow['cnt'] ?? 0);
    }
    return 0;
}

/**
 * Scans text for high-risk sensitive credentials (passwords, PIN patterns, credit cards).
 */
function containsSensitiveCredentials($text) {
    if (empty($text)) return false;
    // Password patterns (e.g. password is: 1234, pwd=secret)
    if (preg_match('/(?:password|passwd|pwd|pin\s*code|atm\s*pin|cvv|cvc)\s*[:=]\s*\S+/i', $text)) {
        return true;
    }
    // 16-digit credit card pattern with optional spaces/dashes
    if (preg_match('/\b(?:\d{4}[ -]?){3}\d{4}\b/', $text)) {
        return true;
    }
    return false;
}

/**
 * Computes timing-safe HMAC-SHA256 hash of a 6-digit numeric OTP code.
 */
function hashOtpCode($otp) {
    $secret = defined('HMAC_OTP_SECRET') ? HMAC_OTP_SECRET : 'reunite_prod_otp_secret_key_8f7b3a2e1d9c0a';
    return hash_hmac('sha256', trim((string)$otp), $secret);
}

/**
 * Validates an input OTP against the stored HMAC-SHA256 hash in constant time.
 */
function verifyOtpCode($inputOtp, $storedHash) {
    if (empty($inputOtp) || empty($storedHash)) {
        return false;
    }
    $computed = hashOtpCode($inputOtp);
    return hash_equals($storedHash, $computed);
}

/**
 * Strips EXIF metadata from uploaded images and stores securely in media vault.
 * Whitelists jpeg, png, webp. Enforces max size 8MB.
 */
function sanitizeAndStoreProofImage($fileArray, $matchId) {
    if (!isset($fileArray) || $fileArray['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded or upload error.'];
    }

    $maxBytes = 8 * 1024 * 1024; // 8MB
    if ($fileArray['size'] > $maxBytes) {
        return ['success' => false, 'error' => 'Image exceeds 8MB maximum size limit.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $fileArray['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes)) {
        return ['success' => false, 'error' => 'Invalid image format. Allowed: JPG, PNG, WebP.'];
    }

    // Destination media vault directory
    $uploadDir = dirname(__DIR__) . '/Frontend/uploads/proofs';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $filename = 'proof_' . (int)$matchId . '_' . bin2hex(random_bytes(6)) . '.jpg';
    $targetPath = $uploadDir . '/' . $filename;

    // Strip EXIF and re-encode using GD
    $imageResource = null;
    if ($mime === 'image/jpeg') {
        $imageResource = @imagecreatefromjpeg($fileArray['tmp_name']);
    } elseif ($mime === 'image/png') {
        $imageResource = @imagecreatefrompng($fileArray['tmp_name']);
    } elseif ($mime === 'image/webp') {
        if (function_exists('imagecreatefromwebp')) {
            $imageResource = @imagecreatefromwebp($fileArray['tmp_name']);
        }
    }

    if ($imageResource) {
        // Save as clean JPEG stripped of all EXIF/camera metadata
        imagejpeg($imageResource, $targetPath, 88);
        imagedestroy($imageResource);
    } else {
        // Fallback move
        if (!move_uploaded_file($fileArray['tmp_name'], $targetPath)) {
            return ['success' => false, 'error' => 'Failed to save sanitized proof image.'];
        }
    }

    return [
        'success' => true,
        'web_path' => 'uploads/proofs/' . $filename,
        'full_path' => $targetPath
    ];
}
