<?php
session_start();
require_once 'db_connect.php';

// Helper function para sa Custom Bouquet Colors (kinuha from save_order)
function getFlowerColor($filename, $flower_name) {
    $base = strtolower(trim($flower_name));
    preg_match('/(\d+)\.png$/i', $filename, $matches);
    $num = isset($matches[1]) ? intval($matches[1]) - 1 : 0;
    $f_colors = [
        'tulip' => ["Red","Pink","Orange","Yellow","Purple","Blue","Green","White"],
        'rose' => ["Red","Pink","Orange","Yellow","Purple","Blue","Green","White","Black"],
        'small sunflower' => ["Yellow","Pink","Purple","Red"],
        'big sunflower' => ["Yellow","Pink","Purple","Red"],
        'lily' => ["Red","Pink","Yellow","Orange","White"],
        'calla lily' => ["Magenta","Maroon","Orange","Yellow","White"],
        'spider lily' => ["Red","Blue","Purple","Pink","Black"],
        'poppy' => ["Red","Orange","Yellow","Pink","White"],
        'iris' => ["Purple","Pink","Orange","Yellow"],
        'cornflower' => ["Blue","Purple","Pink","White Pink","White Purple"], 
        'carnation' => ["Red","Orange","Yellow","Pink","Purple","Green","White"],
        'hyacinth' => ["Red","Yellow","Pink","Purple","White"],
        'hydrangea' => ["Blue","Pink","Purple","White","Orange"],
        'fuchsia' => ["Red","Pink","Purple"],
        'thistle' => ["Pink","Purple","Blue"]
    ];
    if (isset($f_colors[$base]) && isset($f_colors[$base][$num])) {
        $color = $f_colors[$base][$num];
        if (strtolower($color) == 'white pink') return 'Pink';
        if (strtolower($color) == 'white purple') return 'Purple';
        return $color;
    }
    return 'Red'; 
}

