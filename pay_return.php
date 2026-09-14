<?php
session_start();
require_once 'db_connect.php';
require_once 'paymongo.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id    = intval($_SESSION['user_id']);
$payment_id = isset($_GET['ref']) ? intval($_GET['ref']) : 0;
$token      = $_GET['t'] ?? '';
$attempt    = isset($_GET['try']) ? intval($_GET['try']) : 0;

// --- 1. Kunin at patunayan ang payments row ---
$stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id = ?");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$pay = $stmt->get_result()->fetch_assoc();

if (!$pay || !hash_equals((string)$pay['return_token'], (string)$token) || intval($pay['user_id']) !== $user_id) {
    echo "<script>alert('Hindi wasto ang payment link.'); window.location.href='my_orders.php';</script>";
    exit();
}

$order_id = intval($pay['order_id']);
$state    = 'pending';   // pending | paid | already | mismatch | error
$message  = '';

// --- 2. REFRESH GUARD: kapag na-apply na, huwag nang ulitin ---
if (intval($pay['applied']) === 1) {
    $state = 'already';
} else {

    // --- 3. Tanungin ang PayMongo kung bayad na ---
    // Minsan nahuhuli ng ilang segundo ang GCash bago i-attach ang payment,
    // kaya inuulit natin ng ilang beses bago sumuko.
    $paid_payment = null;
    $last_error   = '';

    for ($i = 0; $i < 3; $i++) {
        [$code, $resp] = pm_get_checkout_session($conn, $pay['checkout_session_id']);
        if ($code >= 200 && $code < 300) {
            $paid_payment = pm_find_paid_payment($resp);
            if ($paid_payment) break;
        } else {
            $last_error = pm_error_message($resp);
        }
        if ($i < 2) usleep(1500000); // 1.5s
    }

    if ($paid_payment) {
        $provider_payment_id = $paid_payment['id'] ?? null;
        $paid_centavos       = intval($paid_payment['attributes']['amount'] ?? 0);

        // --- 4. AMOUNT VERIFICATION: dapat eksaktong tugma sa hiningi natin ---
        if ($paid_centavos !== intval($pay['amount_centavos'])) {
            $raw = json_encode($paid_payment);
            $u = $conn->prepare("UPDATE payments SET status = 'mismatch', raw_response = ? WHERE payment_id = ?");
            $u->bind_param("si", $raw, $payment_id);
            $u->execute();
            $state   = 'mismatch';
            $message = 'Hindi tugma ang halagang nabayaran (₱' . number_format($paid_centavos / 100, 2) .
                       ') sa inaasahan (₱' . number_format($pay['amount_centavos'] / 100, 2) . ').';
        } else {

            // --- 5. I-credit sa loob ng transaction ---
            // Tatlong magkahiwalay na proteksyon laban sa doble: ang applied = 0 sa
            // WHERE, ang FOR UPDATE row lock, at ang UNIQUE(provider_payment_id).
            $conn->begin_transaction();
            try {
                $lock = $conn->prepare("SELECT applied FROM payments WHERE payment_id = ? FOR UPDATE");
                $lock->bind_param("i", $payment_id);
                $lock->execute();
                $locked = $lock->get_result()->fetch_assoc();

                if (!$locked || intval($locked['applied']) === 1) {
                    $conn->rollback();
                    $state = 'already';
                } else {
                    $raw = json_encode($paid_payment);
                    $mark = $conn->prepare("UPDATE payments
                                            SET status = 'paid', provider_payment_id = ?, paid_at = NOW(),
                                                applied = 1, raw_response = ?
                                            WHERE payment_id = ? AND applied = 0");
                    $mark->bind_param("ssi", $provider_payment_id, $raw, $payment_id);
                    $mark->execute();

                    if ($mark->affected_rows !== 1) {
                        // May ibang request na nakauna - hayaan na natin.
                        $conn->rollback();
                        $state = 'already';
                    } else {
                        $amount = floatval($pay['amount']);

                        // Ano ang bagong status ng order?
                        $ord = $conn->prepare("SELECT status, total_price, shipping_fee, grand_total, amount_paid
                                               FROM orders WHERE order_id = ? FOR UPDATE");
                        $ord->bind_param("i", $order_id);
                        $ord->execute();
                        $o = $ord->get_result()->fetch_assoc();

                        $grand     = $o['grand_total'] !== null
                                   ? floatval($o['grand_total'])
                                   : floatval($o['total_price']) + floatval($o['shipping_fee']);
                        $new_paid  = floatval($o['amount_paid']) + $amount;
                        $settled   = ($new_paid + 0.01) >= $grand;   // baka may piso na maliit na difference

                        if ($pay['purpose'] === 'balance') {
                            $new_pay_status = $settled ? 'Fully Paid' : 'Paid ' . setting_int($conn, 'dp_percent', 50) . '% DP';
                            // Ang balanse ay binabayaran sa dulo - Completed na kapag na-receive na.
                            $new_status = ($o['status'] === 'To Receive' && $settled) ? 'Completed' : $o['status'];
                        } elseif ($pay['purpose'] === 'full' || $settled) {
                            $new_pay_status = 'Fully Paid';
                            $new_status = in_array($o['status'], ['To Pay', 'Pending'], true) ? 'Processing' : $o['status'];
                        } else {
                            $new_pay_status = 'Paid ' . setting_int($conn, 'dp_percent', 50) . '% DP';
                            $new_status = in_array($o['status'], ['To Pay', 'Pending'], true) ? 'Processing' : $o['status'];
                        }

                        $upd = $conn->prepare("UPDATE orders
                                               SET amount_paid = amount_paid + ?, payment_status = ?, status = ?
                                               WHERE order_id = ?");
                        $upd->bind_param("dssi", $amount, $new_pay_status, $new_status, $order_id);
                        $upd->execute();

                        $conn->commit();
                        $state = 'paid';
                    }
                }
            } catch (Throwable $e) {
                $conn->rollback();
                $state   = 'error';
                $message = 'May problema sa pag-record ng bayad. Pakikontak ang shop.';
            }
        }
    } else {
        $state   = 'pending';
        $message = $last_error;
    }
}

// Kunin ang pinakabagong order info para sa display
$ostmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
$ostmt->bind_param("i", $order_id);
$ostmt->execute();
$order = $ostmt->get_result()->fetch_assoc();

$grand_disp   = $order && $order['grand_total'] !== null
              ? floatval($order['grand_total'])
              : floatval($order['total_price'] ?? 0) + floatval($order['shipping_fee'] ?? 0);
$balance_disp = max(0, $grand_disp - floatval($order['amount_paid'] ?? 0));

// Auto-refresh habang hinihintay ang kumpirmasyon (max 6 na beses)
$auto_refresh = ($state === 'pending' && $attempt < 6);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status | Chub's Handicrafts</title>
    <?php if ($auto_refresh): ?>
        <meta http-equiv="refresh" content="5;url=pay_return.php?ref=<?= $payment_id ?>&t=<?= urlencode($token) ?>&try=<?= $attempt + 1 ?>">
    <?php endif; ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
        :root { --coral: #f18973; --peach: #fce0d8; --text-dark: #444; }
        body { font-family: 'Montserrat', sans-serif; background: #fffafb; margin: 0; color: var(--text-dark);
               display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; box-sizing: border-box; }
        .card { background: #fff; border-radius: 24px; border: 1px solid var(--peach); padding: 45px 40px;
                max-width: 440px; width: 100%; text-align: center; box-shadow: 0 20px 45px rgba(241,137,115,0.12); }
        .icon { font-size: 52px; line-height: 1; margin-bottom: 18px; }
        h1 { font-size: 22px; font-weight: 900; margin: 0 0 12px; }
        p { font-size: 13px; color: #888; line-height: 1.7; margin: 0 0 10px; }
        .amount { font-size: 34px; font-weight: 900; color: var(--coral); margin: 18px 0 6px; }
        .rows { background: #fdf8f6; border-radius: 14px; padding: 18px 22px; margin: 25px 0; text-align: left; }
        .row { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 8px; }
        .row:last-child { margin-bottom: 0; }
        .row .k { color: #aaa; }
        .row .v { font-weight: 700; }
        .btn { display: block; background: var(--coral); color: #fff; text-decoration: none; padding: 16px;
               border-radius: 50px; font-weight: 900; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;
               margin-top: 10px; border: none; width: 100%; cursor: pointer; font-family: inherit; transition: 0.3s; }
        .btn:hover { background: #e07661; }
        .btn-ghost { background: #fff; color: #888; border: 1px solid #ddd; }
        .btn-ghost:hover { background: #f7f7f7; color: #666; }
        .ok { color: #2e7d32; } .warn { color: #a15c00; } .bad { color: #c62828; }
        .spinner { width: 34px; height: 34px; border: 3px solid var(--peach); border-top-color: var(--coral);
                   border-radius: 50%; margin: 0 auto 18px; animation: spin 0.9s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>
<div class="card">

    <?php if ($state === 'paid'): ?>
        <div class="icon ok">&#10003;</div>
        <h1 class="ok">Salamat! Natanggap na ang bayad.</h1>
        <div class="amount">₱<?= number_format($pay['amount'], 2) ?></div>
        <p>Order #<?= $order_id ?></p>

    <?php elseif ($state === 'already'): ?>
        <div class="icon ok">&#10003;</div>
        <h1 class="ok">Naitala na ang bayad na ito.</h1>
        <p>Hindi ka nasingil ng dalawang beses. Order #<?= $order_id ?>.</p>

    <?php elseif ($state === 'mismatch'): ?>
        <div class="icon bad">&#9888;</div>
        <h1 class="bad">Kailangan ng manu-manong pagsusuri</h1>
        <p><?= htmlspecialchars($message) ?></p>
        <p>Hindi muna namin ito itinala. Pakikontak ang shop na dala ang Order #<?= $order_id ?>.</p>

    <?php elseif ($state === 'error'): ?>
        <div class="icon bad">&#9888;</div>
        <h1 class="bad">May naganap na problema</h1>
        <p><?= htmlspecialchars($message) ?></p>

    <?php else: ?>
        <div class="spinner"></div>
        <h1 class="warn">Kino-kumpirma pa ang bayad...</h1>
        <p>
            Kung nabayaran mo na ito sa GCash, sandali lang bago ito lumabas.
            <?php if ($auto_refresh): ?>Awtomatiko itong magche-check ulit sa loob ng 5 segundo.<?php endif; ?>
        </p>
        <?php if (!$auto_refresh): ?>
            <p class="warn">Hindi pa rin kumpirmado pagkatapos ng ilang subok. Kung may nabawas sa GCash mo,
               huwag mag-alala - naitala ang bayad sa PayMongo. Pakikontak ang shop na dala ang Order #<?= $order_id ?>.</p>
        <?php endif; ?>
        <a class="btn" href="pay_return.php?ref=<?= $payment_id ?>&t=<?= urlencode($token) ?>&try=<?= $attempt + 1 ?>">Check again</a>
    <?php endif; ?>

    <?php if ($order): ?>
    <div class="rows">
        <div class="row"><span class="k">Order Total</span><span class="v">₱<?= number_format($grand_disp, 2) ?></span></div>
        <div class="row"><span class="k">Bayad na</span><span class="v">₱<?= number_format($order['amount_paid'], 2) ?></span></div>
        <div class="row">
            <span class="k"><?= $balance_disp > 0 ? 'Babayaran sa pagdating' : 'Balanse' ?></span>
            <span class="v"><?= $balance_disp > 0 ? '₱' . number_format($balance_disp, 2) : 'Wala na' ?></span>
        </div>
        <div class="row"><span class="k">Status</span><span class="v"><?= htmlspecialchars($order['status']) ?></span></div>
    </div>
    <?php endif; ?>

    <a class="btn" href="my_orders.php?status=<?= urlencode($order['status'] ?? 'All') ?>">View My Orders</a>
    <a class="btn btn-ghost" href="shop.php">Continue Shopping</a>
</div>
</body>
</html>
