<?php
session_start();
require_once 'db_connect.php';

// PROTECTION: Admin session check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// ----------------------------------------------------------------------
// AUTO-CREATE & POPULATE RECIPES TABLE (Tatakbo lang 'to kung wala pang table)
// ----------------------------------------------------------------------
$check_table = $conn->query("SHOW TABLES LIKE 'bouquet_recipes'");
if ($check_table->num_rows == 0) {
    $conn->query("CREATE TABLE bouquet_recipes (
        recipe_id INT AUTO_INCREMENT PRIMARY KEY,
        element_name VARCHAR(100),
        material_name VARCHAR(100),
        qty DECIMAL(10, 2)
    )");

    $recipes = [
        // Wrappers
        "('Small', 'Wrapper', 2), ('Small', 'Floral Foam', 1), ('Small', 'Ribbon', 0.01)",
        "('Medium', 'Wrapper', 3), ('Medium', 'Floral Foam', 1), ('Medium', 'Ribbon', 0.03)",
        "('Large', 'Wrapper', 8), ('Large', 'Floral Foam', 1), ('Large', 'Ribbon', 0.06)",
        
        // Flowers
        "('Tulip', 'Fuzzy Wire [COLOR]', 8), ('Tulip', 'Fuzzy Wire Green', 4), ('Tulip', 'Stem Wire', 1), ('Tulip', 'Floral Tape', 0.01), ('Tulip', 'Glue Stick', 0.12)",
        "('Rose', 'Fuzzy Wire [COLOR]', 30), ('Rose', 'Fuzzy Wire Green', 9), ('Rose', 'Stem Wire', 1)",
        "('Small Sunflower', 'Fuzzy Wire [COLOR]', 8), ('Small Sunflower', 'Fuzzy Wire Brown', 1), ('Small Sunflower', 'Fuzzy Wire Green', 3)",
        "('Big Sunflower', 'Fuzzy Wire [COLOR]', 44), ('Big Sunflower', 'Fuzzy Wire Brown', 8), ('Big Sunflower', 'Fuzzy Wire Green', 31)",
        "('Lily', 'Fuzzy Wire [COLOR]', 19), ('Lily', 'Fuzzy Wire Green', 9), ('Lily', 'Stem Wire', 1), ('Lily', 'Floral Tape', 0.01)",
        "('Calla lily', 'Fuzzy Wire [COLOR]', 8), ('Calla lily', 'Fuzzy Wire Yellow', 1), ('Calla lily', 'Fuzzy Wire Green', 4), ('Calla lily', 'Stem Wire', 1), ('Calla lily', 'Floral Tape', 0.01)",
        "('Spider lily', 'Fuzzy Wire [COLOR]', 28), ('Spider lily', 'Fuzzy Wire Green', 6), ('Spider lily', 'Stem Wire', 1), ('Spider lily', 'Floral Tape', 0.01), ('Spider lily', 'Glue Stick', 0.12)",
        "('Poppy', 'Fuzzy Wire [COLOR]', 19), ('Poppy', 'Fuzzy Wire Green', 2), ('Poppy', 'Floral Tape', 0.01)",
        "('Iris', 'Fuzzy Wire [COLOR]', 6), ('Iris', 'Fuzzy Wire Green', 3), ('Iris', 'Stem Wire', 1), ('Iris', 'Floral Tape', 0.01)",
        "('Cornflower', 'Fuzzy Wire [COLOR]', 6), ('Cornflower', 'Fuzzy Wire Black', 1), ('Cornflower', 'Fuzzy Wire Green', 4), ('Cornflower', 'Stem Wire', 1), ('Cornflower', 'Floral Tape', 0.01), ('Cornflower', 'Glue Stick', 0.12)",
        "('Carnation', 'Fuzzy Wire [COLOR]', 24), ('Carnation', 'Fuzzy Wire Green', 11), ('Carnation', 'Stem Wire', 1), ('Carnation', 'Floral Tape', 0.01), ('Carnation', 'Glue Stick', 0.12)",
        "('Hyacinth', 'Fuzzy Wire [COLOR]', 40), ('Hyacinth', 'Fuzzy Wire Green', 6), ('Hyacinth', 'Stem Wire', 1), ('Hyacinth', 'Floral Tape', 0.01)",
        "('Hydrangea', 'Fuzzy Wire [COLOR]', 42), ('Hydrangea', 'Fuzzy Wire Green', 10), ('Hydrangea', 'Floral Tape', 1.5), ('Hydrangea', 'Glue Stick', 0.12)",
        "('Fuchsia', 'Fuzzy Wire [COLOR]', 21), ('Fuchsia', 'Fuzzy Wire Green', 6), ('Fuchsia', 'Stem Wire', 1), ('Fuchsia', 'Floral Tape', 0.01), ('Fuchsia', 'Glue Stick', 0.12)",
        "('Thistle', 'Fuzzy Wire [COLOR]', 10), ('Thistle', 'Fuzzy Wire Green', 5), ('Thistle', 'Stem Wire', 1), ('Thistle', 'Floral Tape', 0.01)",
        
        // Fillers & Leaves
        "('Daisy', 'Fuzzy Wire White', 2), ('Daisy', 'Fuzzy Wire Green', 1), ('Daisy', 'Fuzzy Wire Yellow', 1)",
        "('Lavender', 'Fuzzy Wire Purple', 2), ('Lavender', 'Fuzzy Wire Green', 1), ('Lavender', 'Stem Wire', 1)",
        "('Baby''s Breath', 'Fuzzy Wire White', 5), ('Baby''s Breath', 'Stem Wire', 1), ('Baby''s Breath', 'Floral Tape', 0.01)",
        "('Lily of the Valley', 'Fuzzy Wire White', 12), ('Lily of the Valley', 'Fuzzy Wire Green', 4)",
        "('Statice', 'Fuzzy Wire Purple', 15), ('Statice', 'Fuzzy Wire Green', 1), ('Statice', 'Floral Tape', 0.01)",
        "('Bell flower', 'Fuzzy Wire Purple', 7), ('Bell flower', 'Fuzzy Wire Green', 10)",
        "('Eucalyptus Leaf', 'Fuzzy Wire Green', 5), ('Eucalyptus Leaf', 'Stem Wire', 1), ('Eucalyptus Leaf', 'Floral Tape', 0.01)",
        "('Gardenia Leaf', 'Fuzzy Wire Green', 14), ('Gardenia Leaf', 'Stem Wire', 1), ('Gardenia Leaf', 'Floral Tape', 0.01)",
        "('Small Leaf', 'Fuzzy Wire Green', 1), ('Small Leaf', 'Stem Wire', 1), ('Small Leaf', 'Floral Tape', 0.01)"
    ];

    foreach ($recipes as $values) {
        $conn->query("INSERT INTO bouquet_recipes (element_name, material_name, qty) VALUES $values");
    }
}

