<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$order_id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$order_id) { header("Location: my_orders.php"); exit(); }

$user_id = $_SESSION['user_id'];

// Kuhanin ang Order at User details
$stmt = $conn->prepare("SELECT o.*, u.name, u.phone, u.address 
                        FROM orders o 
                        JOIN users u ON o.user_id = u.user_id 
                        WHERE o.order_id = ? AND o.user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) { die("Order not found."); }

$img_data = $order['custom_image'];
$is_json = (substr($img_data, 0, 1) === '{');
$thumb = $is_json ? json_decode($img_data, true)['f'] : $img_data;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout | Vinescraft</title>
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
        
        h2 { font-size: 22px; font-weight: 900; color: var(--coral); margin-bottom: 25px; text-transform: lowercase; }
        h2::after { content: '.'; }

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
        }
        .btn-confirm:hover { background: #e07661; transform: translateY(-3px); }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

    <!-- NAVIGATION BAR -->
    <div class="top-bar">
        <div class="top-bar-title">Vinescraft Checkout</div>
    </div>

    <!-- BACK BUTTON ROW -->
    <div class="back-container">
        <a href="javascript:history.back()" class="back-link">
            <span>←</span> Back to Shopping
        </a>
    </div>

    <div class="checkout-container">
        <!-- LEFT: DELIVERY OPTIONS -->
        <div class="process-side">
            <div class="section-box">
                <h2>delivery method</h2>
                <div class="tabs">
                    <div class="tab active" onclick="setMethod('ship', this)">SHIP</div>
                    <div class="tab" onclick="setMethod('pickup', this)">PICK UP</div>
                    <div class="tab" onclick="setMethod('walkin', this)">WALK IN</div>
                </div>

                <div class="address-card">
                    <p style="color: var(--coral); font-weight: 900; font-size: 11px; letter-spacing: 1px;">SHIPPING TO:</p>
                    <p><strong><?= htmlspecialchars($order['name']) ?></strong></p>
                    <p><?= htmlspecialchars($order['address']) ?></p>
                    <p><?= htmlspecialchars($order['phone']) ?></p>
                </div>
                
                <p id="method-desc" style="font-size: 12px; color: #888; margin-top: 15px; font-style: italic;">* Standard shipping fee applies.</p>
            </div>
        </div>

        <!-- RIGHT: SUMMARY -->
        <div class="summary-side">
            <div class="summary-panel">
                <h2>your bag</h2>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px;">
                    <span>Subtotal</span>
                    <span>₱<?= number_format($order['total_price'], 2) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px;">
                    <span>Shipping</span>
                    <span id="sum-ship">₱100.00</span>
                </div>
                
                <div class="total-row">
                    <span>Total Amount</span>
                    <span id="sum-total">₱0.00</span>
                </div>

                <div class="dp-box">
                    <span style="font-size: 10px; font-weight: 700; opacity: 0.8;">PAY 50% DOWNPAYMENT NOW</span>
                    <span class="dp-amount" id="sum-dp">₱0.00</span>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 30px; align-items: center;">
                    <img src="<?= $thumb ?>" alt="Preview" style="width: 70px; height: 90px; object-fit: cover; border-radius: 8px; border: 1px solid var(--peach);">
                    <div style="font-size: 12px;">
                        <div style="font-weight: 900; font-size: 14px; color: var(--coral);"><?= $order['order_type'] ?></div>
                        <div style="color: #888;">Order ID: #<?= $order['order_id'] ?></div>
                    </div>
                </div>

                <form action="payment_test.php" method="POST">
                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                    <input type="hidden" id="final-method" name="shipping_method" value="ship">
                    <input type="hidden" id="final-dp" name="dp_amount">
                    <button type="submit" class="btn-confirm">Continue to Payment</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const subtotal = <?= $order['total_price'] ?>;
        
        function setMethod(method, el) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
            
            let shipFee = (method === 'ship') ? 100 : 0;
            let totalVal = subtotal + shipFee;
            let dp = (subtotal * 0.5) + shipFee;
            
            const descs = {
                'ship': '* Standard shipping fee of ₱100.00. 50% DP required.',
                'pickup': '* Self-pickup. No shipping fee. 50% DP required.',
                'walkin': '* Pick up at our Las Piñas branch. 50% DP required.'
            };
            
            document.getElementById('method-desc').innerText = descs[method];
            document.getElementById('sum-ship').innerText = "₱" + shipFee.toFixed(2);
            document.getElementById('sum-total').innerText = "₱" + totalVal.toFixed(2);
            document.getElementById('sum-dp').innerText = "₱" + dp.toFixed(2);
            
            document.getElementById('final-method').value = method;
            document.getElementById('final-dp').value = dp;
        }

        setMethod('ship', document.querySelector('.tab.active'));
    </script>

</body>
</html>