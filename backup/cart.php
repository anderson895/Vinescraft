<?php
session_start();
require_once 'db_connect.php';

// Function para ma-process ang Checkout papunta sa My Orders (To Pay)
if (isset($_GET['action']) && $_GET['action'] == 'checkout') {
    if (empty($_SESSION['cart']) || !isset($_SESSION['user_logged_in'])) {
        header("Location: shop.php");
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $total_amount = 0;
    $items_array = [];
    $thumbnail = "";

    foreach ($_SESSION['cart'] as $id => $data) {
        $qty = is_array($data) ? $data['qty'] : $data;
        $res = $conn->query("SELECT * FROM products WHERE product_id = $id");
        if ($row = $res->fetch_assoc()) {
            $total_amount += $row['price'] * $qty;
            $items_array[] = ['name' => $row['name'], 'qty' => $qty];
            if (empty($thumbnail)) {
                $thumbnail = "uploads/" . $row['image'];
            }
        }
    }

    $items_json = json_encode($items_array);
    $order_type = "Cart Items (" . count($_SESSION['cart']) . ")";

    // Gawin agad 'To Pay' ang status
    $stmt = $conn->prepare("INSERT INTO orders (user_id, order_type, total_price, status, payment_status, custom_image, items_json) VALUES (?, ?, ?, 'To Pay', 'To Pay', ?, ?)");
    $stmt->bind_param("isdss", $user_id, $order_type, $total_amount, $thumbnail, $items_json);

    if ($stmt->execute()) {
        $order_id = $conn->insert_id;
        
        // Save sa order_details para gumana yung "Amount Sold" sa product_view.php AT mag-deduct sa products table
        foreach ($_SESSION['cart'] as $id => $data) {
            $qty = is_array($data) ? $data['qty'] : $data;
            $res = $conn->query("SELECT price FROM products WHERE product_id = $id");
            if ($row = $res->fetch_assoc()) {
                $p = $row['price'];
                // I-save ang order detail
                $conn->query("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES ($order_id, $id, $qty, $p)");
                
                // BAGO: I-deduct nang direkta ang biniling quantity mula sa stock ng products table
                $conn->query("UPDATE products SET stock = stock - $qty WHERE product_id = $id");
            }
        }

        unset($_SESSION['cart']);
        header("Location: my_orders.php?status=All");
        exit();
    }
}

// I-setup ang cart array sa session kung wala pa
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// Function para tanggalin ang isang item
if (isset($_GET['remove'])) {
    $id = intval($_GET['remove']);
    unset($_SESSION['cart'][$id]);
    header("Location: cart.php");
    exit();
}

// Function para ma-empty yung cart
if (isset($_GET['action']) && $_GET['action'] == 'clear') {
    unset($_SESSION['cart']);
    header("Location: cart.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart | Vinescraft</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
        
        :root {
            --coral: #f18973;
            --peach: #fce0d8;
            --text: #444;
            --bg: #fffafb;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--bg);
            margin: 0;
            color: var(--text);
        }

        /* NAVIGATION BAR */
        .top-nav { 
            background: var(--coral); 
            padding: 15px 5%; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            color: white; 
        }
        .top-nav a { 
            color: white; 
            text-decoration: none; 
            font-size: 11px; 
            font-weight: 900; 
            text-transform: uppercase; 
            margin-left: 20px; 
            letter-spacing: 1px;
        }

        .container {
            max-width: 1000px;
            margin: 60px auto;
            padding: 0 20px;
        }

        h2 {
            font-size: 32px;
            font-weight: 900;
            color: var(--coral);
            text-transform: lowercase;
            margin-bottom: 30px;
        }
        h2::after { content: '.'; }

        .cart-box {
            background: white;
            padding: 30px;
            border-radius: 30px;
            border: 1px solid var(--peach);
            box-shadow: 0 15px 40px rgba(241, 137, 115, 0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        th {
            text-align: left;
            padding: 15px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #bbb;
            border-bottom: 2px solid var(--peach);
        }

        td {
            padding: 20px 15px;
            border-bottom: 1px solid #fff5f8;
            font-size: 14px;
            vertical-align: middle;
        }

        /* PRODUCT STYLING */
        .prod-display { display: flex; align-items: center; gap: 15px; }
        .prod-img { width: 60px; height: 60px; object-fit: cover; border-radius: 12px; border: 1px solid var(--peach); }
        .prod-name { font-weight: 900; color: var(--text); font-size: 15px; }

        .qty-badge { background: var(--peach); color: var(--coral); padding: 5px 12px; border-radius: 50px; font-weight: 900; font-size: 12px; }
        .price-text { font-weight: 700; color: var(--text); }
        .subtotal-text { font-weight: 900; color: var(--coral); font-size: 16px; }

        .total-row { background: #fffcfd; }
        .total-label { text-align: right; font-weight: 700; font-size: 12px; text-transform: uppercase; color: #bbb; }

        .actions { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; }

        .btn-checkout {
            background: var(--coral);
            color: white;
            padding: 18px 50px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 900;
            font-size: 12px;
            text-transform: uppercase;
            transition: 0.3s;
            box-shadow: 0 8px 25px rgba(241, 137, 115, 0.3);
        }
        .btn-checkout:hover { background: #e07661; transform: translateY(-3px); }

        .btn-clear { color: #bbb; text-decoration: none; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .btn-clear:hover { color: #ff4757; }
        
        .remove-icon { color: #ff4757; text-decoration: none; font-weight: bold; font-size: 18px; }
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

<div class="container">
    <h2>your cart.</h2>

    <div class="cart-box">
        <?php if (empty($_SESSION['cart'])): ?>
            <div style="text-align: center; padding: 50px; color: #aaa;">
                Wala pang laman ang cart mo. Bloom your day by adding some flowers! <br><br>
                <a href="shop.php" style="color: var(--coral); font-weight: 900; text-decoration: none;">Go to Shop</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_amount = 0;
                    foreach ($_SESSION['cart'] as $id => $data) {
                        $qty = is_array($data) ? $data['qty'] : $data;
                        
                        $sql = "SELECT * FROM products WHERE product_id = $id";
                        $result = $conn->query($sql);
                        
                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $subtotal = $row['price'] * $qty;
                            $total_amount += $subtotal;
                            
                            $img_src = "uploads/" . $row['image']; 
                            ?>
                            <tr>
                                <td>
                                    <div class="prod-display">
                                        <img src="<?= $img_src ?>" class="prod-img" alt="Product">
                                        <span class="prod-name"><?= htmlspecialchars($row['name']) ?></span>
                                    </div>
                                </td>
                                <td class="price-text">₱<?= number_format($row['price'], 2) ?></td>
                                <td><span class="qty-badge"><?= $qty ?></span></td>
                                <td class="subtotal-text">₱<?= number_format($subtotal, 2) ?></td>
                                <td><a href="?remove=<?= $id ?>" class="remove-icon" title="Remove Item">&times;</a></td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                    <tr class="total-row">
                        <td colspan="3" class="total-label">Grand Total:</td>
                        <td class="subtotal-text" style="font-size: 22px;">₱<?= number_format($total_amount, 2) ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <div class="actions">
                <a href="cart.php?action=clear" class="btn-clear" onclick="return confirm('Clear your cart?')">Clear Cart</a>
                <a href="cart.php?action=checkout" class="btn-checkout">Proceed to Checkout</a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>