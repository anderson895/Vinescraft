<?php
session_start();
require_once 'custom_edit.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Gagawa tayo ng unique ID para sa custom item para hindi mag-conflict sa regular products
    $cart_id = 'custom_' . time() . '_' . rand(1000, 9999);
    $replaced = false;

    // RE-EDIT: kapag galing sa "Edit Design", gamitin ang dating key para mapalitan
    // ang item sa mismong pwesto nito imbes na magdagdag ng bagong entry.
    $edit_id = $data['edit_id'] ?? null;
    if (is_string($edit_id) && strpos($edit_id, 'custom_') === 0 && isset($_SESSION['cart'][$edit_id])) {
        $cart_id = $edit_id;
        $replaced = true;
        // Linisin ang lumang preview - papalitan na ito ng bagong render.
        delete_design_images($_SESSION['cart'][$edit_id]['image'] ?? '');
    }

    // Sa disk isinusulat ang preview imbes na base64 - hindi kasya ang apat na
    // snapshot ng t-shirt sa 1 MB na max_allowed_packet ng MySQL.
    $image = persist_design_image($data['custom_image']);

    $_SESSION['cart'][$cart_id] = [
        'is_custom' => true,
        'order_type' => $data['order_type'],
        'size' => $data['size'],
        'image' => $image,
        'items_json' => $data['items_json'],
        'qty' => 1,
        'price' => 0, // 0 muna kasi hihingi pa tayo ng quotation kay seller
        'shirt_color' => isset($data['shirt_color']) ? $data['shirt_color'] : null
    ];

    echo json_encode(['success' => true, 'cart_id' => $cart_id, 'replaced' => $replaced]);
} else {
    echo json_encode(['success' => false, 'message' => 'No data received']);
}
?>
