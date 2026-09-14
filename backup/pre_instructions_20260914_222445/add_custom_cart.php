<?php
session_start();
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Gagawa tayo ng unique ID para sa custom item para hindi mag-conflict sa regular products
    $cart_id = 'custom_' . time() . '_' . rand(1000, 9999);
    
    $_SESSION['cart'][$cart_id] = [
        'is_custom' => true,
        'order_type' => $data['order_type'],
        'size' => $data['size'],
        'image' => $data['custom_image'],
        'items_json' => $data['items_json'],
        'qty' => 1,
        'price' => 0, // 0 muna kasi hihingi pa tayo ng quotation kay seller
        'shirt_color' => isset($data['shirt_color']) ? $data['shirt_color'] : null
    ];
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No data received']);
}
?>