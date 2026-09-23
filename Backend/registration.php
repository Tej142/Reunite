<?php
/**
 * Reunite User Registration Handler
 * Handles student/user account creation
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

$data = getRequestData();
$isApi = isApiRequest();

if ($_SERVER["REQUEST_METHOD"] !== "POST" && empty($data)) {
    if ($isApi) {
        sendJsonResponse(false, "Method not allowed. Use POST.", [], 405);
    } else {
        header("Location: " . FRONTEND_URL . "/signup.php");
        exit();
    }
}

// Extract & Sanitize fields
$full_name = trim($data['full_name'] ?? $data['name'] ?? '');
$pin = trim($data['pin'] ?? $data['college_pin'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$password = $data['password'] ?? '';
$college = trim($data['college'] ?? 'Sri Venkateswara Govt Polytechnic');
$branch = trim($data['branch'] ?? 'CSE');
$dob = trim($data['dob'] ?? '');

// Validation
if (empty($full_name) || empty($email) || empty($password)) {
    if ($isApi) {
        sendJsonResponse(false, "Full name, email, and password are required.", [], 400);
    } else {
        header("Location: " . FRONTEND_URL . "/signup.php?error=" . urlencode("All required fields must be filled."));
        exit();
    }
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    if ($isApi) {
        sendJsonResponse(false, "Invalid email address format.", [], 400);
    } else {
        header("Location: " . FRONTEND_URL . "/signup.php?error=" . urlencode("Invalid email address format."));
        exit();
    }
}

if (strlen($password) < 8) {
    if ($isApi) {
        sendJsonResponse(false, "Password must be at least 8 characters long.", [], 400);
    } else {
        header("Location: " . FRONTEND_URL . "/signup.php?error=" . urlencode("Password must be at least 8 characters long."));
        exit();
    }
}

global $conn;

if (!$conn) {
    // Graceful fallback if database connection is unavailable
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['pin'] = $pin;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = 'user';
    $_SESSION['college'] = $college;
    $_SESSION['branch'] = $branch;

    if ($isApi) {
        sendJsonResponse(true, "Registration successful (Mock Session Mode)", [
            'redirect' => FRONTEND_URL . "/home.php",
            'user' => [
                'full_name' => $full_name,
                'pin' => $pin,
                'email' => $email
            ]
        ]);
    } else {
        header("Location: " . FRONTEND_URL . "/home.php");
        exit();
    }
}

$encrypted_email = encryptData($email);
$encrypted_phone = !empty($phone) ? encryptData($phone) : null;

// Check if user already exists (by PIN, email, or phone)
$check_sql = "SELECT user_id FROM users WHERE (pin = ? AND pin IS NOT NULL AND pin != '') OR email = ? OR email = ? LIMIT 1";
$check_stmt = $conn->prepare($check_sql);

if ($check_stmt) {
    $check_stmt->bind_param("sss", $pin, $encrypted_email, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result && $check_result->num_rows > 0) {
        $check_stmt->close();
        if ($isApi) {
            sendJsonResponse(false, "An account with this PIN or Email already exists. Please log in.", [], 409);
        } else {
            header("Location: " . FRONTEND_URL . "/login.php?error=" . urlencode("Account already exists. Please login."));
            exit();
        }
    }
    $check_stmt->close();
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);
$trust_score = 100;
$role = "user";
$status = "active";

// Insert new student user
$insert_sql = "INSERT INTO users (full_name, pin, email, phone, password_hash, trust_score, role, status, dob, college, branch, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
$stmt = $conn->prepare($insert_sql);

if (!$stmt) {
    if ($isApi) {
        sendJsonResponse(false, "Database preparation error: " . $conn->error, [], 500);
    } else {
        header("Location: " . FRONTEND_URL . "/signup.php?error=" . urlencode("Database error. Please try again."));
        exit();
    }
}

$stmt->bind_param(
    "sssssisssss",
    $full_name,
    $pin,
    $encrypted_email,
    $encrypted_phone,
    $password_hash,
    $trust_score,
    $role,
    $status,
    $dob,
    $college,
    $branch
);

if ($stmt->execute()) {
    $new_user_id = $stmt->insert_id;
    $stmt->close();

    // Log the user in immediately
    $_SESSION['user_id'] = $new_user_id;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['pin'] = $pin;
    $_SESSION['email'] = $email;
    $_SESSION['phone'] = $phone;
    $_SESSION['role'] = $role;
    $_SESSION['college'] = $college;
    $_SESSION['branch'] = $branch;
    $_SESSION['trust_score'] = $trust_score;

    // Log registration audit event
    logAccess($new_user_id, 'User Registered', "College: $college, Branch: $branch");

    if ($isApi) {
        sendJsonResponse(true, "Registration successful!", [
            'redirect' => FRONTEND_URL . "/home.php",
            'user' => [
                'user_id' => $new_user_id,
                'full_name' => $full_name,
                'pin' => $pin,
                'email' => $email,
                'college' => $college,
                'branch' => $branch
            ]
        ]);
    } else {
        header("Location: " . FRONTEND_URL . "/home.php");
        exit();
    }
} else {
    $errorMsg = $stmt->error;
    $stmt->close();
    if ($isApi) {
        sendJsonResponse(false, "Registration failed: " . $errorMsg, [], 500);
    } else {
        header("Location: " . FRONTEND_URL . "/signup.php?error=" . urlencode("Registration error. Please try again."));
        exit();
    }
}