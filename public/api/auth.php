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

// Hardcoded admin credentials for initial access
const ADMIN_EMAIL = 'admin@muffinshop.com';
const ADMIN_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // 'admin123'

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
                
                // Prevent using admin email for registration
                if ($data['email'] === ADMIN_EMAIL) {
                    http_response_code(400);
                    echo json_encode(['error' => 'This email is reserved for administration']);
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
                
                // Create user (always as customer)
                $query = "INSERT INTO users SET name=:name, email=:email, password_hash=:password_hash, role='customer'";
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
                
                // Check for hardcoded admin first
                if ($data['email'] === ADMIN_EMAIL) {
                    if (password_verify($data['password'], ADMIN_PASSWORD_HASH)) {
                        // Check if admin exists in database, if not create it
                        $check_admin = "SELECT id, name, email, role FROM users WHERE email = :email AND role = 'admin'";
                        $admin_stmt = $db->prepare($check_admin);
                        $admin_stmt->bindParam(':email', ADMIN_EMAIL);
                        $admin_stmt->execute();
                        
                        if ($admin_stmt->rowCount() > 0) {
                            $admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);
                            $_SESSION['user_id'] = $admin['id'];
                            $_SESSION['user_email'] = $admin['email'];
                            $_SESSION['user_name'] = $admin['name'];
                            $_SESSION['user_role'] = 'admin';
                        } else {
                            // Create admin user in database
                            $create_admin = "INSERT INTO users SET name='Administrator', email=:email, password_hash=:password_hash, role='admin'";
                            $create_stmt = $db->prepare($create_admin);
                            $create_stmt->bindParam(':email', ADMIN_EMAIL);
                            $create_stmt->bindParam(':password_hash', ADMIN_PASSWORD_HASH);
                            $create_stmt->execute();
                            
                            $_SESSION['user_id'] = $db->lastInsertId();
                            $_SESSION['user_email'] = ADMIN_EMAIL;
                            $_SESSION['user_name'] = 'Administrator';
                            $_SESSION['user_role'] = 'admin';
                        }
                        
                        echo json_encode([
                            'message' => 'Admin login successful',
                            'user' => [
                                'id' => $_SESSION['user_id'],
                                'name' => $_SESSION['user_name'],
                                'email' => $_SESSION['user_email'],
                                'role' => 'admin'
                            ]
                        ]);
                    } else {
                        http_response_code(401);
                        echo json_encode(['error' => 'Invalid admin credentials']);
                    }
                    break;
                }
                
                // Regular user login
                $query = "SELECT id, name, email, password_hash, role FROM users WHERE email = :email AND role = 'customer'";
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