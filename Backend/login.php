<?php
/**
 * Reunite User Login Handler
 * Authenticates students via College PIN, Email, or Phone
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

$data = getRequestData();
$isApi = isApiRequest();

// If already logged in and it's a browser form GET request
if ($_SERVER["REQUEST_METHOD"] === "GET" && isUserLoggedIn() && !$isApi) {
    header("Location: " . FRONTEND_URL . "/home.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" && empty($data)) {
    if ($isApi) {
        sendJsonResponse(false, "Method not allowed. Use POST.", [], 405);
    } else {
        header("Location: " . FRONTEND_URL . "/login.php");
        exit();
    }
}

// Extract credentials
$identifier = trim($data['identifier'] ?? $data['pin'] ?? $data['email'] ?? '');
$password = $data['password'] ?? '';

if (empty($identifier) || empty($password)) {
    if ($isApi) {
        sendJsonResponse(false, "Please provide your College PIN/Email and Password.", [], 400);
    } else {
        header("Location: " . FRONTEND_URL . "/login.php?error=" . urlencode("Please enter credentials."));
        exit();
    }
}

if (!$conn) {
    if ($isApi) {
        sendJsonResponse(false, "Database connection error. Please verify MySQL server is running.", [], 500);
    } else {
        header("Location: " . FRONTEND_URL . "/login.php?error=" . urlencode("Database connection error."));
        exit();
    }
}

$encrypted_identifier = encryptData($identifier);

// Lookup user by PIN, Plain Email, Encrypted Email, Plain Phone, or Encrypted Phone
$sql = "SELECT user_id, full_name, pin, email, phone, password_hash, trust_score, role, status, dob, college, branch FROM users WHERE pin = ? OR email = ? OR email = ? OR phone = ? OR phone = ? LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    if ($isApi) {
        sendJsonResponse(false, "Database query error: " . $conn->error, [], 500);
    } else {
        header("Location: " . FRONTEND_URL . "/login.php?error=" . urlencode("System error."));
        exit();
    }
}

$stmt->bind_param("sssss", $identifier, $encrypted_identifier, $identifier, $encrypted_identifier, $identifier);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $stmt->close();

    // Check account status
    if (isset($user['status']) && strtolower($user['status']) === 'blocked') {
        if ($isApi) {
            sendJsonResponse(false, "Your account is suspended. Please contact campus admin.", [], 403);
        } else {
            header("Location: " . FRONTEND_URL . "/login.php?error=" . urlencode("Account is blocked."));
            exit();
        }
    }

    // Verify Password
    if (password_verify($password, $user['password_hash'])) {
        $rawEmail = decryptData($user['email']);
        $rawPhone = decryptData($user['phone']);

        // Save session state
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['pin'] = $user['pin'] ?: $identifier;
        $_SESSION['email'] = $rawEmail;
        $_SESSION['phone'] = $rawPhone;
        $_SESSION['role'] = $user['role'];
        $_SESSION['college'] = $user['college'];
        $_SESSION['branch'] = $user['branch'];
        $_SESSION['trust_score'] = $user['trust_score'];

        // Audit Log
        logAccess($user['user_id'], ($user['role'] === 'admin' ? 'Admin Login' : 'Student Login'));

        if ($isApi) {
            sendJsonResponse(true, "Successfully logged in!", [
                'redirect' => FRONTEND_URL . "/home.php",
                'user' => [
                    'user_id' => $user['user_id'],
                    'full_name' => $user['full_name'],
                    'pin' => $user['pin'],
                    'email' => $rawEmail,
                    'college' => $user['college'],
                    'branch' => $user['branch'],
                    'trust_score' => $user['trust_score']
                ]
            ]);
        } else {
            header("Location: " . FRONTEND_URL . "/home.php");
            exit();
        }
    } else {
        if ($isApi) {
            sendJsonResponse(false, "Incorrect password. Please try again.", [], 401);
        } else {
            header("Location: " . FRONTEND_URL . "/login.php?error=" . urlencode("Incorrect password."));
            exit();
        }
    }
} else {
    $stmt->close();
    if ($isApi) {
        sendJsonResponse(false, "No student account found with that College PIN or Email.", [], 404);
    } else {
        header("Location: " . FRONTEND_URL . "/login.php?error=" . urlencode("User not found."));
        exit();
    }
}
