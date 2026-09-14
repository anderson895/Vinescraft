<?php
session_start();
require_once 'db_connect.php';

// Binack out ang customer sa hosted page ng PayMongo.
// Walang sinisingil dito at hindi ginagalaw ang amount_paid - minamarkahan
// lang natin ang attempt para malinis ang ledger.

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id    = intval($_SESSION['user_id']);
$payment_id = isset($_GET['ref']) ? intval($_GET['ref']) : 0;
$token      = $_GET['t'] ?? '';
$order_id   = 0;

if ($payment_id) {
    $stmt = $conn->prepare("SELECT order_id, user_id, return_token, applied FROM payments WHERE payment_id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc();

    if ($pay && hash_equals((string)$pay['return_token'], (string)$token) && intval($pay['user_id']) === $user_id) {
        $order_id = intval($pay['order_id']);

        // Huwag galawin ang mga naitala nang bayad - baka nakabalik lang sila sa cancel URL.
        if (intval($pay['applied']) === 0) {
            $upd = $conn->prepare("UPDATE payments SET status = 'cancelled' WHERE payment_id = ? AND applied = 0");
            $upd->bind_param("i", $payment_id);
            $upd->execute();
        }
    }
}

$back = $order_id ? "checkout.php?id=$order_id" : "my_orders.php";
echo "<script>alert('Kinansela ang bayad. Hindi ka nasingil.'); window.location.href=" . json_encode($back) . ";</script>";
