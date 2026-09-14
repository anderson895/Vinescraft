<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : null;

// AJAX LOGIC: Auto-save the selected address to the order
if (isset($_POST['ajax_update_address']) && $order_id) {
    $new_addr = $conn->real_escape_string($_POST['address']);
    
    // Auto-create delivery_address column if it doesn't exist yet
    $check_col = $conn->query("SHOW COLUMNS FROM orders LIKE 'delivery_address'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD delivery_address TEXT DEFAULT NULL");
    }
    
    $conn->query("UPDATE orders SET delivery_address = '$new_addr' WHERE order_id = $order_id AND user_id = $user_id");
    exit();
}

if (!$order_id) { header("Location: my_orders.php"); exit(); }

// Kuhanin ang Order at User details
$stmt = $conn->prepare("SELECT o.*, u.name, u.mobile_no, u.phone, u.address
                        FROM orders o
                        JOIN users u ON o.user_id = u.user_id
                        WHERE o.order_id = ? AND o.user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) { die("Order not found."); }

// Hindi pa napepresyuhan ng admin ang custom request - walang mache-checkout.
if ($order['status'] === 'Pending' || floatval($order['total_price']) <= 0) {
    echo "<script>alert('Hinihintay pa ang presyo mula sa shop para sa order na ito.'); window.location.href='my_orders.php';</script>";
    exit();
}

// --- SETTINGS ---
$subtotal      = floatval($order['total_price']);
$dp_percent    = setting_int($conn, 'dp_percent', 50);
$gcash_on      = setting_bool($conn, 'gcash_enabled', true);
$cod_on        = setting_bool($conn, 'cod_enabled', true);
$couriers      = courier_options($conn);
$fee_pickup    = setting_money($conn, 'ship_fee_pickup', 0);
$fee_walkin    = setting_money($conn, 'ship_fee_walkin', 0);
$pickup_addr   = setting_get($conn, 'store_pickup_address', '');
$min_online    = setting_int($conn, 'paymongo_min_centavos', 10000) / 100;

$checkout_error = "";