// ----------------------------------------------------------------------
// HELPER FUNCTION: Kunin ang Kulay ng Bulaklak base sa filename
// ----------------------------------------------------------------------
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
        'calla lily' => ["Pink","Red","Orange","Yellow","White"],
        'poppy' => ["Red","Orange","Yellow","Pink","White"],
        'iris' => ["Purple","Pink","Orange","Yellow"],
        'cornflower' => ["Blue","Purple","Pink","White Pink","White Purple"], 
        'carnation' => ["Red","Orange","Yellow","Pink","Purple","Green","White"],
        'hyacinth' => ["Red","Yellow","Pink","Purple","White"],
        'hydrangea' => ["Blue","Pink","Purple","White","Orange"],
        'fuchsia' => ["Red","Pink","Purple"],
        'spider lily' => ["Red","Blue","Purple","Pink","Black"],
        'thistle' => ["Pink","Purple","Blue"]
    ];

    if (isset($f_colors[$base]) && isset($f_colors[$base][$num])) {
        $color = $f_colors[$base][$num];
        if (strtolower($color) == 'white pink') return 'Pink';
        if (strtolower($color) == 'white purple') return 'Purple';
        return $color;
    }
    return 'Red'; // fallback
}

$status_flow = ['Pending', 'To Pay', 'Processing', 'To Ship', 'To Receive', 'Completed'];

$view = isset($_GET['view']) ? $_GET['view'] : 'active';
if ($view == 'history') {
    $status_condition = "o.status IN ('Completed', 'Cancelled')";
} else {
    $status_condition = "o.status NOT IN ('Completed', 'Cancelled')";
}

// SET PRICE LOGIC
if (isset($_POST['set_price'])) {
    $oid = $_POST['order_id'];
    $price = $_POST['total_price'];
    $conn->query("UPDATE orders SET total_price = $price, status = 'To Pay', payment_status = 'To Pay' WHERE order_id = $oid");
    echo "<script>alert('Price set! Order is now To Pay.'); window.location.href='admin_orders.php?view=active';</script>";
    exit();
}