// Function para ma-process ang Checkout papunta sa My Orders
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout_selected'])) {
    if (empty($_SESSION['cart']) || !isset($_SESSION['user_logged_in']) || empty($_POST['selected_items'])) {
        header("Location: cart.php");
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $selected_ids = $_POST['selected_items'];

    $regular_total = 0;
    $regular_items = [];
    $regular_thumbnail = "";
    $regular_ids = [];
    $custom_ids = [];

    // Paghiwalayin ang Custom Items at Regular Items
    foreach ($selected_ids as $id) {
        if (strpos($id, 'custom_') === 0) {
            $custom_ids[] = $id;
        } else {
            $regular_ids[] = $id;
            if (isset($_SESSION['cart'][$id])) {
                $data = $_SESSION['cart'][$id];
                $qty = is_array($data) ? $data['qty'] : $data;
                $res = $conn->query("SELECT * FROM products WHERE product_id = $id");
                if ($row = $res->fetch_assoc()) {
                    $regular_total += $row['price'] * $qty;
                    $regular_items[] = ['name' => $row['name'], 'qty' => $qty];
                    if (empty($regular_thumbnail)) {
                        $regular_thumbnail = "uploads/" . $row['image'];
                    }
                }
            }
        }
    }

    // 1. PROCESS REGULAR ITEMS (Diretso To Pay)
    if ($regular_total > 0) {
        $items_json = json_encode($regular_items);
        $order_type = "Cart Items (" . count($regular_ids) . ")";

        $stmt = $conn->prepare("INSERT INTO orders (user_id, order_type, total_price, status, payment_status, custom_image, items_json) VALUES (?, ?, ?, 'To Pay', 'To Pay', ?, ?)");
        $stmt->bind_param("isdss", $user_id, $order_type, $regular_total, $regular_thumbnail, $items_json);

        if ($stmt->execute()) {
            $order_id = $conn->insert_id;
            foreach ($regular_ids as $id) {
                if (isset($_SESSION['cart'][$id])) {
                    $data = $_SESSION['cart'][$id];
                    $qty = is_array($data) ? $data['qty'] : $data;
                    $res = $conn->query("SELECT price FROM products WHERE product_id = $id");
                    if ($row = $res->fetch_assoc()) {
                        $p = $row['price'];
                        $conn->query("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES ($order_id, $id, $qty, $p)");
                        $conn->query("UPDATE products SET stock = stock - $qty WHERE product_id = $id");
                    }
                    unset($_SESSION['cart'][$id]);
                }
            }
        }
    }

    // 2. PROCESS CUSTOM ITEMS (Gagawin as Pending at babawasan ang inventory ng materials)
    foreach ($custom_ids as $cid) {
        if (isset($_SESSION['cart'][$cid])) {
            $c_data = $_SESSION['cart'][$cid];
            $order_type = $c_data['order_type'];
            $size = $c_data['size'];
            $custom_image = $c_data['image'];
            $items_json = $c_data['items_json'];
            
            $query = "INSERT INTO orders (user_id, order_type, size, total_price, custom_image, items_json, status) VALUES (?, ?, ?, 0, ?, ?, 'Pending')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("issss", $user_id, $order_type, $size, $custom_image, $items_json);
            
            if ($stmt->execute()) {
    $order_id = $conn->insert_id;
    
    // SAFETY NET PARA SA ORDER_DETAILS
    try {
        $conn->query("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES ($order_id, NULL, 1, 0)");
    } catch (Exception $e) {
        try {
            $conn->query("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES ($order_id, 0, 1, 0)");
        } catch (Exception $e2) {
        }
    }

                // MATERIALS DEDUCTION LOGIC
                if ($order_type === 'Custom Bouquet') {
                    $materials_to_deduct = [];
                    $base_size = '';
                    if (stripos($size, 'Small') !== false) $base_size = 'Small';
                    elseif (stripos($size, 'Medium') !== false) $base_size = 'Medium';
                    elseif (stripos($size, 'Large') !== false) $base_size = 'Large';

                    if ($base_size != '') {
                        $req = $conn->query("SELECT material_name, quantity_needed FROM flower_materials WHERE element_name = '$base_size'");
                        if ($req) {
                            while($r = $req->fetch_assoc()) {
                                $materials_to_deduct[$r['material_name']] = ($materials_to_deduct[$r['material_name']] ?? 0) + $r['quantity_needed'];
                            }
                        }
                    }

                    $items_array = json_decode($items_json, true);
                    if (is_array($items_array)) {
                        foreach ($items_array as $item) {
                            if (!isset($item['name']) || (isset($item['type']) && $item['type'] === 'deduction_data')) continue;
                            if (isset($item['id']) && $item['id'] === 'custom_size_info') continue;
                            
                            $fname = strtolower(trim($item['name']));
                            if ($fname == 'lily of valley') $fname = 'lily of the valley';
                            if ($fname == 'eucalyptus') $fname = 'eucalyptus leaf';
                            if ($fname == 'gardenia') $fname = 'gardenia leaf';
                            if ($fname == "babys breath") $fname = "baby's breath";

                            $orig_name = $conn->real_escape_string($item['name']);
                            $conn->query("INSERT INTO order_items (order_id, flower_name, quantity) VALUES ($order_id, '$orig_name', 1)");

                            $esc_name = $conn->real_escape_string($fname);
                            $res_map = $conn->query("SELECT material_name, quantity_needed FROM flower_materials WHERE LOWER(element_name) = '$esc_name'");
                            
                            if ($res_map) {
                                while ($map_row = $res_map->fetch_assoc()) {
                                    $mat = $map_row['material_name'];
                                    if ($mat === 'Fuzzy Wire [COLOR]') {
                                        $color = getFlowerColor($item['file'] ?? '', $item['name']);
                                        $mat = "Fuzzy Wire " . ucfirst(strtolower($color));
                                    }
                                    $materials_to_deduct[$mat] = ($materials_to_deduct[$mat] ?? 0) + $map_row['quantity_needed'];
                                }
                            }
                        }
                    }

                    foreach ($materials_to_deduct as $mat_name => $total_qty) {
                        if ($total_qty > 0) {
                            $sql_deduct = "UPDATE materials SET stock = stock - ? WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))";
                            $stmt_deduct = $conn->prepare($sql_deduct);
                            $stmt_deduct->bind_param("ds", $total_qty, $mat_name);
                            $stmt_deduct->execute();
                        }
                    }
                } elseif ($order_type === 'Custom T-Shirt') {
                    $shirt_color_name = $c_data['shirt_color'] ?? 'White';
                    $c_kw = $conn->real_escape_string(strtolower(trim($shirt_color_name)));
                    $ink_ml = rand(8, 15);
                    $conn->query("UPDATE materials SET stock = stock - 1 WHERE (LOWER(category) = 'shirt' OR LOWER(name) LIKE '%shirt%') AND (LOWER(name) = '$c_kw' OR LOWER(name) LIKE '$c_kw %' OR LOWER(name) LIKE '% $c_kw' OR LOWER(name) LIKE '% $c_kw %') LIMIT 1");
                    $conn->query("UPDATE materials SET stock = stock - $ink_ml WHERE LOWER(name) LIKE '%cmyk ink%' LIMIT 1");
                }
                
                unset($_SESSION['cart'][$cid]);
            }
        }
    }

    header("Location: my_orders.php?status=All");
    exit();
}

