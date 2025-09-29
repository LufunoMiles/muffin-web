<?php
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    return strlen($password) >= 6;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (isset($data['action'])) {
        switch($data['action']) {
            case 'register':
                // Validate input
                if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing required fields']);
                    exit;
                }
                
                if (!validateEmail($data['email'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid email format']);
                    exit;
                }
                
                if (!validatePassword($data['password'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Password must be at least 6 characters']);
                    exit;
                }
                
                // Check if user exists
                $check_query = "SELECT id FROM users WHERE email = :email";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':email', $data['email']);
                $check_stmt->execute();
                
                if ($check_stmt->rowCount() > 0) {
                    http_response_code(409);
                    echo json_encode(['error' => 'User already exists']);
                    exit;
                }
                
                // Create user
                $query = "INSERT INTO users SET name=:name, email=:email, password_hash=:password_hash";
                $stmt = $db->prepare($query);
                
                $name = htmlspecialchars(strip_tags($data['name']));
                $email = htmlspecialchars(strip_tags($data['email']));
                $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
                
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password_hash', $password_hash);
                
                if ($stmt->execute()) {
                    $_SESSION['user_id'] = $db->lastInsertId();
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_role'] = 'customer';
                    
                    echo json_encode([
                        'message' => 'User created successfully',
                        'user' => [
                            'id' => $_SESSION['user_id'],
                            'name' => $name,
                            'email' => $email,
                            'role' => 'customer'
                        ]
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Unable to create user']);
                }
                break;
                
            case 'login':
                if (!isset($data['email']) || !isset($data['password'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing email or password']);
                    exit;
                }
                
                $query = "SELECT id, name, email, password_hash, role FROM users WHERE email = :email";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':email', $data['email']);
                $stmt->execute();
                
                if ($stmt->rowCount() == 1) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (password_verify($data['password'], $row['password_hash'])) {
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['user_email'] = $row['email'];
                        $_SESSION['user_name'] = $row['name'];
                        $_SESSION['user_role'] = $row['role'];
                        
                        echo json_encode([
                            'message' => 'Login successful',
                            'user' => [
                                'id' => $row['id'],
                                'name' => $row['name'],
                                'email' => $row['email'],
                                'role' => $row['role']
                            ]
                        ]);
                    } else {
                        http_response_code(401);
                        echo json_encode(['error' => 'Invalid credentials']);
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(['error' => 'User not found']);
                }
                break;
                
            case 'logout':
                session_destroy();
                echo json_encode(['message' => 'Logged out successfully']);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check') {
    // Check if user is logged in
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'loggedIn' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'role' => $_SESSION['user_role']
            ]
        ]);
    } else {
        echo json_encode(['loggedIn' => false]);
    }
}
?>