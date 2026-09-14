<?php
session_start();
require_once 'db_connect.php';

/**
 * MANUAL NA RECEIPT FALLBACK (naka-off by default).
 *
 * Ito ang dating pangunahing daan ng bayad noong panahon ng pekeng GCash page.
 * Ngayon ay PayMongo na ang humahawak ng totoong bayad (tingnan ang pay_start.php),
 * kaya naka-off ito maliban kung gusto mong tumanggap ng manu-manong screenshot.
 *
 * Buksan sa pamamagitan ng: UPDATE site_settings SET setting_value = '1'
 *                           WHERE setting_key = 'payment_manual_fallback';
 */

if (!setting_bool($conn, 'payment_manual_fallback', false)) {
    echo "<script>alert('Naka-off ang manual na receipt upload. Gamitin ang GCash checkout.'); window.location.href='my_orders.php';</script>";
    exit();
}

// 1. Protection Check
if (!isset($_SESSION['user_id']) || !isset($_POST['order_id'])) {
    header("Location: my_orders.php");
    exit();
}

$user_id      = intval($_SESSION['user_id']);
$order_id     = intval($_POST['order_id']);
$payment_type = $_POST['payment_type'] ?? 'dp';

if (!in_array($payment_type, ['dp', 'full', 'balance'], true)) {
    header("Location: my_orders.php");
    exit();
}

// 2. Kunin ang order - naka-scope sa may-ari
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: my_orders.php");
    exit();
}

// 3. FIXED: kino-compute na ang halaga dito imbes na kunin sa $_POST['amount'],
// na pwede sanang palitan ng kahit sino mula sa browser.
$subtotal   = floatval($order['total_price']);
$fee        = floatval($order['shipping_fee']);
$grand      = $order['grand_total'] !== null ? floatval($order['grand_total']) : $subtotal + $fee;
$remaining  = round($grand - floatval($order['amount_paid']), 2);
$dp_percent = setting_int($conn, 'dp_percent', 50);

if ($payment_type === 'dp') {
    $amount = round($subtotal * ($dp_percent / 100), 2) + $fee;
} elseif ($payment_type === 'balance') {
    $amount = $remaining;
} else {
    $amount = $grand;
}
if ($amount > $remaining) $amount = $remaining;
$amount = round($amount, 2);

if ($amount <= 0) {
    echo "<script>alert('Wala nang babayaran sa order na ito.'); window.location.href='my_orders.php';</script>";
    exit();
}

$settled = (floatval($order['amount_paid']) + $amount + 0.01) >= $grand;

// 4. FIXED: dati ay diretso sa 'Completed' ang balance payment kahit hindi pa
// natatanggap ng customer ang order. Dapat 'To Receive' muna bago Completed.
if ($settled) {
    $new_payment_status = 'Fully Paid';
    $new_status = ($order['status'] === 'To Receive') ? 'Completed'
                : (in_array($order['status'], ['To Pay', 'Pending'], true) ? 'Processing' : $order['status']);
} else {
    $new_payment_status = 'Paid ' . $dp_percent . '% DP';
    $new_status = in_array($order['status'], ['To Pay', 'Pending'], true) ? 'Processing' : $order['status'];
}

// 5. Handle Receipt Upload
$receipt_path = "";
if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] == 0) {
    if (!file_exists('uploads/receipts')) {
        mkdir('uploads/receipts', 0777, true);
    }
    $filename = time() . '_' . basename($_FILES['receipt_image']['name']);
    $target = "uploads/receipts/" . $filename;
    if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $target)) {
        $receipt_path = $target;
    }
}

// 6. Update Database
if ($receipt_path !== "") {
    $sql = "UPDATE orders SET status = ?, payment_status = ?, amount_paid = amount_paid + ?, receipt_image = ?
            WHERE order_id = ? AND user_id = ?";
    $upd = $conn->prepare($sql);
    $upd->bind_param("ssdsii", $new_status, $new_payment_status, $amount, $receipt_path, $order_id, $user_id);
} else {
    $sql = "UPDATE orders SET status = ?, payment_status = ?, amount_paid = amount_paid + ?
            WHERE order_id = ? AND user_id = ?";
    $upd = $conn->prepare($sql);
    $upd->bind_param("ssdii", $new_status, $new_payment_status, $amount, $order_id, $user_id);
}

if ($upd->execute()) {
    echo "<script>
        alert('Naitala ang bayad na ₱" . number_format($amount, 2) . ". Status: " . $new_status . "');
        window.location.href = 'my_orders.php?status=" . urlencode($new_status) . "';
    </script>";
} else {
    echo "Error updating record: " . $conn->error;
}
