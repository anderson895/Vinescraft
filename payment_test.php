<?php
/**
 * DATING pekeng GCash page (static QR + manual na upload ng receipt).
 * Pinalitan na ito ng totoong PayMongo checkout.
 *
 * Nananatili ang file para gumana pa rin ang mga lumang link at bookmark;
 * dinadala lang nito ang request sa pay_start.php.
 * Ang orihinal ay nasa backup/payment_test_fake_gcash.php.
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$order_id = isset($_POST['order_id']) ? intval($_POST['order_id'])
          : (isset($_GET['id']) ? intval($_GET['id']) : 0);

$type = $_POST['payment_type'] ?? $_GET['type'] ?? 'dp';
$purpose = in_array($type, ['dp', 'full', 'balance'], true) ? $type : 'dp';

if (!$order_id) {
    header("Location: my_orders.php");
    exit();
}

header("Location: pay_start.php?order_id=$order_id&purpose=$purpose");
exit();
