<?php
session_start();
require_once 'db_connect.php';

// 1. Protection Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Catch the data (Inayos: Binabasa na ngayon both POST at GET)
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : (isset($_GET['id']) ? intval($_GET['id']) : null);
$payment_type = isset($_POST['payment_type']) ? $_POST['payment_type'] : (isset($_GET['type']) ? $_GET['type'] : 'dp');

if (!$order_id) {
    echo "<script>alert('Error: Order ID missing.'); window.location.href='my_orders.php';</script>";
    exit();
}

// 3. Fetch Order Details for verification
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Order not found or unauthorized access.");
}

// 4. Determine Amount to Pay
$display_amount = 0;
$payment_label = "";

if ($payment_type == 'balance') {
    $display_amount = $order['total_price'] * 0.5;
    $payment_label = "Remaining Balance (50%)";
} else {
    // Para sa DP, isasama yung shipping fee kung pinasa sa POST, kung wala, exact 50% ng price
    $display_amount = isset($_POST['dp_amount']) ? floatval($_POST['dp_amount']) : ($order['total_price'] * 0.5);
    $payment_label = "Initial Downpayment (50%)";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCash Payment Portal | Vinescraft</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #0157ff; margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; box-sizing: border-box; }
        .gcash-container { width: 100%; max-width: 400px; background: #fff; border-radius: 15px; overflow: hidden; box-shadow: 0 20px 45px rgba(0,0,0,0.3); }
        
        /* HEADER WITH LOGO */
        .gcash-header { background-color: #0157ff; padding: 20px; display: flex; align-items: center; justify-content: center; gap: 12px; }
        .gcash-mini-logo { width: 32px; height: 32px; object-fit: contain; }
        
        .merchant-info { padding: 15px; text-align: center; border-bottom: 1px solid #f2f2f2; }
        .merchant-name { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 1px; }
        .vines-brand { font-weight: 900; font-size: 18px; color: #0157ff; }
        
        .amount-section { padding: 20px; text-align: center; background: #fafafa; }
        .amount-display { font-size: 38px; font-weight: 900; color: #111; margin: 0; }
        
        /* QR & ACCOUNT INFO SECTION */
        .qr-section { padding: 25px; text-align: center; background: white; }
        .qr-image { width: 200px; height: 200px; border: 1px solid #eee; padding: 10px; border-radius: 12px; margin-bottom: 20px; }
        .account-info { color: #333; }
        .info-label { font-size: 11px; color: #888; font-weight: 800; text-transform: uppercase; display: block; }
        .acc-number { font-size: 22px; font-weight: 900; color: #0157ff; margin: 4px 0 8px; display: block; letter-spacing: 1px; }
        .acc-name { font-size: 14px; font-weight: 700; color: #444; }

        .payment-details { padding: 15px 30px; border-top: 1px solid #f0f0f0; background: #fdfdfd; }
        .row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
        .label { color: #999; }
        .val { font-weight: 700; color: #333; }

        .btn-pay { display: block; width: 85%; margin: 20px auto; background-color: #0157ff; color: white; border: none; padding: 16px; border-radius: 50px; font-size: 15px; font-weight: 900; cursor: pointer; transition: 0.3s; text-transform: uppercase; box-shadow: 0 5px 15px rgba(1, 87, 255, 0.3); }
        .btn-pay:hover { background-color: #0046cc; transform: translateY(-2px); }
        
        .footer-secure { text-align: center; font-size: 9px; color: #ccc; padding-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

<div class="gcash-container">
    <div class="gcash-header">
        <img src="img/gcash.png" alt="Icon" class="gcash-mini-logo" onerror="this.style.display='none'">
        <span style="color: white; font-weight: 900; font-size: 20px; letter-spacing: 1px;">GCash</span>
    </div>

    <div class="merchant-info">
        <div class="merchant-name">Paying to</div>
        <div class="vines-brand">GAZETTE IN VINES SHOP</div>
    </div>

    <div class="amount-section">
        <div style="font-size: 11px; color: #888;">Total Amount to Pay</div>
        <div class="amount-display">₱ <?= number_format($display_amount, 2) ?></div>
        <div style="font-size: 10px; color: #ad1457; font-weight: 900; margin-top: 5px;"><?= $payment_label ?></div>
    </div>

    <div class="qr-section">
        <img src="img/qr.jpg" alt="GCash QR Code" class="qr-image" onerror="this.style.display='none'">
        <div class="account-info">
            <span class="info-label">GCash Number</span>
            <span class="acc-number">09206488630</span>
            <span class="acc-name">Account Name: MI*****E G.</span>
        </div>
    </div>

    <div class="payment-details">
        <div class="row">
            <span class="label">Order Reference</span>
            <span class="val">#<?= htmlspecialchars($order_id) ?></span>
        </div>
        <div class="row">
            <span class="label">Payment Type</span>
            <span class="val"><?= strtoupper($payment_type) ?></span>
        </div>
    </div>

    <form action="process_final_payment.php" method="POST">
        <input type="hidden" name="order_id" value="<?= $order_id ?>">
        <input type="hidden" name="payment_type" value="<?= $payment_type ?>">
        <input type="hidden" name="amount" value="<?= $display_amount ?>">
        <button type="submit" class="btn-pay">I have Sent the Payment</button>
    </form>

    <div class="footer-secure">
        Verified Merchant • Secure Vinescraft System
    </div>
</div>

</body>
</html>