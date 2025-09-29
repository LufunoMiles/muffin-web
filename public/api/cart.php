<?php
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    
    if (isset($data['action'])) {
        switch($data['action']) {
            case 'add':
                if (!isset($data['product_id']) || !isset($data['quantity'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing product_id or quantity']);
                    exit;
                }
                
                // Get product details
                $product_query = "SELECT price, stock FROM products WHERE id = :id";
                $product_stmt = $db->prepare($product_query);
                $product_stmt->bindParam(':id', $data['product_id']);
                $product_stmt->execute();
                
                if ($product_stmt->rowCount() == 0) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Product not found']);
                    exit;
                }
                
                $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product['stock'] < $data['quantity']) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Insufficient stock']);
                    exit;
                }
                
                // Initialize cart if not exists
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                
                // Add item to cart
                $product_id = $data['product_id'];
                if (isset($_SESSION['cart'][$product_id])) {
                    $_SESSION['cart'][$product_id]['quantity'] += $data['quantity'];
                } else {
                    $_SESSION['cart'][$product_id] = [
                        'quantity' => $data['quantity'],
                        'price' => $product['price']
                    ];
                }
                
                echo json_encode([
                    'message' => 'Item added to cart',
                    'cart' => $_SESSION['cart']
                ]);
                break;
                
            case 'get':
                $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
                $cart_details = [];
                $total = 0;
                
                foreach ($cart as $product_id => $item) {
                    $product_query = "SELECT name, image_url FROM products WHERE id = :id";
                    $product_stmt = $db->prepare($product_query);
                    $product_stmt->bindParam(':id', $product_id);
                    $product_stmt->execute();
                    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $item_total = $item['quantity'] * $item['price'];
                    $total += $item_total;
                    
                    $cart_details[] = [
                        'product_id' => $product_id,
                        'name' => $product['name'],
                        'image_url' => $product['image_url'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'item_total' => $item_total
                    ];
                }
                
                echo json_encode([
                    'items' => $cart_details,
                    'total' => $total
                ]);
                break;
                
            case 'clear':
                $_SESSION['cart'] = [];
                echo json_encode(['message' => 'Cart cleared']);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>