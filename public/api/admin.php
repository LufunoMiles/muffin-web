<?php
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_product') {
        // Handle product creation with image upload
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $price = $_POST['price'] ?? '';
        $stock = $_POST['stock'] ?? '';
        
        if (empty($name) || empty($price) || empty($stock)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        $image_url = '';
        
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['image']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.']);
                exit;
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
                $image_url = '/uploads/' . $filename;
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to upload image']);
                exit;
            }
        }
        
        // Insert product
        $query = "INSERT INTO products SET name=:name, description=:description, price=:price, stock=:stock, image_url=:image_url";
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':image_url', $image_url);
        
        if ($stmt->execute()) {
            echo json_encode([
                'message' => 'Product added successfully',
                'product_id' => $db->lastInsertId()
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add product']);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all orders for admin
    $query = "SELECT o.*, u.name as user_name, u.email 
              FROM orders o 
              JOIN users u ON o.user_id = u.id 
              ORDER BY o.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $orders = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Get order items
        $items_query = "SELECT oi.*, p.name, p.image_url 
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.id 
                       WHERE oi.order_id = :order_id";
        $items_stmt = $db->prepare($items_query);
        $items_stmt->bindParam(':order_id', $row['id']);
        $items_stmt->execute();
        
        $row['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        $orders[] = $row;
    }
    
    echo json_encode($orders);
}
?>