// I-setup ang cart array sa session kung wala pa
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// Function para tanggalin ang isang item
if (isset($_GET['remove'])) {
    $id = $_GET['remove'];
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
    <title>Your Cart | Chub's Handicrafts</title>
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

        /* NAV NOTIFICATION BADGE */
        .nav-badge {
            background: #ff4757;
            color: white;
            padding: 2px 6px;
            border-radius: 50px;
            font-size: 8px;
            font-weight: 900;
            margin-left: 3px;
            vertical-align: super;
            box-shadow: 0 0 5px rgba(255, 71, 87, 0.5);
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
            margin-bottom: 30px;
        }

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

        /* CHECKBOX STYLING */
        input[type="checkbox"] {
            accent-color: var(--coral);
            width: 18px;
            height: 18px;
            cursor: pointer;
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
            border: none;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 900;
            font-size: 12px;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 8px 25px rgba(241, 137, 115, 0.3);
            font-family: inherit;
        }
        .btn-checkout:hover { background: #e07661; transform: translateY(-3px); }
        .btn-checkout:disabled { background: #ccc; cursor: not-allowed; box-shadow: none; transform: none; }

        .btn-clear { color: #bbb; text-decoration: none; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .btn-clear:hover { color: #ff4757; }
        
        .remove-icon { color: #ff4757; text-decoration: none; font-weight: bold; font-size: 18px; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

<?php include 'notifications.php'; ?>
<?php include 'navbar.php'; ?>

<div class="container">
    <h2>Your Cart</h2>

    <div class="cart-box">
        <?php if (empty($_SESSION['cart'])): ?>
            <div style="text-align: center; padding: 50px; color: #aaa;">
                Wala pang laman ang cart mo. Bloom your day by adding some flowers! <br><br>
                <a href="shop.php" style="color: var(--coral); font-weight: 900; text-decoration: none;">Go to Shop</a>
            </div>
        <?php else: ?>
            <form action="cart.php" method="POST" id="cartForm">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">
                                <input type="checkbox" id="selectAll" checked>
                            </th>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($_SESSION['cart'] as $id => $data) {
                            
                            // CHECK KUNG CUSTOM ITEM
                            if (strpos($id, 'custom_') === 0) {
                                $subtotal = 0; // Price will be provided by admin
                                $qty = 1;
                                
                                $img_raw = $data['image'];
                                $is_multi = (isset($img_raw[0]) && $img_raw[0] === '{');
                                $decoded_img = $is_multi ? json_decode($img_raw, true) : $img_raw;
                                $img_src = $is_multi ? ($decoded_img['f'] ?? '') : $img_raw;

                                ?>
                                <tr>
                                    <td style="text-align: center;">
                                        <input type="checkbox" name="selected_items[]" value="<?= $id ?>" class="item-checkbox" data-subtotal="0" checked>
                                    </td>
                                    <td>
                                        <div class="prod-display">
                                            <img src="<?= $img_src ?>" class="prod-img" alt="Custom Product">
                                            <div>
                                                <span class="prod-name"><?= htmlspecialchars($data['order_type']) ?></span><br>
                                                <span style="font-size: 10px; color: #888; text-transform: uppercase;"><?= htmlspecialchars($data['size']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="price-text" style="color: #aaa; font-style: italic;">To be priced</td>
                                    <td><span class="qty-badge"><?= $qty ?></span></td>
                                    <td class="subtotal-text" style="color: #aaa; font-size: 14px;">Pending Price</td>
                                    <td><a href="?remove=<?= $id ?>" class="remove-icon" title="Remove Item">&times;</a></td>
                                </tr>
                                <?php
                            } 
                            // REGULAR ITEMS
                            else {
                                $qty = is_array($data) ? $data['qty'] : $data;
                                $sql = "SELECT * FROM products WHERE product_id = $id";
                                $result = $conn->query($sql);
                                
                                if ($result->num_rows > 0) {
                                    $row = $result->fetch_assoc();
                                    $subtotal = $row['price'] * $qty;
                                    $img_src = "uploads/" . $row['image']; 
                                    ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <input type="checkbox" name="selected_items[]" value="<?= $id ?>" class="item-checkbox" data-subtotal="<?= $subtotal ?>" checked>
                                        </td>
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
                        }
                        ?>
                        <tr class="total-row">
                            <td colspan="4" class="total-label">Selected Total:</td>
                            <td class="subtotal-text" style="font-size: 22px;" id="grandTotalDisplay">₱0.00</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <div class="actions">
                    <a href="cart.php?action=clear" class="btn-clear" onclick="return confirm('Clear your cart?')">Clear Entire Cart</a>
                    <button type="submit" name="checkout_selected" class="btn-checkout" id="checkoutBtn">Checkout / Request Selected</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.item-checkbox');
        const grandTotalDisplay = document.getElementById('grandTotalDisplay');
        const checkoutBtn = document.getElementById('checkoutBtn');

        // Function to recalculate total dynamically
        function calculateTotal() {
            let total = 0;
            let checkedCount = 0;
            
            checkboxes.forEach(cb => {
                if (cb.checked) {
                    total += parseFloat(cb.getAttribute('data-subtotal'));
                    checkedCount++;
                }
            });

            // Format number with commas
            grandTotalDisplay.innerText = "₱" + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            // Disable button if nothing is selected
            checkoutBtn.disabled = (checkedCount === 0);
        }

        // Event listener for "Select All" checkbox
        if(selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => {
                    cb.checked = this.checked;
                });
                calculateTotal();
            });
        }

        // Event listeners for individual checkboxes
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                // If one gets unchecked, uncheck the "Select All" box
                if (!this.checked) {
                    selectAll.checked = false;
                } else {
                    // Check if all are checked to re-check "Select All"
                    const allChecked = Array.from(checkboxes).every(c => c.checked);
                    selectAll.checked = allChecked;
                }
                calculateTotal();
            });
        });

        // Initial calculation on page load
        if(checkboxes.length > 0) {
            calculateTotal();
        }
    });
</script>

</body>
</html>