<?php
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = "SELECT id, name, description, price, stock, image_url FROM products ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $products = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $products[] = $row;
    }
    
    echo json_encode($products);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>