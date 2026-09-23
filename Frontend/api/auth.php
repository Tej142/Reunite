<?php
/**
 * Frontend Auth API Bridge
 * Dispatches to Backend authentication & profile handlers
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../Backend/config/config.php';
require_once __DIR__ . '/../../Backend/functions.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';
global $conn;

if ($action === 'login') {
    $identifier = trim($input['identifier'] ?? $input['pin'] ?? $input['email'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    if ($conn) {
        $encrypted_id = encryptData($identifier);
        $sql = "SELECT user_id, full_name, pin, email, phone, password_hash, trust_score, role, status, college, branch FROM users WHERE pin = ? OR email = ? OR email = ? OR phone = ? OR phone = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssss", $identifier, $encrypted_id, $identifier, $encrypted_id, $identifier);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows === 1) {
                $user = $res->fetch_assoc();
                $stmt->close();

                if (isset($user['status']) && strtolower($user['status']) === 'blocked') {
                    echo json_encode(['success' => false, 'message' => 'Account is suspended. Contact admin.']);
                    exit;
                }

                if (password_verify($password, $user['password_hash'])) {
                    login_user([
                        'user_id' => $user['user_id'],
                        'name' => $user['full_name'],
                        'pin' => $user['pin'] ?: $identifier,
                        'email' => decryptData($user['email']),
                        'phone' => decryptData($user['phone']),
                        'college' => $user['college'],
                        'branch' => $user['branch'],
                        'trust_score' => $user['trust_score'],
                        'role' => $user['role']
                    ]);
                    logAccess($user['user_id'], 'Student Login');
                    echo json_encode(['success' => true, 'redirect' => 'home.php', 'user' => get_current_user_data()]);
                    exit;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Incorrect password. Please try again.']);
                    exit;
                }
            } else {
                $stmt->close();
                echo json_encode(['success' => false, 'message' => 'No account found with this College PIN or Email.']);
                exit;
            }
        }
    }

    echo json_encode(['success' => false, 'message' => 'Database connection failed or account not found.']);
    exit;
}

// ── 1. SEND OTP VIA BREVO API ────────────────────────────
if ($action === 'send_otp') {
    $email = trim($input['email'] ?? '');
    $name = trim($input['name'] ?? $input['full_name'] ?? 'Student');
    $pin = trim($input['pin'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
        exit;
    }

    // Check if account already exists with PIN or Email
    if ($conn) {
        $encrypted_email = encryptData($email);
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE (pin = ? AND pin IS NOT NULL AND pin != '') OR email = ? OR email = ? LIMIT 1");
        if ($check_stmt) {
            $check_stmt->bind_param("sss", $pin, $encrypted_email, $email);
            $check_stmt->execute();
            $check_res = $check_stmt->get_result();
            if ($check_res && $check_res->num_rows > 0) {
                $check_stmt->close();
                echo json_encode(['success' => false, 'message' => 'An account already exists with this College PIN or Email.']);
                exit;
            }
            $check_stmt->close();
        }
    }

    // Generate 6-digit OTP
    $otp = generateEmailOtp($email);

    // Branded HTML Email Template
    $htmlEmail = '
    <div style="max-width: 520px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #1C1917; color: #F5EDE6; border-radius: 16px; padding: 32px; border: 1px solid #332B25;">
        <div style="text-align: center; margin-bottom: 24px;">
            <h1 style="color: #C4622D; margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -0.5px;">REUNITE TEAM</h1>
            <p style="color: #A89F91; font-size: 14px; margin-top: 4px;">Student Account Verification</p>
        </div>
        <div style="background: #25201C; border-radius: 12px; padding: 24px; text-align: center; border: 1px solid #3D342D;">
            <p style="margin: 0 0 16px; font-size: 15px; color: #E7DFD5;">Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>
            <p style="margin: 0 0 20px; font-size: 14px; color: #A89F91;">Use the 6-digit verification code below to confirm your email and create your Reunite account:</p>
            <div style="font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #C4622D; background: #141210; padding: 16px 24px; border-radius: 10px; display: inline-block; border: 1px dashed #C4622D; font-family: monospace;">' . $otp . '</div>
            <p style="margin: 20px 0 0; font-size: 12px; color: #7A7267;">This code is valid for <strong>10 minutes</strong>. Do not share it with anyone.</p>
        </div>
        <p style="font-size: 12px; color: #7A7267; text-align: center; margin-top: 24px;">&copy; ' . date('Y') . ' REUNITE TEAM &bull; Campus Lost & Found Community</p>
    </div>';

    $mailResult = sendBrevoEmail($email, $name, "Your Verification Code: $otp — REUNITE TEAM", $htmlEmail);

    if ($mailResult['success']) {
        $msg = "A 6-digit verification code has been sent to " . htmlspecialchars($email);
        $isSimulated = !empty($mailResult['simulated']);
        if ($isSimulated) {
            $msg .= " (Local Test Code: " . $otp . ")";
        }
        echo json_encode([
            'success' => true,
            'message' => $msg,
            'otp_sent' => true,
            'simulated' => $isSimulated,
            'dev_otp' => $isSimulated ? $otp : null
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send verification email. ' . ($mailResult['error'] ?? '')]);
    }
    exit;
}

// ── 2. VERIFY OTP ─────────────────────────────────────────
if ($action === 'verify_otp') {
    $email = trim($input['email'] ?? '');
    $code = trim($input['code'] ?? $input['otp'] ?? '');

    if (empty($email) || empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Email and 6-digit verification code are required.']);
        exit;
    }

    $result = verifyEmailOtp($email, $code);
    echo json_encode($result);
    exit;
}

// ── 3. SIGNUP (After Email Verification & Password Entry) ─
if ($action === 'signup') {
    $name = trim($input['name'] ?? $input['full_name'] ?? 'Student');
    $dob = trim($input['dob'] ?? $input['date_of_birth'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $pin = trim($input['pin'] ?? '');
    $college = trim($input['college'] ?? 'Sri Venkateswara Govt Polytechnic');
    $branch = trim($input['branch'] ?? 'cme');
    $password = $input['password'] ?? '';

    // Check if email was verified via OTP
    if (!isEmailOtpVerified($email)) {
        echo json_encode(['success' => false, 'message' => 'Please verify your email address with the 6-digit code first.']);
        exit;
    }

    if (empty($password) || strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
        exit;
    }

    if ($conn) {
        $encrypted_email = encryptData($email);
        $encrypted_phone = !empty($phone) ? encryptData($phone) : null;
        $pass_hash = password_hash($password, PASSWORD_DEFAULT);
        $trust_score = 100;
        $role = 'user';
        $status = 'active';

        $stmt = $conn->prepare("INSERT INTO users (full_name, pin, email, phone, password_hash, trust_score, role, status, dob, college, branch, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        if ($stmt) {
            $stmt->bind_param("sssssisssss", $name, $pin, $encrypted_email, $encrypted_phone, $pass_hash, $trust_score, $role, $status, $dob, $college, $branch);
            if ($stmt->execute()) {
                $uid = $stmt->insert_id;
                $stmt->close();
                login_user([
                    'user_id' => $uid,
                    'name' => $name,
                    'pin' => $pin,
                    'dob' => $dob,
                    'email' => $email,
                    'phone' => $phone,
                    'college' => $college,
                    'branch' => $branch,
                    'trust_score' => $trust_score
                ]);
                logAccess($uid, 'User Registered');
                echo json_encode(['success' => true, 'redirect' => 'home.php']);
                exit;
            } else {
                $err = $stmt->error;
                $stmt->close();
                echo json_encode(['success' => false, 'message' => 'Registration database error: ' . $err]);
                exit;
            }
        }
    }

    login_user([
        'name' => $name,
        'pin' => $pin,
        'dob' => $dob,
        'email' => $email,
        'phone' => $phone,
        'college' => $college,
        'branch' => $branch
    ]);

    echo json_encode(['success' => true, 'redirect' => 'home.php']);
    exit;
}

if ($action === 'update_profile') {
    if (!is_logged_in()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $college = trim($input['college'] ?? '');
    $branch = trim($input['branch'] ?? '');

    if (empty($name) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Name and Email are required.']);
        exit;
    }

    $user_id = $_SESSION['user_id'] ?? 1;
    if ($conn) {
        $encrypted_phone = !empty($phone) ? encryptData($phone) : null;
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, college = ?, branch = ? WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ssssi", $name, $encrypted_phone, $college, $branch, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    update_user_profile([
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'college' => $college,
        'branch' => $branch
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully!',
        'user' => get_current_user_data()
    ]);
    exit;
}

if ($action === 'change_password') {
    if (!is_logged_in()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $current_pass = trim($input['current_password'] ?? '');
    $new_pass = trim($input['new_password'] ?? '');

    if (empty($current_pass) || empty($new_pass)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all password fields.']);
        exit;
    }

    if (strlen($new_pass) < 8) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long.']);
        exit;
    }

    $user_id = $_SESSION['user_id'] ?? 1;
    if ($conn) {
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if (!password_verify($current_pass, $row['password_hash'])) {
                    $stmt->close();
                    echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
                    exit;
                }
            }
            $stmt->close();

            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $up_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            if ($up_stmt) {
                $up_stmt->bind_param("si", $new_hash, $user_id);
                $up_stmt->execute();
                $up_stmt->close();
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully!'
    ]);
    exit;
}

// ── 4. FORGOT PASSWORD: SEND RESET LINK VIA BREVO API ────
if ($action === 'send_reset_link') {
    $identifier = trim($input['identifier'] ?? $input['email'] ?? $input['pin'] ?? '');

    if (empty($identifier)) {
        echo json_encode(['success' => false, 'message' => 'Please enter your registered College PIN or Email address.']);
        exit;
    }

    $foundUser = null;
    if ($conn) {
        $encrypted_id = encryptData($identifier);
        $stmt = $conn->prepare("SELECT user_id, full_name, pin, email FROM users WHERE pin = ? OR email = ? OR email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("sss", $identifier, $encrypted_id, $identifier);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $foundUser = $row;
            }
            $stmt->close();
        }
    }

    // If user not found in DB, return generic friendly message for privacy or prompt
    if (!$foundUser) {
        echo json_encode(['success' => false, 'message' => 'No student account was found with this College PIN or Email.']);
        exit;
    }

    $userId = $foundUser['user_id'];
    $rawEmail = decryptData($foundUser['email']);
    $studentName = $foundUser['full_name'] ?: 'Student';

    // Generate 64-character token with 10-minute validity
    $token = createPasswordResetToken($userId, $rawEmail);

    // Build absolute URL for reset password page
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['PHP_SELF'] ?? '/Frontend/api');
    $baseFrontendDir = dirname($scriptDir); // gets /Frontend or /pw/reunitel/Frontend
    $resetUrl = rtrim($protocol . $host . $baseFrontendDir, '/') . '/reset-password.php?token=' . urlencode($token);

    // Branded HTML Email Template with prominent Reset Button
    $htmlEmail = '
    <div style="max-width: 540px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #1C1917; color: #F5EDE6; border-radius: 16px; padding: 32px; border: 1px solid #332B25;">
        <div style="text-align: center; margin-bottom: 24px;">
            <h1 style="color: #C4622D; margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -0.5px;">REUNITE TEAM</h1>
            <p style="color: #A89F91; font-size: 14px; margin-top: 4px;">Password Reset Request</p>
        </div>
        <div style="background: #25201C; border-radius: 12px; padding: 24px; text-align: center; border: 1px solid #3D342D;">
            <p style="margin: 0 0 16px; font-size: 16px; color: #E7DFD5;">Hello <strong>' . htmlspecialchars($studentName) . '</strong>,</p>
            <p style="margin: 0 0 24px; font-size: 14px; color: #A89F91; line-height: 1.5;">We received a request to reset the password for your Reunite account. Click the button below to create a new password:</p>
            <div style="margin: 28px 0;">
                <a href="' . htmlspecialchars($resetUrl) . '" style="background: #C4622D; color: #FFFFFF; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px; display: inline-block; letter-spacing: 0.3px; box-shadow: 0 4px 12px rgba(196, 98, 45, 0.35);">Reset My Password &rarr;</a>
            </div>
            <p style="margin: 20px 0 0; font-size: 12px; color: #7A7267;">This reset link is valid for <strong>10 minutes</strong>. If you did not request this, you can safely ignore this email.</p>
        </div>
        <div style="margin-top: 20px; font-size: 12px; color: #7A7267; word-break: break-all; text-align: center;">
            <p style="margin: 0 0 6px;">Button not working? Copy and paste this link into your browser:</p>
            <a href="' . htmlspecialchars($resetUrl) . '" style="color: #C4622D; text-decoration: underline;">' . htmlspecialchars($resetUrl) . '</a>
        </div>
        <p style="font-size: 11px; color: #575047; text-align: center; margin-top: 24px;">&copy; ' . date('Y') . ' REUNITE TEAM &bull; Campus Lost & Found Community</p>
    </div>';

    $mailResult = sendBrevoEmail($rawEmail, $studentName, "Reset Your Reunite Password", $htmlEmail);

    if ($mailResult['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'A password reset link has been sent to your registered email address (' . htmlspecialchars(substr($rawEmail, 0, 3) . '***@' . explode('@', $rawEmail)[1]) . '). Please check your inbox.',
            'simulated' => !empty($mailResult['simulated']),
            'dev_reset_url' => !empty($mailResult['simulated']) ? $resetUrl : null
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send password reset email. ' . ($mailResult['error'] ?? '')
        ]);
    }
    exit;
}

// ── 5. VALIDATE RESET TOKEN ──────────────────────────────
if ($action === 'validate_reset_token') {
    $token = trim($input['token'] ?? $_GET['token'] ?? '');
    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Reset token is missing.']);
        exit;
    }

    $tokenData = verifyPasswordResetToken($token);
    if ($tokenData) {
        echo json_encode([
            'success' => true,
            'user' => [
                'name' => $tokenData['full_name'] ?? 'Student',
                'pin' => $tokenData['pin'] ?? '',
                'email' => decryptData($tokenData['email'] ?? '')
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'This password reset link is invalid or has expired (10-minute validity exceeded). Please request a new link.'
        ]);
    }
    exit;
}

// ── 6. PERFORM PASSWORD RESET (After User Submits New Pass)
if ($action === 'perform_reset_password') {
    $token = trim($input['token'] ?? '');
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Reset token is missing.']);
        exit;
    }

    if (empty($newPassword) || strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    $result = consumePasswordResetToken($token, $newPassword);
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request action.']);
exit;