// ----------------------------------------------------------------------
// BAGONG DEDUCTION LOGIC GAMIT ANG BOUQUET_RECIPES DATABASE
// ----------------------------------------------------------------------
if (isset($_POST['update_status'])) {
    $oid = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    $deduction_msg = "";

    $chk = $conn->query("SELECT status, payment_status, order_type, size, items_json FROM orders WHERE order_id = $oid");
    if ($chk->num_rows > 0) {
        $o_data = $chk->fetch_assoc();
        $old_stat = $o_data['status'];
        
        // SECURITY CHECKS
        if ($old_stat === 'Pending' && $new_status !== 'Cancelled') {
            echo "<script>alert('Please set price first.'); window.location.href='admin_orders.php?view=active';</script>";
            exit();
        }

        // TRIGGER CHECK: Magbabawas lang pagka-lipat sa 'To Ship' (Siguradong tapos na gawin)
        $shipping_stages = ['To Ship', 'To Receive', 'Completed'];
        $is_moving_to_shipping = in_array($new_status, $shipping_stages) && !in_array($old_stat, $shipping_stages);
        $is_bouquet = stripos($o_data['order_type'], 'Bouquet') !== false;

        if ($is_moving_to_shipping && $is_bouquet) {
            $required_materials = [];

            // 1. Tignan ang size at kunin ang recipe
            $size_str = $o_data['size'] ?? '';
            $base_size = '';
            if (stripos($size_str, 'Small') !== false) $base_size = 'Small';
            elseif (stripos($size_str, 'Medium') !== false) $base_size = 'Medium';
            elseif (stripos($size_str, 'Large') !== false) $base_size = 'Large';

            if ($base_size != '') {
                $req = $conn->query("SELECT material_name, qty FROM bouquet_recipes WHERE element_name = '$base_size'");
                while($r = $req->fetch_assoc()) {
                    $mat = $r['material_name'];
                    $required_materials[$mat] = ($required_materials[$mat] ?? 0) + $r['qty'];
                }
            }

            // 2. Tignan ang items_json at kunin ang recipe bawat piraso
            $raw_json = $o_data['items_json'] ?? '[]';
            $items = json_decode($raw_json, true);
            if (is_string($items)) { $items = json_decode($items, true); }
            if (is_string($items)) { $items = json_decode(stripslashes($items), true); }

            if (is_array($items)) {
                foreach ($items as $item) {
                    if (!isset($item['name']) || (isset($item['type']) && $item['type'] === 'deduction_data')) continue;
                    
                    $element_name = strtolower(trim($item['name']));
                    
                    // Cleanup spacing & text variations
                    if ($element_name == 'lily of valley') $element_name = 'lily of the valley';
                    if ($element_name == 'eucalyptus') $element_name = 'eucalyptus leaf';
                    if ($element_name == 'gardenia') $element_name = 'gardenia leaf';
                    if ($element_name == "babys breath") $element_name = "baby's breath";

                    $element_esc = $conn->real_escape_string($element_name);
                    $req = $conn->query("SELECT material_name, qty FROM bouquet_recipes WHERE LOWER(TRIM(element_name)) = '$element_esc'");
                    
                    if ($req && $req->num_rows > 0) {
                        while($r = $req->fetch_assoc()) {
                            $mat = $r['material_name'];
                            
                            // Kapag nakita nya yung "[COLOR]" sa recipe, papalitan nya ng saktong kulay na pinili ng customer
                            if ($mat === 'Fuzzy Wire [COLOR]') {
                                $color = getFlowerColor($item['file'] ?? '', $item['name']);
                                $mat = "Fuzzy Wire " . ucfirst(strtolower($color));
                            }
                            
                            $required_materials[$mat] = ($required_materials[$mat] ?? 0) + $r['qty'];
                        }
                    }
                }
            }

            // 3. I-EXECUTE ANG DEDUCTION SA MATERIALS TABLE
            if (!empty($required_materials)) {
                $deducted_list = [];
                // Ginawa nating flexible (LIKE operator) para kahit may konting spacing issue, mahanap niya pa rin
                $stmt = $conn->prepare("UPDATE materials SET stock = stock - ? WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) OR LOWER(TRIM(name)) LIKE CONCAT('%', LOWER(TRIM(?)), '%')");
                
                foreach ($required_materials as $mat => $qty) {
                    if ($qty > 0) {
                        $stmt->bind_param("dss", $qty, $mat, $mat);
                        $stmt->execute();
                        if ($stmt->affected_rows > 0) {
                            $deducted_list[] = "$mat (-$qty)";
                        } else {
                            // Ire-report niya kung may material sa recipe na wala sa database mo
                            $deducted_list[] = "[NOT FOUND IN DB] $mat (-$qty)";
                        }
                    }
                }
                $stmt->close();
                $deduction_msg = "\\n\\n[INVENTORY REPORT]\\n" . implode("\\n", $deducted_list);
            } else {
                $deduction_msg = "\\n\\n[WARNING] Walang materials na nakuha sa recipes table.";
            }
        }
    }

    $conn->query("UPDATE orders SET status = '$new_status' WHERE order_id = $oid");
    echo "<script>alert('Status updated to $new_status!$deduction_msg'); window.location.href='admin_orders.php?view=active';</script>";
    exit();
}

