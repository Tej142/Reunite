<?php
/**
 * Reunite Backend Helper Functions
 * Clean database, authentication, and security utilities
 */

require_once __DIR__ . '/config/config.php';

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
    $isJson = function_exists('isApiRequest') && isApiRequest();
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
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $inFrontend = str_contains($uri, '/Frontend/') || file_exists('maintenance.php');
    $target = $inFrontend ? 'maintenance.php' : 'Frontend/maintenance.php';
    header('HTTP/1.1 503 Service Unavailable');
    header('Retry-After: ' . $retryAfter);
    header('Location: ' . $target);
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
 * Executes Vector + Hybrid Real-Time Matchmaking and generates user notifications
 */
function runRealtimeMatchmaking($insertedId, $reportType, $category, $title, $description, $location, $imageWebPath, $userId) {
    global $conn;
    if (!$conn || !$insertedId) return [];

    $createdMatches = [];
    $targetType = ($reportType === 'found') ? 'lost' : 'found';
    $targetTable = ($reportType === 'found') ? 'lost_reports' : 'found_reports';

    // 1. Query Vector Matches from Flask AI Engine
    $vectorMatches = [];
    try {
        $flaskUrl = (defined('FLASK_AI_URL') ? FLASK_AI_URL : 'http://127.0.0.1:5000') . '/report/search-matches';
        $searchPayload = [
            'text' => "$category. $title. $description. $location.",
            'report_id' => ($reportType === 'found' ? 'RF-' : 'RL-') . str_pad($insertedId, 5, '0', STR_PAD_LEFT),
            'report_type' => $targetType,
            'image_path' => $imageWebPath,
            'top_k' => 10
        ];
        $ch = curl_init($flaskUrl);
        if ($ch) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($searchPayload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
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

    $matchedDbIds = [];

    // Process Vector Matches
    foreach ($vectorMatches as $vm) {
        $meta = $vm['metadata'] ?? [];
        $matchedId = (int)($meta['db_id'] ?? 0);
        $score = (float)($vm['match_percentage'] ?? 0);

        if ($matchedId > 0 && $score >= 35.0) {
            $matchedDbIds[$matchedId] = $score;
        }
    }

    // 2. Hybrid / Fallback DB Similarity Matcher
    $queryOpposite = "SELECT id, user_id, title, description, category, location FROM `$targetTable` WHERE status = 'active' AND id != ?";
    $stmtOpp = $conn->prepare($queryOpposite);
    if ($stmtOpp) {
        $stmtOpp->bind_param("i", $insertedId);
        $stmtOpp->execute();
        $resOpp = $stmtOpp->get_result();
        
        $tokens1 = array_unique(array_filter(preg_split('/[\s,\.\-_]+/', strtolower("$title $description $category $location"))));
        
        while ($opp = $resOpp->fetch_assoc()) {
            $oppId = (int)$opp['id'];
            if (isset($matchedDbIds[$oppId])) continue; // Already matched by vector engine

            $oppTokens = array_unique(array_filter(preg_split('/[\s,\.\-_]+/', strtolower("{$opp['title']} {$opp['description']} {$opp['category']} {$opp['location']}"))));
            $common = array_intersect($tokens1, $oppTokens);
            
            // Remove common stop words
            $stopWords = ['the','a','an','in','on','at','near','by','is','was','of','for','and','or','with','i','my','to','from'];
            $meaningfulCommon = array_diff($common, $stopWords);

            $score = 0;
            if (strcasecmp($category, $opp['category']) === 0 && !empty($category) && $category !== 'General') {
                $score += 40;
            }
            if (!empty($meaningfulCommon)) {
                $score += min(count($meaningfulCommon) * 20, 50);
            }
            if (!empty($location) && !empty($opp['location']) && (stripos($location, $opp['location']) !== false || stripos($opp['location'], $location) !== false)) {
                $score += 15;
            }

            if ($score >= 45) {
                $matchedDbIds[$oppId] = min($score, 98.0);
            }
        }
        $stmtOpp->close();
    }

    // 3. Insert into `matches` and trigger `notifications`
    foreach ($matchedDbIds as $oppId => $score) {
        $lostId = ($reportType === 'found') ? $oppId : $insertedId;
        $foundId = ($reportType === 'found') ? $insertedId : $oppId;

        // Check if match already exists
        $checkStmt = $conn->prepare("SELECT id FROM matches WHERE lost_report_id = ? AND found_report_id = ?");
        if ($checkStmt) {
            $checkStmt->bind_param("ii", $lostId, $foundId);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result();
            if ($checkRes->num_rows > 0) {
                $checkStmt->close();
                continue; // Already recorded
            }
            $checkStmt->close();
        }

        // Insert into matches table
        $insMatch = $conn->prepare("INSERT INTO matches (lost_report_id, found_report_id, similarity_score, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
        $matchId = null;
        if ($insMatch) {
            $scoreDecimal = number_format($score, 2, '.', '');
            $insMatch->bind_param("iid", $lostId, $foundId, $scoreDecimal);
            $insMatch->execute();
            $matchId = $insMatch->insert_id;
            $insMatch->close();
        }

        // Fetch details of both reports to construct personalized notifications
        $lostInfo = $conn->query("SELECT user_id, title FROM lost_reports WHERE id = $lostId")->fetch_assoc();
        $foundInfo = $conn->query("SELECT user_id, title FROM found_reports WHERE id = $foundId")->fetch_assoc();

        if ($lostInfo && $foundInfo) {
            $lostUserId = (int)$lostInfo['user_id'];
            $foundUserId = (int)$foundInfo['user_id'];
            $emojiRegex = '/[\x{1F300}-\x{1FAFF}\x{1F000}-\x{1F2FF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200D}]/u';
            $lostTitle = trim(preg_replace('/\s+/', ' ', preg_replace($emojiRegex, '', $lostInfo['title'] ?? '')));
            $foundTitle = trim(preg_replace('/\s+/', ' ', preg_replace($emojiRegex, '', $foundInfo['title'] ?? '')));
            $scoreDisplay = round($score) . '%';

            // Notification for Lost Report Owner
            if ($lostUserId > 0) {
                $nTitle = "Potential Match: " . substr($foundTitle, 0, 40);
                $nMsg = "A found item '" . $foundTitle . "' matches your lost report '" . $lostTitle . "' with {$scoreDisplay} similarity.";
                $nLink = "item.php?match_id=" . ($matchId ?: 1);
                $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, report_type, report_id, type, message, link, is_read, created_at) VALUES (?, ?, 'LOST', ?, 'match', ?, ?, 0, NOW())");
                if ($nStmt) {
                    $nStmt->bind_param("isiss", $lostUserId, $nTitle, $lostId, $nMsg, $nLink);
                    $nStmt->execute();
                    $nStmt->close();
                }
            }

            // Notification for Found Report Owner (if different user)
            if ($foundUserId > 0 && $foundUserId !== $lostUserId) {
                $nTitle = "Potential Match: " . substr($lostTitle, 0, 40);
                $nMsg = "Your reported found item '" . $foundTitle . "' has a {$scoreDisplay} match with a lost item ('" . $lostTitle . "').";
                $nLink = "item.php?match_id=" . ($matchId ?: 1);
                $nStmt = $conn->prepare("INSERT INTO notifications (user_id, title, report_type, report_id, type, message, link, is_read, created_at) VALUES (?, ?, 'FOUND', ?, 'match', ?, ?, 0, NOW())");
                if ($nStmt) {
                    $nStmt->bind_param("isiss", $foundUserId, $nTitle, $foundId, $nMsg, $nLink);
                    $nStmt->execute();
                    $nStmt->close();
                }
            }

            $createdMatches[] = [
                'match_id' => $matchId,
                'lost_id' => $lostId,
                'found_id' => $foundId,
                'similarity_score' => $score,
                'lost_title' => $lostTitle,
                'found_title' => $foundTitle
            ];
        }
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



