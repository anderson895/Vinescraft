<?php
session_start();
require_once 'db_connect.php';
require_once 'paymongo.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id  = intval($_SESSION['user_id']);
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
$purpose  = $_GET['purpose'] ?? 'dp';
if (!in_array($purpose, ['dp', 'full', 'balance'], true)) $purpose = 'dp';

function pay_fail($msg, $order_id = 0) {
    $back = $order_id ? "checkout.php?id=$order_id" : "my_orders.php";
    echo "<script>alert(" . json_encode($msg) . "); window.location.href=" . json_encode($back) . ";</script>";
    exit();
}

if (!$order_id) pay_fail('Missing order ID.');

// --- 1. Kunin ang order (naka-scope sa may-ari) ---
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) pay_fail('Order not found.');
if (in_array($order['status'], ['Cancelled', 'Completed'], true)) {
    pay_fail('This order is already closed.');
}

// --- 2. I-compute ang halaga DITO LANG. Walang halagang galing sa browser. ---
$subtotal   = floatval($order['total_price']);
$fee        = floatval($order['shipping_fee']);
$grand      = $order['grand_total'] !== null ? floatval($order['grand_total']) : $subtotal + $fee;
$paid       = floatval($order['amount_paid']);
$remaining  = round($grand - $paid, 2);
$dp_percent = setting_int($conn, 'dp_percent', 50);

if ($purpose === 'dp') {
    $amount = round($subtotal * ($dp_percent / 100), 2) + $fee;
    $label  = $dp_percent . '% Downpayment';
} elseif ($purpose === 'balance') {
    $amount = $remaining;
    $label  = 'Remaining Balance';
} else {
    $amount = $grand;
    $label  = 'Full Payment';
}

// Huwag hayaang lumampas sa natitirang utang
if ($amount > $remaining) $amount = $remaining;
$amount = round($amount, 2);

if ($amount <= 0) {
    pay_fail('There is nothing left to pay on this order.', $order_id);
}

$amount_centavos = pm_pesos_to_centavos($amount);
$min_centavos    = setting_int($conn, 'paymongo_min_centavos', 10000);
if ($amount_centavos < $min_centavos) {
    pay_fail('The minimum online payment is ₱' . number_format($min_centavos / 100, 2) .
             '. Please choose Full Payment or Cash on Delivery.', $order_id);
}

$keys = paymongo_keys($conn);
if ($keys === false) {
    pay_fail('The payment gateway is not set up yet. Please contact the shop.', $order_id);
}

// --- 3. Gumawa muna ng payments row BAGO mag-redirect ---
// Kahit mawala ang customer sa gitna, may record tayo na mapo-poll mamaya.
$return_token = bin2hex(random_bytes(16));
$ins = $conn->prepare("INSERT INTO payments (order_id, user_id, provider, mode, purpose, amount, amount_centavos, return_token, status)
                       VALUES (?, ?, 'paymongo', ?, ?, ?, ?, ?, 'pending')");
$ins->bind_param("iissdis", $order_id, $user_id, $keys['mode'], $purpose, $amount, $amount_centavos, $return_token);
$ins->execute();
$payment_id = $conn->insert_id;

if (!$payment_id) {
    pay_fail('Could not start the payment. Please try again.', $order_id);
}

// --- 4. Gumawa ng Checkout Session ---
$base = pm_site_base_url();
[$code, $resp] = pm_create_checkout_session($conn, [
    'name'            => "Order #$order_id - $label",
    'description'     => "Chub's Handicrafts Order #$order_id",
    'reference'       => "CHUBS-$order_id-$payment_id",
    'amount_centavos' => $amount_centavos,
    'success_url'     => "$base/pay_return.php?ref=$payment_id&t=$return_token",
    'cancel_url'      => "$base/pay_cancel.php?ref=$payment_id&t=$return_token",
]);

if ($code < 200 || $code >= 300 || empty($resp['data']['id'])) {
    $err = pm_error_message($resp);
    $raw = json_encode($resp);
    $upd = $conn->prepare("UPDATE payments SET status = 'failed', raw_response = ? WHERE payment_id = ?");
    $upd->bind_param("si", $raw, $payment_id);
    $upd->execute();
    pay_fail('Could not create the payment: ' . $err, $order_id);
}

$session_id   = $resp['data']['id'];
$intent_id    = $resp['data']['attributes']['payment_intent']['id'] ?? null;
$checkout_url = $resp['data']['attributes']['checkout_url'] ?? '';

$upd = $conn->prepare("UPDATE payments SET checkout_session_id = ?, payment_intent_id = ? WHERE payment_id = ?");
$upd->bind_param("ssi", $session_id, $intent_id, $payment_id);
$upd->execute();

if (!$checkout_url) {
    pay_fail('No payment link was returned by the gateway.', $order_id);
}

// --- 5. Papunta na sa hosted page ng PayMongo ---
header("Location: " . $checkout_url);
exit();
