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

echo json_encode(['success' => false, 'message' => 'Invalid request action.']);
exit;
