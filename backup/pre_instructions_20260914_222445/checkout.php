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
$stmt = $conn->prepare("SELECT o.*, u.name, u.phone, u.address 
                        FROM orders o 
                        JOIN users u ON o.user_id = u.user_id 
                        WHERE o.order_id = ? AND o.user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) { die("Order not found."); }

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
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="color: var(--coral); font-weight: 900; font-size: 11px; letter-spacing: 1px;">SHIPPING TO:</span>
                        <a href="profile.php" style="font-size: 9px; color: #888; text-decoration: none; border: 1px solid #ddd; padding: 4px 10px; border-radius: 50px; font-weight: bold; transition: 0.3s;">Manage / Add Address</a>
                    </div>
                    
                    <p style="margin: 5px 0;"><strong><?= htmlspecialchars($order['name']) ?></strong> | <?= htmlspecialchars($order['phone']) ?></p>
                    
                    <?php if(empty($addresses)): ?>
                        <div style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 10px; font-size: 12px; margin-top: 15px; border: 1px dashed #ef9a9a;">
                            <strong>No delivery address found.</strong><br>
                            Please <a href="profile.php" style="color: #c62828; font-weight: 900; text-decoration: underline;">click here to add an address</a> in your profile to continue.
                        </div>
                    <?php else: ?>
                        <select id="address-select" onchange="updateSelectedAddress()" style="width:100%; padding:12px; margin-top:10px; border-radius:10px; border:1px solid var(--peach); font-family:inherit; font-size: 13px; outline: none; background: white; color: var(--text-dark); cursor: pointer;">
                            <?php foreach($addresses as $addr): ?>
                                <option value="<?= htmlspecialchars($addr['full_address']) ?>">
                                    <?= htmlspecialchars($addr['label']) ?>: <?= htmlspecialchars($addr['full_address']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
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
                        <div style="font-weight: 900; font-size: 14px; color: var(--coral);"><?= htmlspecialchars($order['order_type']) ?></div>
                        <div style="color: #888;">Order ID: #<?= htmlspecialchars($order['order_id']) ?></div>
                    </div>
                </div>

                <form action="payment_test.php" method="POST">
                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                    <input type="hidden" id="final-method" name="shipping_method" value="ship">
                    <input type="hidden" id="final-dp" name="dp_amount">
                    <button type="submit" id="btn-confirm" class="btn-confirm" <?= empty($addresses) ? 'disabled' : '' ?>>
                        Continue to Payment
                    </button>
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
                'walkin': '* Pick up at our branch. 50% DP required.'
            };
            
            document.getElementById('method-desc').innerText = descs[method];
            document.getElementById('sum-ship').innerText = "₱" + shipFee.toFixed(2);
            document.getElementById('sum-total').innerText = "₱" + totalVal.toFixed(2);
            document.getElementById('sum-dp').innerText = "₱" + dp.toFixed(2);
            
            document.getElementById('final-method').value = method;
            document.getElementById('final-dp').value = dp;

            // If pick up or walk in, allow payment even without an address
            const btnConfirm = document.getElementById('btn-confirm');
            <?php if(empty($addresses)): ?>
                if(method === 'ship') {
                    btnConfirm.disabled = true;
                } else {
                    btnConfirm.disabled = false;
                }
            <?php endif; ?>
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

        // Run functions on load
        document.addEventListener('DOMContentLoaded', () => {
            setMethod('ship', document.querySelector('.tab.active'));
            
            // Save the default address immediately if it exists
            if(document.getElementById('address-select')) {
                updateSelectedAddress();
            }
        });
    </script>

</body>
</html>