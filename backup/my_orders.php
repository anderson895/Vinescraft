<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';

// --- BAGO: CANCEL ORDER LOGIC ---
if (isset($_POST['cancel_order']) && isset($_POST['order_id'])) {
    $cancel_id = intval($_POST['order_id']);
    // Update ang status sa 'Cancelled' para mawala sa active Order Management ng Admin
    $cancel_stmt = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE order_id = ? AND user_id = ? AND status IN ('Pending', 'To Pay')");
    $cancel_stmt->bind_param("ii", $cancel_id, $user_id);
    if ($cancel_stmt->execute()) {
        echo "<script>alert('Order cancelled successfully.'); window.location.href='my_orders.php';</script>";
        exit();
    }
}

// --- STEP 1: GET PRECISE NOTIFICATION COUNTS ---
$count_stmt = $conn->prepare("SELECT status, COUNT(*) as total FROM orders WHERE user_id = ? GROUP BY status");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_res = $count_stmt->get_result();

$counts = [
    'Pending' => 0, 'To Pay' => 0, 'Processing' => 0,
    'To Ship' => 0, 'To Receive' => 0, 'Completed' => 0
];
$total_all = 0;

while($c = $count_res->fetch_assoc()) {
    if (array_key_exists($c['status'], $counts)) {
        $counts[$c['status']] = $c['total'];
    }
    $total_all += $c['total'];
}

