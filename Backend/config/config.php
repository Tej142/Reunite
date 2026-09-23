<?php
/**
 * Reunite Database & Global Configuration
 * Database: lost_connect_db
 */

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Automatically load environment variables from .env if present
(function() {
    $envPaths = [
        __DIR__ . '/.env',
        dirname(__DIR__) . '/.env',
        dirname(__DIR__, 2) . '/.env'
    ];
    foreach ($envPaths as $path) {
        if (file_exists($path) && is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0 || strpos($line, ';') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $val) = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim($val, " \t\n\r\0\x0B\"'");
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = $val;
                        putenv("$key=$val");
                    }
                }
            }
            break;
        }
    }
})();

// Helper to read env value from $_ENV or getenv()
function get_config_val($key, $default = '') {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    return $default;
}

// Database Credentials
define('DB_HOST', get_config_val('DB_HOST', 'localhost'));
define('DB_USER', get_config_val('DB_USER', 'root'));
define('DB_PASS', get_config_val('DB_PASS', ''));
define('DB_NAME', get_config_val('DB_NAME', 'lost_connect_db'));
define('DB_PORT', (int)get_config_val('DB_PORT', 3306));

// Encryption & Security Keys
define('ENCRYPTION_KEY', get_config_val('APP_ENC_KEY', 'reunite_secret_encryption_key_2024'));
define('ENCRYPTION_IV', substr(hash('sha256', 'reunite_iv_2024'), 0, 16));

// ── Email API Provider Configuration (Paste API Key in .env or below) ──
// 1. Brevo (Sendinblue) API (https://app.brevo.com/settings/keys/api)
define('BREVO_API_KEY', get_config_val('BREVO_API_KEY', ''));
define('BREVO_SENDER_EMAIL', get_config_val('BREVO_SENDER_EMAIL', 'charante153624@gmail.com'));
define('BREVO_SENDER_NAME', get_config_val('BREVO_SENDER_NAME', 'REUNITE TEAM'));

// 2. Resend API (https://resend.com/api-keys)
define('RESEND_API_KEY', get_config_val('RESEND_API_KEY', ''));
define('RESEND_SENDER_EMAIL', get_config_val('RESEND_SENDER_EMAIL', 'onboarding@resend.dev'));
define('RESEND_SENDER_NAME', get_config_val('RESEND_SENDER_NAME', 'REUNITE TEAM'));

// 3. Direct Gmail / Standard SMTP Configuration
define('SMTP_HOST', get_config_val('SMTP_HOST', ''));
define('SMTP_PORT', (int)get_config_val('SMTP_PORT', 587));
define('SMTP_USER', get_config_val('SMTP_USER', ''));
define('SMTP_PASS', get_config_val('SMTP_PASS', ''));
define('SMTP_FROM_EMAIL', get_config_val('SMTP_FROM_EMAIL', get_config_val('SMTP_USER', 'charante153624@gmail.com')));
define('SMTP_FROM_NAME', get_config_val('SMTP_FROM_NAME', 'REUNITE TEAM'));

// Python Flask AI Backend Endpoint (Render cloud backend in production, local in dev)
$isLocalHost = in_array(strtolower($_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost')), ['localhost', '127.0.0.1']);
define('FLASK_AI_URL', get_config_val('FLASK_BACKEND_URL', $isLocalHost ? 'http://127.0.0.1:5000' : 'https://reunite-ai-backend.onrender.com'));

// App URLs & Paths
define('BASE_PATH', dirname(__DIR__));
define('FRONTEND_URL', '../Frontend');

// Establish MySQLi Database Connection
$conn = null;
try {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);

    if ($conn->connect_error) {
        // Log connection error without crashing immediately for non-DB endpoints
        error_log("Database Connection Failed: " . $conn->connect_error);
    } else {
        $conn->set_charset("utf8mb4");
    }
} catch (Exception $e) {
    error_log("Database Exception: " . $e->getMessage());
    $conn = null;
}

/**
 * Returns active database connection or null
 */
function get_db_connection() {
    global $conn;
    return $conn;
}
