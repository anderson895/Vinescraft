<?php
session_start();
require_once 'db_connect.php';

// 1. Protection Check
if (!isset($_SESSION['user_logged_in'])) {
    echo "<script>alert('Please login first to add items to your cart.'); window.location.href='login.php';</script>";
    exit();
}

if (isset($_POST['add_to_cart'])) {
    $p_id = intval($_POST['p_id']);
    $p_name = $_POST['p_name'];
    $p_price = floatval($_POST['p_price']);
    $p_qty = intval($_POST['qty']);
    $p_image = $_POST['p_image'];

    // 2. Initialize Cart if empty
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // 3. Logic: Kung nasa cart na yung item, dagdagan na lang ang quantity
    if (isset($_SESSION['cart'][$p_id])) {
        $_SESSION['cart'][$p_id]['qty'] += $p_qty;
    } else {
        // Kung bago, i-add bilang bagong entry
        $_SESSION['cart'][$p_id] = [
            'name' => $p_name,
            'price' => $p_price,
            'qty' => $p_qty,
            'image' => $p_image
        ];
    }

    echo "<script>alert('Added to cart successfully!'); window.location.href='shop.php';</script>";
    exit();
} else {
    // Pag may nag-trip na i-access 'to nang diretso
    header("Location: shop.php");
    exit();
}
?>