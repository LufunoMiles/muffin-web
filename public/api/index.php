<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

// CORS headers
header('Access-Control-Allow-Origin: http://localhost:8080');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

// Simple routing
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Route the request
if (preg_match('#/api/([a-z]+)#', $path, $matches)) {
    $endpoint = $matches[1];
    
    switch($endpoint) {
        case 'auth':
            require 'auth.php';
            break;
        case 'products':
            require 'products.php';
            break;
        case 'cart':
            require 'cart.php';
            break;
        case 'orders':
            require 'orders.php';
            break;
        case 'admin':
            require 'admin.php';
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid API route']);
}
?>