// --- CONFIRM: dito nagsa-save ng piniling delivery at payment ---
// Pinipili lang ang customer; ang lahat ng halaga ay kinu-compute dito sa server.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_checkout'])) {

    $shipping_method = in_array($_POST['shipping_method'] ?? '', ['ship', 'pickup', 'walkin'], true)
                     ? $_POST['shipping_method'] : 'ship';
    $courier = ($shipping_method === 'ship' && isset($couriers[$_POST['courier'] ?? '']))
             ? $_POST['courier'] : null;
    $payment_method = ($_POST['payment_method'] ?? '') === 'COD' ? 'COD' : 'GCash';
    $payment_plan   = ($_POST['payment_plan'] ?? '') === 'full' ? 'full' : 'dp50';
    $address        = trim($_POST['address'] ?? '');

    // Igalang ang naka-off na payment methods
    if ($payment_method === 'GCash' && !$gcash_on) $payment_method = 'COD';
    if ($payment_method === 'COD'   && !$cod_on)   $payment_method = 'GCash';
    if ($payment_method === 'COD') $payment_plan = 'cod';

    // I-compute ang pera base sa DB + settings, hindi sa POST
    $fee   = shipping_fee_for($conn, $shipping_method, $courier);
    $grand = $subtotal + $fee;
    $pay_now = ($payment_plan === 'dp50')
             ? round($subtotal * ($dp_percent / 100), 2) + $fee
             : $grand;

    if ($shipping_method === 'ship' && $address === '') {
        $checkout_error = "Pumili muna ng delivery address.";
    } elseif ($shipping_method === 'ship' && !$courier) {
        $checkout_error = "Pumili muna ng courier.";
    } elseif ($payment_method === 'GCash' && $pay_now < $min_online) {
        // Minimum ng PayMongo sa e-wallet. Masyadong maliit ang DP para sa online payment.
        $checkout_error = "Masyadong maliit ang halagang babayaran online (₱" . number_format($pay_now, 2) .
                          "). Ang minimum ay ₱" . number_format($min_online, 2) .
                          ". Piliin ang Full Payment o Cash on Delivery.";
    } else {
        // FIXED: dati ay hindi talaga nasi-save ang shipping_method at shipping_fee -
        // naipapasa lang ito sa payment page tapos nawawala.
        $upd = $conn->prepare("UPDATE orders SET shipping_method = ?, courier = ?, shipping_fee = ?,
                                      delivery_address = ?, payment_method = ?, payment_plan = ?, grand_total = ?
                               WHERE order_id = ? AND user_id = ?");
        $addr_to_save = ($shipping_method === 'ship') ? $address : $pickup_addr;
        // s=method, s=courier, d=fee, s=address, s=payment_method, s=payment_plan, d=grand, i=order, i=user
        $upd->bind_param("ssdsssdii", $shipping_method, $courier, $fee, $addr_to_save,
                                      $payment_method, $payment_plan, $grand, $order_id, $user_id);
        $upd->execute();

        if ($payment_method === 'COD') {
            // Walang babayaran online. Diretso na sa Processing para makita ng shop.
            $cod = $conn->prepare("UPDATE orders SET status = 'Processing', payment_status = 'COD'
                                   WHERE order_id = ? AND user_id = ?");
            $cod->bind_param("ii", $order_id, $user_id);
            $cod->execute();
            header("Location: my_orders.php?status=Processing");
            exit();
        }

        header("Location: pay_start.php?order_id=" . $order_id . "&purpose=" . ($payment_plan === 'full' ? 'full' : 'dp'));
        exit();
    }
}

// Kuhanin lahat ng naka-save na address ng user
$addresses = [];
$addr_res = $conn->query("SELECT * FROM user_addresses WHERE user_id = $user_id ORDER BY is_default DESC");
if ($addr_res) {
    while($row = $addr_res->fetch_assoc()) {
        $addresses[] = $row;
    }
}
// Fallback kung wala pa sa user_addresses table pero meron sa users table
if (empty($addresses) && !empty($order['address'])) {
    $addresses[] = [
        'label' => 'Default Home',
        'full_address' => $order['address']
    ];
}

$img_data = $order['custom_image'];
$is_json = (substr($img_data, 0, 1) === '{');
$thumb = $is_json ? json_decode($img_data, true)['f'] : $img_data;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout | Chub's Handicrafts</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;900&display=swap');

        :root {
            --coral: #f18973;
            --peach: #fce0d8;
            --text-dark: #444;
            --grey-light: #f9f9f9;
        }

        body { font-family: 'Montserrat', sans-serif; background: #fff; margin: 0; color: var(--text-dark); }
        
        /* NAVIGATION BAR - Title on the Left */
        .top-bar { 
            background: var(--coral); 
            color: white; 
            padding: 15px 5%; 
            display: flex; 
            align-items: center;
        }

        .top-bar-title {
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-size: 16px;
        }

        /* BACK BUTTON ROW - Under Nav Bar */
        .back-container {
            padding: 15px 5% 0;
            max-width: 1100px;
            margin: 0 auto;
        }

        .back-link {
            text-decoration: none;
            color: var(--coral);
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s;
        }

        .back-link:hover { color: #e07661; transform: translateX(-3px); }

        /* CHECKOUT CONTENT */
        .checkout-container { 
            max-width: 1100px; 
            margin: 30px auto 50px; 
            display: grid; 
            grid-template-columns: 1.5fr 1fr; 
            gap: 60px; 
            padding: 0 20px; 
        }
        
        h2 { font-size: 32px; font-weight: 900; color: var(--coral); margin-bottom: 25px; text-transform: lowercase; }

        /* TABS & CARDS */
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; }
        .tab { flex: 1; padding: 15px; text-align: center; cursor: pointer; font-weight: 700; border: 2px solid var(--peach); border-radius: 50px; font-size: 11px; transition: 0.3s; }
        .tab.active { background: var(--coral); color: white; border-color: var(--coral); }
        
        .address-card { background: var(--grey-light); padding: 25px; border-radius: 15px; border: 1px solid var(--peach); }

        /* SUMMARY */
        .summary-panel { background: #fff; border: 2px solid var(--peach); padding: 30px; border-radius: 20px; position: sticky; top: 20px; }
        .total-row { display: flex; justify-content: space-between; font-weight: 900; font-size: 18px; padding-top: 20px; border-top: 2px solid var(--peach); margin-top: 20px; color: var(--text-dark); }
        
        .dp-box { background: var(--peach); padding: 20px; border-radius: 12px; margin-top: 20px; text-align: center; }
        .dp-amount { font-size: 24px; font-weight: 900; color: var(--coral); display: block; margin-top: 5px; }

        .btn-confirm { 
            background: var(--coral); color: white; border: none; width: 100%; padding: 20px; 
            font-size: 14px; font-weight: 900; border-radius: 50px; cursor: pointer; 
            text-transform: uppercase; margin-top: 30px; box-shadow: 0 10px 20px rgba(241, 137, 115, 0.3); 
            transition: 0.3s;
        }
        .btn-confirm:hover:not(:disabled) { background: #e07661; transform: translateY(-3px); }
        .btn-confirm:disabled { background: #ccc; cursor: not-allowed; box-shadow: none; }

        /* NUMBERED STEP CARDS */
        .step { margin-bottom: 35px; }
        .step-title { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
        .step-num {
            width: 26px; height: 26px; border-radius: 50%; background: var(--coral); color: #fff;
            font-size: 12px; font-weight: 900; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .step-title h3 { font-size: 13px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; margin: 0; color: var(--text-dark); }

        /* RADIO OPTION CARDS */
        .opt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .opt-card {
            display: flex; align-items: flex-start; gap: 11px; padding: 16px 18px;
            border: 2px solid var(--peach); border-radius: 14px; cursor: pointer; transition: 0.25s; background: #fff;
        }
        .opt-card:hover { border-color: #f5b9a9; }
        .opt-card.selected { border-color: var(--coral); background: #fff8f6; }
        .opt-card.disabled { opacity: 0.5; cursor: not-allowed; background: #fafafa; }
        .opt-card.disabled:hover { border-color: var(--peach); }
        .opt-card input { margin: 2px 0 0; accent-color: var(--coral); cursor: inherit; flex-shrink: 0; }
        .opt-card .opt-body { flex: 1; min-width: 0; }
        .opt-card strong { display: block; font-size: 13px; font-weight: 900; margin-bottom: 3px; }
        .opt-card small { display: block; font-size: 11px; color: #999; line-height: 1.5; }
        .opt-card .opt-fee { font-size: 12px; font-weight: 900; color: var(--coral); }

        .pickup-note {
            background: var(--peach); border-radius: 12px; padding: 16px 18px; font-size: 12px; line-height: 1.6;
        }
        .pickup-note strong { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--coral); margin-bottom: 5px; }

        .err-box { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; padding: 14px 18px; border-radius: 12px; font-size: 12px; font-weight: 700; margin-bottom: 25px; }

        .sum-line { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .dp-box small { display: block; font-size: 10px; color: #a2695c; margin-top: 8px; line-height: 1.5; }

        @media (max-width: 900px) {
            .checkout-container { grid-template-columns: 1fr; gap: 30px; }
            .summary-panel { position: static; }
            .opt-grid { grid-template-columns: 1fr; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

    <!-- NAVIGATION BAR -->
    <div class="top-bar">
        <div class="top-bar-title">Checkout</div>
    </div>

    <!-- BACK BUTTON ROW -->
    <div class="back-container">
        <a href="javascript:history.back()" class="back-link">
            <span>←</span> Back to Shopping
        </a>
    </div>

    <form method="POST" id="checkoutForm">
    <input type="hidden" name="confirm_checkout" value="1">
    <div class="checkout-container">

        <!-- LEFT: DELIVERY + PAYMENT -->
        <div class="process-side">

            <?php if ($checkout_error): ?>
                <div class="err-box"><?= htmlspecialchars($checkout_error) ?></div>
            <?php endif; ?>

            <!-- STEP 1: DELIVERY -->
            <div class="step">
                <div class="step-title"><span class="step-num">1</span><h3>Delivery Method</h3></div>

                <div class="tabs">
                    <div class="tab active" data-method="ship" onclick="setMethod('ship', this)">SHIP</div>
                    <div class="tab" data-method="pickup" onclick="setMethod('pickup', this)">PICK UP</div>
                    <div class="tab" data-method="walkin" onclick="setMethod('walkin', this)">WALK IN</div>
                </div>
                <input type="hidden" name="shipping_method" id="final-method" value="ship">

                <!-- Courier choice (SHIP lang) -->
                <div id="courier-block" style="margin-bottom: 20px;">
                    <div class="opt-grid">
                        <?php $ci = 0; foreach ($couriers as $c_name => $c_fee): $ci++; ?>
                        <label class="opt-card <?= $ci === 1 ? 'selected' : '' ?>" data-group="courier">
                            <input type="radio" name="courier" value="<?= htmlspecialchars($c_name) ?>" <?= $ci === 1 ? 'checked' : '' ?>
                                   data-fee="<?= $c_fee ?>" onchange="recalc()">
                            <div class="opt-body">
                                <strong><?= htmlspecialchars($c_name) ?></strong>
                                <span class="opt-fee">₱<?= number_format($c_fee, 2) ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Address (SHIP lang) -->
                <div id="address-block" class="address-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="color: var(--coral); font-weight: 900; font-size: 11px; letter-spacing: 1px;">SHIPPING TO:</span>
                        <a href="profile.php" style="font-size: 9px; color: #888; text-decoration: none; border: 1px solid #ddd; padding: 4px 10px; border-radius: 50px; font-weight: bold; transition: 0.3s;">Manage / Add Address</a>
                    </div>

                    <p style="margin: 5px 0;"><strong><?= htmlspecialchars($order['name']) ?></strong> | <?= htmlspecialchars($order['mobile_no'] ?: $order['phone']) ?></p>

                    <?php if(empty($addresses)): ?>
                        <div style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 10px; font-size: 12px; margin-top: 15px; border: 1px dashed #ef9a9a;">
                            <strong>No delivery address found.</strong><br>
                            Please <a href="profile.php" style="color: #c62828; font-weight: 900; text-decoration: underline;">click here to add an address</a> in your profile to continue.
                        </div>
                    <?php else: ?>
                        <select id="address-select" name="address" onchange="updateSelectedAddress()" style="width:100%; padding:12px; margin-top:10px; border-radius:10px; border:1px solid var(--peach); font-family:inherit; font-size: 13px; outline: none; background: white; color: var(--text-dark); cursor: pointer;">
                            <?php foreach($addresses as $addr): ?>
                                <option value="<?= htmlspecialchars($addr['full_address']) ?>">
                                    <?= htmlspecialchars($addr['label']) ?>: <?= htmlspecialchars($addr['full_address']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <!-- Pick up / Walk in note -->
                <div id="pickup-block" class="pickup-note" style="display:none;">
                    <strong id="pickup-label">Pick up at</strong>
                    <?= htmlspecialchars($pickup_addr ?: "Chub's Handicrafts") ?>
                    <div style="margin-top:6px; color:#a2695c;">Walang shipping fee.</div>
                </div>
            </div>

            <!-- STEP 2: PAYMENT -->
            <div class="step">
                <div class="step-title"><span class="step-num">2</span><h3>Payment Method</h3></div>

                <div class="opt-grid" style="margin-bottom: 14px;">
                    <label class="opt-card <?= $gcash_on ? 'selected' : 'disabled' ?>" data-group="pm">
                        <input type="radio" name="payment_method" value="GCash" <?= $gcash_on ? 'checked' : 'disabled' ?> onchange="recalc()">
                        <div class="opt-body">
                            <strong>GCash</strong>
                            <small>Bayad online ngayon.</small>
                        </div>
                    </label>
                    <label class="opt-card <?= !$gcash_on && $cod_on ? 'selected' : ($cod_on ? '' : 'disabled') ?>" data-group="pm">
                        <input type="radio" name="payment_method" value="COD" <?= !$gcash_on && $cod_on ? 'checked' : '' ?> <?= $cod_on ? '' : 'disabled' ?> onchange="recalc()">
                        <div class="opt-body">
                            <strong>Cash on Delivery</strong>
                            <small>Bayad sa pagdating.</small>
                        </div>
                    </label>
                </div>

                <!-- GCash: DP o Full -->
                <div id="plan-block">
                    <div class="opt-grid">
                        <label class="opt-card" data-group="plan" id="plan-dp-card">
                            <input type="radio" name="payment_plan" value="dp50" id="plan-dp" onchange="recalc()">
                            <div class="opt-body">
                                <strong><?= $dp_percent ?>% Downpayment</strong>
                                <small id="dp-hint">Bayad ngayon, balanse sa pagdating.</small>
                            </div>
                        </label>
                        <label class="opt-card selected" data-group="plan" id="plan-full-card">
                            <input type="radio" name="payment_plan" value="full" id="plan-full" checked onchange="recalc()">
                            <div class="opt-body">
                                <strong>Full Payment</strong>
                                <small>Bayad lahat ngayon.</small>
                            </div>
                        </label>
                    </div>
                </div>

                <div id="cod-note" class="pickup-note" style="display:none; margin-top:4px;">
                    <strong>Cash on Delivery</strong>
                    <span id="cod-text">Ibabayad mo ang buong halaga sa rider pagdating ng order.</span>
                </div>
            </div>
        </div>

        <!-- RIGHT: SUMMARY -->
        <div class="summary-side">
            <div class="summary-panel">
                <h2>your bag</h2>

                <div class="sum-line">
                    <span>Subtotal</span>
                    <span>₱<?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="sum-line">
                    <span id="ship-label">Shipping</span>
                    <span id="sum-ship">₱0.00</span>
                </div>

                <div class="total-row">
                    <span>Total Amount</span>
                    <span id="sum-total">₱0.00</span>
                </div>

                <div class="dp-box">
                    <span style="font-size: 10px; font-weight: 700; opacity: 0.8;" id="paynow-label">PAY NOW</span>
                    <span class="dp-amount" id="sum-paynow">₱0.00</span>
                    <small id="balance-note"></small>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 30px; align-items: center;">
                    <img src="<?= $thumb ?>" alt="Preview" style="width: 70px; height: 90px; object-fit: cover; border-radius: 8px; border: 1px solid var(--peach);">
                    <div style="font-size: 12px;">
                        <div style="font-weight: 900; font-size: 14px; color: var(--coral);"><?= htmlspecialchars($order['order_type'] ?: 'Your Order') ?></div>
                        <div style="color: #888;">Order ID: #<?= htmlspecialchars($order['order_id']) ?></div>
                    </div>
                </div>

                <button type="submit" id="btn-confirm" class="btn-confirm">Continue to Payment</button>
                <p id="min-warning" style="display:none; font-size:11px; color:#c62828; text-align:center; margin-top:12px; line-height:1.5;"></p>
            </div>
        </div>
    </div>
    </form>

    <script>
        // Display lang ang mga numero dito. Ini-compute ulit ng server ang lahat
        // pagka-submit, kaya walang epekto kung pakialaman ito sa browser.
        const SUBTOTAL   = <?= json_encode((float)$subtotal) ?>;
        const DP_PERCENT = <?= json_encode((int)$dp_percent) ?>;
        const FEE_PICKUP = <?= json_encode((float)$fee_pickup) ?>;
        const FEE_WALKIN = <?= json_encode((float)$fee_walkin) ?>;
        const MIN_ONLINE = <?= json_encode((float)$min_online) ?>;
        const HAS_ADDRESS = <?= json_encode(!empty($addresses)) ?>;

        const peso = n => "₱" + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        function currentMethod() { return document.getElementById('final-method').value; }
        function currentPM() { return document.querySelector('input[name="payment_method"]:checked')?.value || 'GCash'; }
        function currentPlan() { return document.querySelector('input[name="payment_plan"]:checked')?.value || 'full'; }

        function shipFee() {
            const m = currentMethod();
            if (m === 'pickup') return FEE_PICKUP;
            if (m === 'walkin') return FEE_WALKIN;
            const c = document.querySelector('input[name="courier"]:checked');
            return c ? parseFloat(c.dataset.fee) : 0;
        }

        function setMethod(method, el) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('final-method').value = method;

            const isShip = (method === 'ship');
            document.getElementById('courier-block').style.display = isShip ? 'block' : 'none';
            document.getElementById('address-block').style.display = isShip ? 'block' : 'none';
            document.getElementById('pickup-block').style.display = isShip ? 'none' : 'block';
            document.getElementById('pickup-label').innerText = (method === 'walkin') ? 'Walk in at' : 'Pick up at';

            // Wala nang "delivery" kung pickup/walkin - hindi magkasya ang COD wording
            document.getElementById('cod-text').innerText = isShip
                ? 'Ibabayad mo ang buong halaga sa rider pagdating ng order.'
                : 'Ibabayad mo ang buong halaga sa shop pagkuha mo ng order.';

            // Kapag naka-disable ang address select, huwag itong isama sa POST
            const sel = document.getElementById('address-select');
            if (sel) sel.disabled = !isShip;

            recalc();
        }

        function syncCards() {
            document.querySelectorAll('.opt-card').forEach(card => {
                const input = card.querySelector('input[type="radio"]');
                if (!input || input.disabled) return;
                card.classList.toggle('selected', input.checked);
            });
        }

        function recalc() {
            const fee   = shipFee();
            const total = SUBTOTAL + fee;
            const isCOD = currentPM() === 'COD';
            const plan  = currentPlan();

            // Walang DP/Full na pagpipilian kapag COD - buo ang bayad sa pagdating
            document.getElementById('plan-block').style.display = isCOD ? 'none' : 'block';
            document.getElementById('cod-note').style.display   = isCOD ? 'block' : 'none';

            const dpNow = Math.round(SUBTOTAL * (DP_PERCENT / 100) * 100) / 100 + fee;
            document.getElementById('dp-hint').innerText =
                'Bayad ngayon ' + peso(dpNow) + ', balanse ' + peso(total - dpNow) + ' sa pagdating.';

            // Minimum ng PayMongo - masyadong maliit ang DP para sa online payment
            const dpTooSmall = dpNow < MIN_ONLINE;
            const dpInput = document.getElementById('plan-dp');
            dpInput.disabled = dpTooSmall && !isCOD;
            document.getElementById('plan-dp-card').classList.toggle('disabled', dpInput.disabled);
            if (dpInput.disabled && dpInput.checked) {
                document.getElementById('plan-full').checked = true;
            }

            let payNow, label, balance;
            if (isCOD) {
                payNow = 0; label = 'PAY ON DELIVERY'; balance = total;
            } else if (plan === 'dp50' && !dpInput.disabled) {
                payNow = dpNow; label = 'PAY ' + DP_PERCENT + '% NOW'; balance = total - dpNow;
            } else {
                payNow = total; label = 'PAY IN FULL NOW'; balance = 0;
            }

            document.getElementById('ship-label').innerText =
                currentMethod() === 'ship'
                    ? 'Shipping (' + (document.querySelector('input[name="courier"]:checked')?.value || '') + ')'
                    : 'Shipping';
            document.getElementById('sum-ship').innerText   = peso(fee);
            document.getElementById('sum-total').innerText  = peso(total);
            document.getElementById('paynow-label').innerText = label;
            document.getElementById('sum-paynow').innerText = peso(isCOD ? total : payNow);
            document.getElementById('balance-note').innerText =
                balance > 0 && !isCOD ? 'Balanse sa pagdating: ' + peso(balance) : '';

            // Button label + validation
            const btn = document.getElementById('btn-confirm');
            const warn = document.getElementById('min-warning');
            const needsAddress = (currentMethod() === 'ship' && !HAS_ADDRESS);

            let blocked = needsAddress;
            warn.style.display = 'none';

            if (needsAddress) {
                warn.innerText = 'Magdagdag muna ng delivery address para makapagpatuloy.';
                warn.style.display = 'block';
            } else if (!isCOD && payNow < MIN_ONLINE) {
                warn.innerText = 'Ang minimum na online payment ay ' + peso(MIN_ONLINE) +
                                 '. Piliin ang Cash on Delivery para sa order na ito.';
                warn.style.display = 'block';
                blocked = true;
            } else if (dpTooSmall && !isCOD) {
                warn.innerText = 'Masyadong maliit ang ' + DP_PERCENT + '% downpayment para sa online payment (minimum ' + peso(MIN_ONLINE) + ').';
                warn.style.display = 'block';
            }

            btn.disabled = blocked;
            btn.innerText = isCOD ? 'Place COD Order' : ('Pay ' + peso(payNow) + ' with GCash');

            syncCards();
        }

        // AJAX function to auto-save the address to the order
        function updateSelectedAddress() {
            const addr = document.getElementById('address-select').value;
            const formData = new FormData();
            formData.append('ajax_update_address', '1');
            formData.append('address', addr);

            fetch('checkout.php?id=<?= $order_id ?>', {
                method: 'POST',
                body: formData
            }).then(response => {
                console.log("Address updated for this order.");
            }).catch(err => console.error(err));
        }

        document.addEventListener('DOMContentLoaded', () => {
            setMethod('ship', document.querySelector('.tab.active'));

            if (document.getElementById('address-select')) {
                updateSelectedAddress();
            }
        });
    </script>

</body>
</html>