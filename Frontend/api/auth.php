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
    $role = strtolower(trim($input['role'] ?? 'student')); // 'student' or 'campus_admin'

    if (empty($identifier) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    if (function_exists('checkRateLimit')) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $rateKey = 'auth_login_' . md5($identifier . '_' . $ip);
        if (!checkRateLimit($rateKey, 10, 300)) {
            echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please wait 5 minutes before trying again.']);
            exit;
        }
    }

    $defaultAdminUser = function_exists('get_config_val') ? get_config_val('ADMIN_EMAIL', 'admin') : 'admin';
    $defaultAdminPass = function_exists('get_config_val') ? get_config_val('ADMIN_PASSWORD', 'pass') : 'pass';

    // ─────────────────────────────────────────────────────────────
    // 1. CAMPUS ADMIN EXPLICIT LOGIN
    // ─────────────────────────────────────────────────────────────
    if ($role === 'campus_admin') {
        if ($conn) {
            ensureAdminsTable();
            $stmt = $conn->prepare("SELECT admin_id, username, full_name, email, password_hash, role, status, campus_id FROM admins WHERE username = ? OR email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("ss", $identifier, $identifier);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows === 1) {
                    $admin = $res->fetch_assoc();
                    $stmt->close();

                    if (isset($admin['status']) && strtolower($admin['status']) === 'suspended') {
                        echo json_encode(['success' => false, 'message' => 'This administrator account is currently suspended. Please contact master admin.']);
                        exit;
                    }

                    $isMasterPass = ($password === $defaultAdminPass && in_array(strtolower($identifier), ['admin', 'admin@reunite.site']));

                    if (password_verify($password, $admin['password_hash']) || $isMasterPass) {
                        @$conn->query("UPDATE admins SET last_login = NOW() WHERE admin_id = " . (int)$admin['admin_id']);
                        login_admin($admin);
                        if (function_exists('logAccess')) {
                            logAccess($admin['admin_id'], 'Campus Admin Portal Login');
                        }
                        echo json_encode([
                            'success'  => true,
                            'redirect' => 'admin/index.php',
                            'role'     => $admin['role'] ?? 'campus_admin',
                            'message'  => 'Welcome back, ' . ($admin['full_name'] ?? 'Administrator') . '!'
                        ]);
                        exit;
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Incorrect password for administrator account.']);
                        exit;
                    }
                } else {
                    $stmt->close();
                }
            }
        }

        // Check fallback master admin credentials
        if ((strtolower($identifier) === 'admin' || strtolower($identifier) === 'admin@reunite.site') && $password === $defaultAdminPass) {
            login_admin([
                'admin_id'  => 1,
                'full_name' => 'System Administrator',
                'username'  => 'admin',
                'email'     => 'admin@reunite.site',
                'role'      => 'master_admin',
                'campus_id' => null
            ]);
            echo json_encode([
                'success'  => true,
                'redirect' => 'admin/index.php',
                'role'     => 'master_admin',
                'message'  => 'Welcome back, Master Administrator!'
            ]);
            exit;
        }

        // Smart check: Did a student try logging in on the campus admin tab?
        if ($conn) {
            $encId = encryptData($identifier);
            $checkUser = $conn->prepare("SELECT user_id, password_hash FROM users WHERE pin = ? OR email = ? OR email = ? OR phone = ? LIMIT 1");
            if ($checkUser) {
                $checkUser->bind_param("ssss", $identifier, $encId, $identifier, $encId);
                $checkUser->execute();
                $uRes = $checkUser->get_result();
                if ($uRes && $uRes->num_rows === 1) {
                    $uRow = $uRes->fetch_assoc();
                    $checkUser->close();
                    if (password_verify($password, $uRow['password_hash'])) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'These credentials belong to a Student account. Please switch to the "Student" tab to sign in.'
                        ]);
                        exit;
                    }
                } else {
                    $checkUser->close();
                }
            }
        }

        echo json_encode(['success' => false, 'message' => 'Invalid administrator username/email or password.']);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 2. STUDENT LOGIN (WITH SMART ADMIN FALLBACK)
    // ─────────────────────────────────────────────────────────────
    if ($conn) {
        $encrypted_id = encryptData($identifier);
        $sql = "SELECT user_id, full_name, pin, email, phone, password_hash, trust_score, role, status, college, branch, campus_id FROM users WHERE pin = ? OR email = ? OR email = ? OR phone = ? OR phone = ? LIMIT 1";
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
                        'campus_id' => $user['campus_id'] ?? null,
                        'trust_score' => $user['trust_score'],
                        'role' => $user['role']
                    ]);
                    if (function_exists('logAccess')) {
                        logAccess($user['user_id'], 'Student Login');
                    }
                    echo json_encode(['success' => true, 'redirect' => 'home.php', 'role' => 'student', 'user' => get_current_user_data()]);
                    exit;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid College PIN/Email or password. Please try again.']);
                    exit;
                }
            } else {
                $stmt->close();
            }
        }

        // Smart check: Did a campus admin try logging in on the student tab?
        ensureAdminsTable();
        $adminStmt = $conn->prepare("SELECT admin_id, username, full_name, email, password_hash, role, status, campus_id FROM admins WHERE username = ? OR email = ? LIMIT 1");
        if ($adminStmt) {
            $adminStmt->bind_param("ss", $identifier, $identifier);
            $adminStmt->execute();
            $aRes = $adminStmt->get_result();
            if ($aRes && $aRes->num_rows === 1) {
                $admin = $aRes->fetch_assoc();
                $adminStmt->close();

                if (isset($admin['status']) && strtolower($admin['status']) === 'suspended') {
                    echo json_encode(['success' => false, 'message' => 'This administrator account is suspended.']);
                    exit;
                }

                $isMasterPass = ($password === $defaultAdminPass && in_array(strtolower($identifier), ['admin', 'admin@reunite.site']));

                if (password_verify($password, $admin['password_hash']) || $isMasterPass) {
                    @$conn->query("UPDATE admins SET last_login = NOW() WHERE admin_id = " . (int)$admin['admin_id']);
                    login_admin($admin);
                    if (function_exists('logAccess')) {
                        logAccess($admin['admin_id'], 'Campus Admin Login (from student page)');
                    }
                    echo json_encode([
                        'success'  => true,
                        'redirect' => 'admin/index.php',
                        'role'     => $admin['role'] ?? 'campus_admin',
                        'message'  => 'Campus Admin credentials verified. Redirecting to Admin Portal...'
                    ]);
                    exit;
                }
            } else {
                $adminStmt->close();
            }
        }
    }

    // Fallback master admin on student tab
    if ((strtolower($identifier) === 'admin' || strtolower($identifier) === 'admin@reunite.site') && $password === $defaultAdminPass) {
        login_admin([
            'admin_id'  => 1,
            'full_name' => 'System Administrator',
            'username'  => 'admin',
            'email'     => 'admin@reunite.site',
            'role'      => 'master_admin',
            'campus_id' => null
        ]);
        echo json_encode([
            'success'  => true,
            'redirect' => 'admin/index.php',
            'role'     => 'master_admin',
            'message'  => 'Welcome back, Master Administrator!'
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid College PIN/Email or password. Please try again.']);
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

    // Professional, clean, modern OTP verification email
    $htmlEmail = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Your Verification Code</title>
    </head>
    <body style="margin: 0; padding: 0; background-color: #F8FAFC; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; color: #1E293B;">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #F8FAFC; padding: 40px 16px;">
        <tr>
          <td align="center">
            <table role="presentation" width="100%" style="max-width: 520px; background-color: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);" cellspacing="0" cellpadding="0">
              <tr>
                <td style="padding: 28px 36px 20px 36px; border-bottom: 1px solid #F1F5F9;">
                  <span style="font-size: 19px; font-weight: 700; color: #C4622D; letter-spacing: -0.3px;">Reunite</span>
                  <span style="display: inline-block; margin-left: 8px; font-size: 12px; color: #64748B; font-weight: 500;">Campus Lost &amp; Found</span>
                </td>
              </tr>
              <tr>
                <td style="padding: 32px 36px;">
                  <h1 style="margin: 0 0 16px 0; font-size: 20px; font-weight: 600; color: #0F172A; line-height: 1.35;">Verify your email address</h1>
                  <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 1.6; color: #334155;">Hello ' . htmlspecialchars($name) . ',</p>
                  <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.6; color: #334155;">Use the 6-digit verification code below to confirm your email and complete your Reunite account registration:</p>
                  
                  <div style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 18px 24px; text-align: center; margin: 24px 0;">
                    <span style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #0F172A;">' . $otp . '</span>
                  </div>

                  <p style="margin: 0 0 8px 0; font-size: 13px; line-height: 1.6; color: #64748B;">This code is valid for <strong>10 minutes</strong>. For your security, do not share this code with anyone.</p>
                </td>
              </tr>
              <tr>
                <td style="padding: 20px 36px 24px 36px; background-color: #F8FAFC; border-top: 1px solid #F1F5F9;">
                  <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #94A3B8; text-align: center;">
                    This is an automated security message from Reunite &bull; Campus Lost &amp; Found Platform.<br>
                    Please do not reply directly to this email.
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </body>
    </html>';

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
    $campus_id = !empty($input['campus_id']) ? (int)$input['campus_id'] : null;
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
        // Resolve campus_id from campuses table if not provided
        if (!$campus_id && !empty($college)) {
            $cStmt = $conn->prepare("SELECT id FROM campuses WHERE name = ? OR code = ? LIMIT 1");
            if ($cStmt) {
                $cStmt->bind_param("ss", $college, $college);
                $cStmt->execute();
                $cRes = $cStmt->get_result();
                if ($cRes && $cRow = $cRes->fetch_assoc()) {
                    $campus_id = (int)$cRow['id'];
                }
                $cStmt->close();
            }
        }

        $encrypted_email = encryptData($email);
        $encrypted_phone = !empty($phone) ? encryptData($phone) : null;
        $pass_hash = password_hash($password, PASSWORD_DEFAULT);
        $trust_score = 100;
        $role = 'user';
        $status = 'active';

        $stmt = $conn->prepare("INSERT INTO users (full_name, pin, email, phone, password_hash, trust_score, role, status, dob, college, branch, email_verified, campus_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
        if ($stmt) {
            $stmt->bind_param("sssssisssssi", $name, $pin, $encrypted_email, $encrypted_phone, $pass_hash, $trust_score, $role, $status, $dob, $college, $branch, $campus_id);
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
                    'campus_id' => $campus_id,
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

        $campus_id = null;
        if (!empty($college)) {
            $cStmt = $conn->prepare("SELECT id FROM campuses WHERE name = ? OR code = ? LIMIT 1");
            if ($cStmt) {
                $cStmt->bind_param("ss", $college, $college);
                $cStmt->execute();
                $cRes = $cStmt->get_result();
                if ($cRes && $cRow = $cRes->fetch_assoc()) {
                    $campus_id = (int)$cRow['id'];
                }
                $cStmt->close();
            }
        }

        if ($campus_id) {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, college = ?, branch = ?, campus_id = ? WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param("ssssii", $name, $encrypted_phone, $college, $branch, $campus_id, $user_id);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, college = ?, branch = ? WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param("ssssi", $name, $encrypted_phone, $college, $branch, $user_id);
                $stmt->execute();
                $stmt->close();
            }
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

    if (function_exists('checkRateLimit')) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $rateKey = 'pw_reset_' . md5($identifier . '_' . $ip);
        if (!checkRateLimit($rateKey, 3, 600)) {
            echo json_encode(['success' => false, 'message' => 'Too many reset requests. Please wait 10 minutes before requesting another link.']);
            exit;
        }
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

    // If user not found in DB, return generic friendly message to prevent enumeration
    if (!$foundUser) {
        echo json_encode([
            'success' => true,
            'message' => 'If an account exists with this College PIN or Email, a password reset link has been dispatched.'
        ]);
        exit;
    }

    $userId = $foundUser['user_id'];
    $rawEmail = decryptData($foundUser['email']);
    $studentName = $foundUser['full_name'] ?: 'Student';

    // Generate 64-character token with 10-minute validity
    $token = createPasswordResetToken($userId, $rawEmail);

    // Build absolute URL for reset password page
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'reunite-app.rf.gd';
    $scriptDir = dirname($_SERVER['PHP_SELF'] ?? '/Frontend/api');
    $baseFrontendDir = dirname($scriptDir); // gets /Frontend or /pw/reunitel/Frontend
    $resetUrl = rtrim($protocol . $host . $baseFrontendDir, '/') . '/reset-password.php?token=' . urlencode($token);

    // Professional, clean, modern Password Reset HTML Email Template
    $htmlEmail = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Reset Your Password</title>
    </head>
    <body style="margin: 0; padding: 0; background-color: #F8FAFC; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; color: #1E293B;">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #F8FAFC; padding: 40px 16px;">
        <tr>
          <td align="center">
            <table role="presentation" width="100%" style="max-width: 520px; background-color: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);" cellspacing="0" cellpadding="0">
              <tr>
                <td style="padding: 28px 36px 20px 36px; border-bottom: 1px solid #F1F5F9;">
                  <span style="font-size: 19px; font-weight: 700; color: #C4622D; letter-spacing: -0.3px;">Reunite</span>
                  <span style="display: inline-block; margin-left: 8px; font-size: 12px; color: #64748B; font-weight: 500;">Campus Lost &amp; Found</span>
                </td>
              </tr>
              <tr>
                <td style="padding: 32px 36px;">
                  <h1 style="margin: 0 0 16px 0; font-size: 20px; font-weight: 600; color: #0F172A; line-height: 1.35;">Password reset request</h1>
                  <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 1.6; color: #334155;">Hello ' . htmlspecialchars($studentName) . ',</p>
                  <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.6; color: #334155;">We received a request to reset the password for your Reunite account. Click the button below to choose a new password:</p>
                  
                  <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 28px 0;">
                    <tr>
                      <td align="left" style="border-radius: 6px; background-color: #C4622D;">
                        <a href="' . htmlspecialchars($resetUrl) . '" target="_blank" style="display: inline-block; padding: 13px 28px; font-size: 14px; font-weight: 600; color: #FFFFFF; text-decoration: none; border-radius: 6px; border: 1px solid #C4622D;">Reset Password</a>
                      </td>
                    </tr>
                  </table>

                  <p style="margin: 0 0 16px 0; font-size: 13px; line-height: 1.6; color: #64748B;">This link is valid for <strong>10 minutes</strong>. If you did not request a password reset, no action is needed and your account remains secure.</p>

                  <hr style="border: none; border-top: 1px solid #F1F5F9; margin: 28px 0 20px 0;">

                  <p style="margin: 0 0 6px 0; font-size: 12px; color: #94A3B8;">If the button above does not work, copy and paste this URL into your browser:</p>
                  <p style="margin: 0; font-size: 12px; line-height: 1.5; word-break: break-all;">
                    <a href="' . htmlspecialchars($resetUrl) . '" style="color: #C4622D; text-decoration: underline;">' . htmlspecialchars($resetUrl) . '</a>
                  </p>
                </td>
              </tr>
              <tr>
                <td style="padding: 20px 36px 24px 36px; background-color: #F8FAFC; border-top: 1px solid #F1F5F9;">
                  <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #94A3B8; text-align: center;">
                    This is an automated security message from Reunite &bull; Campus Lost &amp; Found Platform.<br>
                    Please do not reply directly to this email.
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </body>
    </html>';

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