// --- STEP 2: FETCH ORDERS BASED ON FILTER ---
if ($status_filter === 'All') {
    // Ipakita ALL orders kasama completed at cancelled sa All tab
    $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_id DESC");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? AND status = ? ORDER BY order_id DESC");
    $stmt->bind_param("is", $user_id, $status_filter);
}
$stmt->execute();
$orders = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Vinescraft</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
        :root { --coral: #f18973; --peach: #fce0d8; --text-dark: #444; --grey-light: #f9f9f9; }
        body { font-family: 'Montserrat', sans-serif; background-color: #fff; margin: 0; color: var(--text-dark); }
        
        .top-nav { background: var(--coral); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; color: white; position: sticky; top: 0; z-index: 1000; }
        .top-nav a { color: white; text-decoration: none; font-size: 10px; font-weight: 900; text-transform: uppercase; margin-left: 15px; letter-spacing: 1px; transition: 0.3s; }
        .top-nav a:hover { opacity: 0.8; }

        .page-header { text-align: center; margin: 30px 0 20px; }
        .page-header h1 { font-size: 42px; font-weight: 900; color: var(--coral); margin: 0; text-transform: lowercase; }

        .tabs-wrapper { display: flex; justify-content: center; gap: 10px; margin: 30px 0; flex-wrap: wrap; }
        .tab-link { 
            position: relative;
            padding: 12px 20px; border: 2px solid var(--peach); border-radius: 50px; 
            text-decoration: none; color: var(--coral); font-size: 10px; font-weight: 700; 
            transition: 0.3s; text-transform: uppercase;
        }
        .tab-link.active { background: var(--coral); color: white; border-color: var(--coral); }
        
        .badge {
            position: absolute; top: -8px; right: -5px; background: #ff4757; color: white;
            font-size: 9px; width: 18px; height: 18px; border-radius: 50%;
            display: flex; justify-content: center; align-items: center;
            border: 2px solid white; font-weight: 900;
        }

        .order-container { max-width: 900px; margin: 0 auto; padding: 0 20px 80px; }
        .order-card { 
            background: white; border: 1px solid var(--peach); border-radius: 20px; 
            padding: 20px; display: flex; gap: 20px; margin-bottom: 20px; 
            align-items: flex-start; transition: 0.3s; 
        }
        .order-preview { width: 100px; height: 120px; border-radius: 12px; object-fit: cover; cursor: pointer; border: 1px solid var(--grey-light); }
        .order-info { flex-grow: 1; }
        .order-info h3 { margin: 0; color: var(--coral); font-size: 16px; text-transform: uppercase; }
        
        .materials-receipt { margin: 10px 0; padding: 10px; background: var(--grey-light); border-radius: 12px; font-size: 11px; border: 1px solid var(--peach); }
        .inclusion-row { display: flex; justify-content: space-between; padding: 2px 0; border-bottom: 1px dashed #eee; }
        
        .btn-action { background: var(--coral); color: white; border: none; padding: 12px; border-radius: 50px; cursor: pointer; font-weight: 900; font-size: 11px; text-transform: uppercase; width: 100%; margin-top: 10px; transition: 0.3s; }
        .btn-action:hover { opacity: 0.8; }
        .balance-box { background: #fff5f8; padding: 15px; border-radius: 15px; border: 1px dashed var(--coral); margin-top: 15px; }
        
        .modal { display: none; position: fixed; z-index: 1000; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 90%; max-width: 700px; position: relative; }
        .grid-view { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-top: 20px; }
        .grid-item img { width: 100%; border-radius: 10px; border: 1px solid var(--peach); }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

<nav class="top-nav">
    <div style="font-weight: 900; font-size: 18px; text-transform: lowercase;">vinescraft.</div>
    <div>
        <a href="index.php">Home</a>
        <a href="shop.php">Shop</a>
        <a href="customizer.php">Custom Bouquet</a>
        <a href="customize_tshirt.php">Custom Shirt</a>
        <a href="about.php">Our Story</a>
        <a href="cart.php">Cart</a>
        <a href="chat.php">Chat</a>
        <a href="my_orders.php">Orders</a>
        
        <?php if(!empty($_SESSION['user_logged_in'])): ?>
            <a href="profile.php">Account</a>
            <a href="logout.php" style="font-size: 9px; opacity: 0.6; margin-left: 5px;">(Logout)</a>
        <?php else: ?>
            <a href="login.php">Account</a>
        <?php endif; ?>
    </div>
</nav>

<div class="page-header"><h1>my orders.</h1></div>

<div class="tabs-wrapper">
    <a href="?status=All" class="tab-link <?= $status_filter == 'All' ? 'active' : '' ?>">
        All <?php if($total_all > 0) echo "<span class='badge'>$total_all</span>"; ?>
    </a>

    <?php 
    $stages = ['Pending', 'To Pay', 'Processing', 'To Ship', 'To Receive', 'Completed']; 
    foreach($stages as $s): 
        $display_badge = $counts[$s];
    ?>
        <a href="?status=<?= $s ?>" class="tab-link <?= $status_filter == $s ? 'active' : '' ?>">
            <?= $s ?>
            <?php if($display_badge > 0) echo "<span class='badge'>$display_badge</span>"; ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="order-container">
    <?php if($orders->num_rows > 0): while($row = $orders->fetch_assoc()): ?>
        <?php 
            $img_raw = $row['custom_image'];
            $is_multi = (isset($img_raw[0]) && $img_raw[0] === '{');
            $decoded_img = $is_multi ? json_decode($img_raw, true) : $img_raw;
            $thumbnail = $is_multi ? ($decoded_img['f'] ?? '') : $img_raw;
        ?>
        <div class="order-card">
            <img src="<?= $thumbnail ?>" class="order-preview" onclick='viewDesign(<?= json_encode($decoded_img) ?>)'>
            <div class="order-info">
                <div style="display:flex; justify-content:space-between;">
                    <h3><?= htmlspecialchars($row['order_type']) ?></h3>
                    <div style="display:flex; gap: 5px;">
                        <span style="font-size: 8px; font-weight: 900; color: #fff; background: var(--coral); padding: 3px 8px; border-radius: 10px;"><?= strtoupper($row['status']) ?></span>
                        <span style="font-size: 8px; font-weight: 900; color: var(--coral); background: var(--peach); padding: 3px 8px; border-radius: 10px;"><?= $row['payment_status'] ?></span>
                    </div>
                </div>
                <p style="font-size:10px; color:#aaa;">Order ID: #<?= $row['order_id'] ?></p>

                <div class="materials-receipt">
                    <span style="font-weight:900; color:var(--coral); font-size:9px; text-transform:uppercase;">Inclusions:</span>
                    <?php 
                    if (!empty($row['items_json'])) {
                        $items = json_decode($row['items_json'], true);
                        if (is_array($items)) {
                            $summary = [];
                            foreach ($items as $it) { $n = $it['name'] ?? 'Item'; $summary[$n] = ($summary[$n] ?? 0) + 1; }
                            foreach ($summary as $n => $q) {
                                echo "<div class='inclusion-row'><span>$n</span><span style='font-weight:900; color:var(--coral);'>x$q</span></div>";
                            }
                        }
                    } else { echo "<div class='inclusion-row'>Standard Arrangement</div>"; }
                    ?>
                </div>

                <div style="font-size: 18px; font-weight: 900;">
                    <?= $row['status'] == 'Pending' ? '<span style="color:#ccc; font-size:12px;">Pricing...</span>' : '₱' . number_format($row['total_price'], 2) ?>
                </div>

                <!-- BAGO: Buttons na may Cancel Logic -->
                <?php if($row['status'] == 'Pending'): ?>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this pending request?');">
                        <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
                        <button type="submit" name="cancel_order" class="btn-action" style="background: white; color: #ff4757; border: 1px solid #ff4757; margin-top: 10px;">Cancel Request</button>
                    </form>

                <?php elseif($row['status'] == 'To Pay'): ?>
                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <button class="btn-action" style="margin-top: 0; flex: 1;" onclick="window.location.href='checkout.php?id=<?= $row['order_id'] ?>'">Pay 50% DP</button>
                        <form method="POST" style="flex: 1;" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                            <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
                            <button type="submit" name="cancel_order" class="btn-action" style="background: white; color: #ff4757; border: 1px solid #ff4757; margin-top: 0; width: 100%;">Cancel Order</button>
                        </form>
                    </div>

                <?php elseif($row['status'] == 'To Receive' && $row['payment_status'] == 'Paid 50% DP'): ?>
                    <div class="balance-box">
                        <p style="font-size: 10px; margin-bottom: 8px;">Order ready! Please pay remaining 50% balance.</p>
                        <button class="btn-action" onclick="window.location.href='payment_test.php?id=<?= $row['order_id'] ?>&type=balance'">Pay Balance (₱<?= number_format($row['total_price'] * 0.5, 2) ?>)</button>
                    </div>

                <?php elseif($row['status'] == 'Completed'): ?>
                    <div style="margin-top:10px; color:#28a745; font-size:10px; font-weight:900;">✓ FULLY PAID & RECEIVED</div>
                
                <?php elseif($row['status'] == 'Cancelled'): ?>
                    <div style="margin-top:10px; color:#ff4757; font-size:10px; font-weight:900;">✖ ORDER CANCELLED</div>
                <?php endif; ?>
                <!-- END BAGO -->

            </div>
        </div>
    <?php endwhile; else: ?>
        <p style="text-align:center; color:#ccc; padding: 40px;">No orders found.</p>
    <?php endif; ?>
</div>

<div id="designModal" class="modal" onclick="this.style.display='none'">
    <div class="modal-content" onclick="event.stopPropagation()">
        <h2 style="margin-top: 0; color: var(--coral); font-size: 14px; text-transform: uppercase;">Design Details</h2>
        <div id="designGrid" class="grid-view"></div>
    </div>
</div>

<script>
    function viewDesign(data) {
        const grid = document.getElementById('designGrid');
        grid.innerHTML = ""; 
        if (typeof data === 'object' && data !== null) {
            const labels = { f: 'Front', b: 'Back', ls: 'Left', rs: 'Right' };
            for (const [key, src] of Object.entries(data)) {
                grid.innerHTML += `<div class="grid-item"><img src="${src}"><div style="font-size:9px; font-weight:900; margin-top:5px; text-align:center;">${labels[key] || key}</div></div>`;
            }
        } else {
            grid.innerHTML = `<div class="grid-item" style="grid-column: 1/-1;"><img src="${data}" style="max-height:400px; width:auto; display:block; margin:auto;"></div>`;
        }
        document.getElementById('designModal').style.display = 'flex';
    }
</script>
</body>
</html>