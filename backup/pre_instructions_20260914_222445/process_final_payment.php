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
    $new_status = 'Processing';
    $new_payment_status = 'Paid 50% DP';
    $redirect_tab = 'Processing';
} elseif ($payment_type === 'balance') {
    $new_status = 'Completed';
    $new_payment_status = 'Fully Paid';
    $redirect_tab = 'Completed';
} else {
    header("Location: my_orders.php");
    exit();
}

// 3. Auto-Create Receipt Column if not exists
$check_col = $conn->query("SHOW COLUMNS FROM orders LIKE 'receipt_image'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE orders ADD receipt_image VARCHAR(255) DEFAULT NULL");
}

// 4. Handle Receipt Upload
$receipt_path = "";
if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] == 0) {
    // Gumawa ng folder kung wala pa
    if (!file_exists('uploads/receipts')) { 
        mkdir('uploads/receipts', 0777, true); 
    }
    
    // Unique filename
    $filename = time() . '_' . basename($_FILES['receipt_image']['name']);
    $target = "uploads/receipts/" . $filename;
    
    if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $target)) {
        $receipt_path = $target;
    }
}

// 5. Update Database
if ($receipt_path !== "") {
    $sql = "UPDATE orders SET 
            status = ?, 
            payment_status = ?, 
            amount_paid = amount_paid + ?, 
            receipt_image = ? 
            WHERE order_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdsii", $new_status, $new_payment_status, $amount, $receipt_path, $order_id, $_SESSION['user_id']);
} else {
    // Fallback in case upload failed
    $sql = "UPDATE orders SET 
            status = ?, 
            payment_status = ?, 
            amount_paid = amount_paid + ? 
            WHERE order_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdii", $new_status, $new_payment_status, $amount, $order_id, $_SESSION['user_id']);
}

if ($stmt->execute()) {
    echo "<script>
        alert('Payment Successful! Your order status is now: " . $new_status . "');
        window.location.href = 'my_orders.php?status=" . $redirect_tab . "';
    </script>";
} else {
    echo "Error updating record: " . $conn->error;
}
?>