$sql = "SELECT o.*, u.name AS customer_name 
        FROM orders o 
        JOIN users u ON o.user_id = u.user_id 
        WHERE $status_condition 
        ORDER BY o.order_id DESC";
$orders = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Management | Vinescraft Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
        :root { --coral: #f18973; --peach: #fce0d8; --text: #444; }
        
        body { font-family: 'Montserrat', sans-serif; background: #fffafb; margin: 0; display: flex; color: var(--text); }
        
        .sidebar { width: 260px; background: white; height: 100vh; border-right: 1px solid var(--peach); padding: 30px 20px; position: fixed; }
        .sidebar h1 { color: var(--coral); font-size: 20px; font-weight: 900; text-transform: lowercase; margin-bottom: 40px; }
        .sidebar h1::after { content: '.'; }
        
        .nav-links { display: flex; flex-direction: column; gap: 10px; }
        .nav-links a { text-decoration: none; color: #888; font-size: 13px; font-weight: 700; padding: 12px 20px; border-radius: 10px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: var(--peach); color: var(--coral); }
        .nav-links a.logout { margin-top: 20px; color: #ff4757; }

        .main-content { margin-left: 300px; padding: 40px; width: calc(100% - 340px); }
        h2 { font-size: 32px; font-weight: 900; color: var(--coral); margin: 0 0 10px; text-transform: lowercase; }

        .tabs-nav { display: flex; gap: 15px; margin-bottom: 30px; }
        .tab-link { text-decoration: none; padding: 12px 30px; border-radius: 50px; font-size: 11px; font-weight: 700; border: 2px solid var(--peach); color: var(--coral); text-transform: uppercase; transition: 0.3s; }
        .tab-link.active { background: var(--coral); color: white; border-color: var(--coral); }

        .content-box { background: white; border-radius: 20px; border: 1px solid var(--peach); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--coral); color: white; padding: 18px; text-align: left; font-size: 10px; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid var(--peach); font-size: 13px; }

        .preview-img { width: 70px; height: 90px; object-fit: cover; border-radius: 10px; border: 1px solid var(--peach); cursor: pointer; transition: 0.3s; }
        .preview-img:hover { transform: scale(1.05); }
        
        .badge { padding: 5px 12px; border-radius: 50px; font-size: 9px; font-weight: 900; text-transform: uppercase; }
        .bg-pending { background: #eee; color: #888; }
        .bg-paid { background: #66bb6a; color: white; }

        .btn-action { background: var(--coral); color: white; border: none; padding: 8px 15px; border-radius: 50px; cursor: pointer; font-size: 10px; font-weight: bold; }
        .status-select { font-size: 11px; padding: 8px; border-radius: 5px; border: 1px solid var(--peach); width: 100%; outline: none; }

        .img-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.85); backdrop-filter: blur(8px); justify-content: center; align-items: center; }
        .modal-container { background: white; padding: 30px; border-radius: 30px; position: relative; max-width: 90%; max-height: 90%; overflow-y: auto; text-align: center; }
        .modal-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px; }
        .modal-item { text-align: center; }
        .modal-item img { width: 100%; height: 250px; object-fit: contain; border-radius: 15px; border: 1px solid var(--peach); background: #f9f9f9; }
        .modal-item span { display: block; margin-top: 10px; font-size: 10px; font-weight: 900; color: var(--coral); text-transform: uppercase; }
        .close-modal { position: absolute; top: 15px; right: 20px; color: var(--text); font-size: 30px; font-weight: bold; cursor: pointer; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

    <div class="sidebar">
        <h1>vinescraft.</h1>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard Home</a>
            <a href="admin_orders.php" class="active">Manage Orders</a>
            <a href="admin_products.php">Manage Products</a>
            <a href="admin_materials.php">Manage Materials</a>
            <a href="admin_reviews.php">Reviews</a>
            <a href="admin_messages.php">Messages</a>
            <a href="admin_history.php">Login History</a>
            <a href="admin_logout.php" class="logout">Logout</a>
        </div>
    </div>

    <div class="main-content">
        <h2>order management.</h2>

        <div class="tabs-nav">
            <a href="?view=active" class="tab-link <?= $view == 'active' ? 'active' : '' ?>">Active Orders</a>
            <a href="?view=history" class="tab-link <?= $view == 'history' ? 'active' : '' ?>">History</a>
        </div>

        <div class="content-box">
            <table>
                <thead>
                    <tr>
                        <th>Preview</th>
                        <th>Details</th>
                        <th>Customer</th>
                        <th>Payment</th>
                        <th>Price</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($orders->num_rows > 0): while($o = $orders->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php 
                                $img_data = $o['custom_image'];
                                $is_json = (substr($img_data, 0, 1) === '{');
                                
                                if($is_json) {
                                    $sides = json_decode($img_data, true);
                                    $preview = $sides['f'] ?? ''; 
                                    $encoded_json = htmlspecialchars($img_data, ENT_QUOTES, 'UTF-8');
                                    echo "<img src='$preview' class='preview-img' onclick=\"openShirtModal('$encoded_json')\">";
                                } else {
                                    echo "<img src='$img_data' class='preview-img' onclick=\"openSingleModal('$img_data')\">";
                                }
                            ?>
                        </td>

                        <td>
                            <strong style="color: var(--coral);">#<?= $o['order_id'] ?></strong> | <?= $o['order_type'] ?><br>
                            <small>Size: <?= $o['size'] ?></small>
                        </td>

                        <td><?= htmlspecialchars($o['customer_name']) ?></td>
                        <td><span class="badge <?= ($o['payment_status'] == 'Fully Paid') ? 'bg-paid' : 'bg-pending' ?>"><?= $o['payment_status'] ?></span></td>
                        
                        <td>
                            <?php if($o['status'] == 'Pending' && !empty($o['items_json'])): ?>
                                <form method="POST" style="display:flex; gap:5px;">
                                    <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                                    <input type="number" name="total_price" step="0.01" style="width:65px; border:1px solid var(--peach); border-radius:5px; padding:5px; outline:none;" required>
                                    <button type="submit" name="set_price" class="btn-action">Set</button>
                                </form>
                            <?php else: ?>
                                ₱<?= number_format($o['total_price'], 2) ?>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php
                                $current_idx = array_search($o['status'], $status_flow);
                                $can_proceed = true;
                                $block_reason = "";

                                if ($o['status'] == 'Pending') {
                                    $can_proceed = false;
                                    $block_reason = "Set Price First";
                                } elseif ($o['status'] == 'To Pay' && !in_array($o['payment_status'], ['Paid 50% DP', 'Fully Paid'])) {
                                    $can_proceed = false;
                                    $block_reason = "Waiting for DP";
                                }
                            ?>
                            <form method="POST">
                                <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                                <input type="hidden" name="update_status" value="1">
                                <select name="status" class="status-select" onchange="this.form.submit()">
                                    <option selected disabled><?= $o['status'] ?></option>
                                    <?php 
                                        if ($can_proceed && $current_idx !== false && $current_idx < count($status_flow) - 1) {
                                            $next = $status_flow[$current_idx + 1];
                                            echo "<option value='$next'>Next: $next</option>";
                                        } elseif (!$can_proceed && !in_array($o['status'], ['Completed', 'Cancelled'])) {
                                            echo "<option disabled style='color:#ccc;'>($block_reason)</option>";
                                        }
                                    ?>
                                    <option value="Cancelled" style="color:#ff4757; font-weight:bold;">Cancel Order</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="imageModal" class="img-modal">
        <div class="modal-container">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h3 style="color:var(--coral); font-weight:900; font-size:14px; text-transform:uppercase;">Design Preview</h3>
            <div id="modalContent" class="modal-grid"></div>
        </div>
    </div>

    <script>
        function openShirtModal(jsonStr) {
            const data = JSON.parse(jsonStr);
            const container = document.getElementById("modalContent");
            container.innerHTML = ''; 
            const labels = { f: 'Front Side', b: 'Back Side', ls: 'Left Sleeve', rs: 'Right Sleeve' };
            for (let side in data) {
                if (data[side]) {
                    container.innerHTML += `<div class="modal-item"><img src="${data[side]}"><span>${labels[side] || side}</span></div>`;
                }
            }
            document.getElementById("imageModal").style.display = "flex";
        }
        function openSingleModal(src) {
            document.getElementById("modalContent").innerHTML = `<div class="modal-item" style="grid-column: 1/-1;"><img src="${src}" style="max-height:450px;"></div>`;
            document.getElementById("imageModal").style.display = "flex";
        }
        function closeModal() { document.getElementById("imageModal").style.display = "none"; }
        window.onclick = function(e) { if (e.target == document.getElementById("imageModal")) closeModal(); }
    </script>
</body>
</html>