<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['temp_order'])) {
    header("Location: index.php");
    exit();
}

$u_id = $_SESSION['user_id'];
$temp = $_SESSION['temp_order'];
$total = $_POST['final_total'];
$dp = $_POST['final_dp'];
$rem = $_POST['final_rem'];
$sf = $_POST['final_sf'];

$address = $conn->real_escape_string($temp['delivery_address']);
$method = $temp['shipping_method'];
$msg = $conn->real_escape_string($temp['seller_message']);

// 1. Save to orders
$sql = "INSERT INTO orders (user_id, total_amount, shipping_method, shipping_fee, downpayment_amount, remaining_balance, delivery_address, seller_message, payment_status) 
        VALUES ($u_id, $total, '$method', $sf, $dp, $rem, '$address', '$msg', 'Pending')";

if ($conn->query($sql) === TRUE) {
    $order_id = $conn->insert_id;

    // 2. Save to order_details
    foreach ($_SESSION['cart'] as $id => $qty) {
        $res = $conn->query("SELECT price FROM products WHERE product_id = $id");
        $row = $res->fetch_assoc();
        $p = $row['price'];
        $conn->query("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES ($order_id, $id, $qty, $p)");
    }

    unset($_SESSION['cart']);
    unset($_SESSION['temp_order']);
    echo "<script>alert('Payment Successful! Order has been recorded.'); window.location.href='index.php';</script>";
} else {
    echo "Error: " . $conn->error;
}
?>