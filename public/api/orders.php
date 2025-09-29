<?php
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create order
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Cart is empty']);
        exit;
    }
    
    try {
        $db->beginTransaction();
        
        // Calculate total
        $total = 0;
        foreach ($_SESSION['cart'] as $product_id => $item) {
            $total += $item['quantity'] * $item['price'];
        }
        
        // Create order
        $order_query = "INSERT INTO orders SET user_id=:user_id, total_price=:total_price";
        $order_stmt = $db->prepare($order_query);
        $order_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $order_stmt->bindParam(':total_price', $total);
        $order_stmt->execute();
        
        $order_id = $db->lastInsertId();
        
        // Create order items and update stock
        foreach ($_SESSION['cart'] as $product_id => $item) {
            // Add order item
            $item_query = "INSERT INTO order_items SET order_id=:order_id, product_id=:product_id, quantity=:quantity, price=:price";
            $item_stmt = $db->prepare($item_query);
            $item_stmt->bindParam(':order_id', $order_id);
            $item_stmt->bindParam(':product_id', $product_id);
            $item_stmt->bindParam(':quantity', $item['quantity']);
            $item_stmt->bindParam(':price', $item['price']);
            $item_stmt->execute();
            
            // Update product stock
            $update_query = "UPDATE products SET stock = stock - :quantity WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':quantity', $item['quantity']);
            $update_stmt->bindParam(':id', $product_id);
            $update_stmt->execute();
        }
        
        $db->commit();
        
        // Clear cart
        $_SESSION['cart'] = [];
        
        echo json_encode([
            'message' => 'Order created successfully',
            'order_id' => $order_id
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create order: ' . $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get user orders
    $query = "SELECT o.*, 
                     (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
              FROM orders o 
              WHERE o.user_id = :user_id 
              ORDER BY o.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
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