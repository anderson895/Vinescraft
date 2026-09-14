<?php
session_start();
require_once 'db_connect.php';

// 1. Protection Check
if (!isset($_SESSION['user_id']) || !isset($_POST['order_id'])) {
    header("Location: my_orders.php");
    exit();
}

$order_id = intval($_POST['order_id']);
$payment_type = $_POST['payment_type']; // 'dp' o 'balance'
$amount = floatval($_POST['amount']);

// Default values para sa redirect at SQL
$new_status = "";
$new_payment_status = "";
$redirect_tab = "";

// 2. Linear Logic Handler
if ($payment_type === 'dp') {
    // Stage: Pagkabayad ng Downpayment
    $new_status = 'Processing';
    $new_payment_status = 'Paid 50% DP';
    $redirect_tab = 'Processing';
} elseif ($payment_type === 'balance') {
    // Stage: Pagkabayad ng natitirang 50%
    $new_status = 'Completed';
    $new_payment_status = 'Fully Paid';
    $redirect_tab = 'Completed';
} else {
    // Fallback security
    header("Location: my_orders.php");
    exit();
}

// 3. Update Database
$sql = "UPDATE orders SET 
        status = ?, 
        payment_status = ?, 
        amount_paid = amount_paid + ? 
        WHERE order_id = ? AND user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssdii", $new_status, $new_payment_status, $amount, $order_id, $_SESSION['user_id']);

if ($stmt->execute()) {
    // 4. Success Response & Smart Redirect
    echo "<script>
        alert('Payment Successful! Your order status is now: " . $new_status . "');
        window.location.href = 'my_orders.php?status=" . $redirect_tab . "';
    </script>";
} else {
    echo "Error updating record: " . $conn->error;
}
?>