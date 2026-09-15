<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';

if ($action === 'login') {
    $identifier = trim($input['identifier'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    // Dummy Credential Check for Testing
    // Accepts PIN: 24155-cm-002, Email: alex@college.edu, or Phone: 9876543210 with Password: password123
    // Or any valid formatted PIN/Email/Phone with password length >= 8
    $validPass = ($password === 'password123' || strlen($password) >= 8);

    if ($validPass) {
        $name = 'Alex Johnson';
        if (strpos($identifier, '@') !== false) {
            $parts = explode('@', $identifier);
            $name = ucfirst($parts[0]);
        }

        login_user([
            'name' => $name,
            'pin' => $identifier,
            'email' => strpos($identifier, '@') !== false ? $identifier : 'alex.johnson@college.edu',
            'college' => 'Sri Venkateswara Govt Polytechnic',
            'branch' => 'Computer Science & Engineering'
        ]);

        echo json_encode(['success' => true, 'redirect' => 'home.php']);
        exit;
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid credentials! Try using PIN: 24155-cm-002 or Email: alex@college.edu with Password: password123'
        ]);
        exit;
    }
}

if ($action === 'signup') {
    $name = trim($input['name'] ?? 'Student');
    $email = trim($input['email'] ?? '');
    $pin = trim($input['pin'] ?? '');
    $college = trim($input['college'] ?? '');
    $branch = trim($input['branch'] ?? '');

    login_user([
        'name' => $name,
        'pin' => $pin,
        'email' => $email,
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

    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully!'
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request action.']);
exit;
