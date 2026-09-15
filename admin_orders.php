<?php
session_start();
require_once 'db_connect.php';

// FETCH: Kunin yung total na unread para sa Sidebar Badge
$unread_total_query = $conn->query("SELECT COUNT(*) as unread FROM messages WHERE sender_type = 'user' AND is_read = 0");
$unread_count = $unread_total_query ? $unread_total_query->fetch_assoc()['unread'] : 0;

// PROTECTION: Admin session check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// AUTO-CREATE RECEIPT COLUMN PARA SAFE ANG QUERY SA BABA
$check_receipt_col = $conn->query("SHOW COLUMNS FROM orders LIKE 'receipt_image'");
if ($check_receipt_col && $check_receipt_col->num_rows == 0) {
    $conn->query("ALTER TABLE orders ADD receipt_image VARCHAR(255) DEFAULT NULL");
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
// HELPER FUNCTIONS
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
    return ''; // fallback
}

// BAGO: Helper para i-break down ang flower recipe sa Admin Side
function getFlowerRecipeAdmin($flower_string, $conn) {
    $base_name = $flower_string;
    $color = 'Red';
    if (preg_match('/^(.*?)\s*\((.*?)\)$/', $flower_string, $matches)) {
        $base_name = trim($matches[1]);
        $color = trim($matches[2]);
    }
    
    $recipe = [];
    $base_esc = $conn->real_escape_string($base_name);
    $res = $conn->query("SELECT material_name, qty FROM bouquet_recipes WHERE LOWER(TRIM(element_name)) = LOWER('$base_esc')");
    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            $mat = $r['material_name'];
            if ($mat === 'Fuzzy Wire [COLOR]') {
                $color = ucfirst(strtolower($color));
                if (strtolower($color) == 'White pink') $color = 'Pink';
                if (strtolower($color) == 'White purple') $color = 'Purple';
                $mat = "Fuzzy Wire " . $color;
            }
            $recipe[$mat] = floatval($r['qty']);
        }
    }
    return $recipe;
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

// RE-CHECK PAYMENT
// Hindi kayang abutin ng PayMongo webhooks ang localhost, kaya kapag nawala ang
// customer bago makabalik sa site, hindi nalalaman ng DB ang bayad. Ito ang
// manu-manong webhook: tinatanong ulit ang PayMongo tungkol sa naka-pending na attempt.
if (isset($_POST['recheck_payment'])) {
    require_once 'paymongo.php';
    $oid = intval($_POST['order_id']);
    $msg = "No new payment found.";

    $q = $conn->prepare("SELECT * FROM payments
                         WHERE order_id = ? AND applied = 0 AND checkout_session_id IS NOT NULL
                         ORDER BY payment_id DESC LIMIT 1");
    $q->bind_param("i", $oid);
    $q->execute();
    $pending = $q->get_result()->fetch_assoc();

    if (!$pending) {
        $msg = "No pending online payment for this order.";
    } else {
        [$code, $resp] = pm_get_checkout_session($conn, $pending['checkout_session_id']);
        $paid = ($code >= 200 && $code < 300) ? pm_find_paid_payment($resp) : null;

        if (!$paid) {
            $msg = "This attempt is not paid yet on PayMongo.";
        } elseif (intval($paid['attributes']['amount'] ?? 0) !== intval($pending['amount_centavos'])) {
            $msg = "AMOUNT MISMATCH: paid ₱" . number_format(($paid['attributes']['amount'] ?? 0) / 100, 2) .
                   " but ₱" . number_format($pending['amount_centavos'] / 100, 2) . " was expected. Not recorded.";
            $raw = json_encode($paid);
            $u = $conn->prepare("UPDATE payments SET status='mismatch', raw_response=? WHERE payment_id=?");
            $u->bind_param("si", $raw, $pending['payment_id']);
            $u->execute();
        } else {
            // Parehong idempotency guard gaya sa pay_return.php
            $raw = json_encode($paid);
            $ppid = $paid['id'] ?? null;
            $mark = $conn->prepare("UPDATE payments SET status='paid', provider_payment_id=?, paid_at=NOW(),
                                           applied=1, raw_response=? WHERE payment_id=? AND applied=0");
            $mark->bind_param("ssi", $ppid, $raw, $pending['payment_id']);
            $mark->execute();

            if ($mark->affected_rows === 1) {
                $amt = floatval($pending['amount']);
                $o = $conn->query("SELECT status, total_price, shipping_fee, grand_total, amount_paid
                                   FROM orders WHERE order_id = $oid")->fetch_assoc();
                $g = $o['grand_total'] !== null ? floatval($o['grand_total'])
                                                : floatval($o['total_price']) + floatval($o['shipping_fee']);
                $settled = (floatval($o['amount_paid']) + $amt + 0.01) >= $g;
                $dp_pct  = setting_int($conn, 'dp_percent', 50);

                $ps = $settled ? 'Fully Paid' : 'Paid ' . $dp_pct . '% DP';
                $st = $o['status'];
                if (in_array($st, ['To Pay', 'Pending'], true)) $st = 'Processing';
                elseif ($st === 'To Receive' && $settled)       $st = 'Completed';

                $upd = $conn->prepare("UPDATE orders SET amount_paid = amount_paid + ?, payment_status = ?, status = ?
                                       WHERE order_id = ?");
                $upd->bind_param("dssi", $amt, $ps, $st, $oid);
                $upd->execute();
                $msg = "Payment of ₱" . number_format($amt, 2) . " found and recorded. Status: $st";
            } else {
                $msg = "This payment was already recorded.";
            }
        }
    }

    echo "<script>alert(" . json_encode($msg) . "); window.location.href='admin_orders.php?view=active';</script>";
    exit();
}

// COLLECT BALANCE ON DELIVERY
// Dito nagsasara ang COD at 50% DP na orders - ito ang nagre-record ng cash na
// natanggap ng rider. Kung wala ito, walang paraan para ma-Fully Paid ang COD.
if (isset($_POST['collect_balance'])) {
    $oid = intval($_POST['order_id']);
    $stmt = $conn->prepare("UPDATE orders
                            SET amount_paid = COALESCE(grand_total, total_price + shipping_fee),
                                payment_status = 'Fully Paid'
                            WHERE order_id = ? AND status = 'To Receive'");
    $stmt->bind_param("i", $oid);
    $stmt->execute();
    echo "<script>alert('Balance collected. The order is now Fully Paid.'); window.location.href='admin_orders.php?view=active';</script>";
    exit();
}

// DEDUCTION LOGIC GAMIT ANG BOUQUET_RECIPES DATABASE
if (isset($_POST['update_status'])) {
    $oid = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    $deduction_msg = "";

    $chk = $conn->query("SELECT status, payment_status, order_type, size, items_json, materials_deducted FROM orders WHERE order_id = $oid");
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
        $is_bouquet = stripos($o_data['order_type'] ?? '', 'Bouquet') !== false;

        // MAHALAGA: nakabawas na ng materials ang cart.php noong nag-checkout.
        // Dati, hindi ito nadodoble dahil laging '' ang order_type kaya hindi
        // kailanman naging totoo ang $is_bouquet. Ngayong tama na ang order_type,
        // ang flag na ito na ang pumipigil sa dobleng pagbawas.
        $already_deducted = intval($o_data['materials_deducted'] ?? 0) === 1;

        if ($is_moving_to_shipping && $is_bouquet && !$already_deducted) {
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
                    // Metadata lang ang wrapper_info / custom_size_info / deduction_data,
                    // hindi tunay na materyales - laktawan.
                    if (!is_deductible_item($item)) continue;

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
                            
                            if ($mat === 'Fuzzy Wire [COLOR]') {
                                $color = getFlowerColor($item['file'] ?? '', $item['name']);
                                if (!$color) $color = 'Red'; // Deduction fallback
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
                $stmt = $conn->prepare("UPDATE materials SET stock = stock - ? WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) OR LOWER(TRIM(name)) LIKE CONCAT('%', LOWER(TRIM(?)), '%')");
                
                foreach ($required_materials as $mat => $qty) {
                    if ($qty > 0) {
                        $stmt->bind_param("dss", $qty, $mat, $mat);
                        $stmt->execute();
                        if ($stmt->affected_rows > 0) {
                            $deducted_list[] = "$mat (-$qty)";
                        } else {
                            $deducted_list[] = "[NOT FOUND IN DB] $mat (-$qty)";
                        }
                    }
                }
                $stmt->close();

                // Markahan para hindi na ulit mabawasan ang parehong order.
                $conn->query("UPDATE orders SET materials_deducted = 1 WHERE order_id = $oid");

                $deduction_msg = "\\n\\n[INVENTORY REPORT]\\n" . implode("\\n", $deducted_list);
            } else {
                $deduction_msg = "\\n\\n[WARNING] No materials were found in the recipes table.";
            }
        }
    }

    $conn->query("UPDATE orders SET status = '$new_status' WHERE order_id = $oid");
    echo "<script>alert('Status updated to $new_status!$deduction_msg'); window.location.href='admin_orders.php?view=active';</script>";
    exit();
}

$sql = "SELECT o.*, u.name AS customer_name, u.mobile_no, u.address 
        FROM orders o 
        JOIN users u ON o.user_id = u.user_id 
        WHERE $status_condition 
        ORDER BY o.order_id DESC";
$orders = $conn->query($sql);

// Aling mga order ang may naiwang hindi natapos na online payment? Dito lalabas
// ang "Re-check Payment" button - pandagdag ito sa kawalan ng webhooks sa localhost.
$pending_pay = [];
try {
    $pp = $conn->query("SELECT DISTINCT order_id FROM payments WHERE applied = 0 AND checkout_session_id IS NOT NULL");
    if ($pp) while ($r = $pp->fetch_assoc()) $pending_pay[intval($r['order_id'])] = true;
} catch (Throwable $e) {
}
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
        
        /* BADGE CSS PARA SA SIDEBAR */
.msg-badge {
    background: #ff4757;
    color: white;
    padding: 2px 6px;
    border-radius: 50px;
    font-size: 10px;
    font-weight: 900;
    margin-left: 5px;
    vertical-align: top;
}

        .sidebar { width: 260px; background: white; height: 100vh; border-right: 1px solid var(--peach); padding: 30px 20px; position: fixed; }
        .sidebar h1 { color: var(--coral); font-size: 20px; font-weight: 900; text-transform: lowercase; margin-bottom: 40px; }
        
        .nav-links { display: flex; flex-direction: column; gap: 10px; }
        .nav-links a { text-decoration: none; color: #888; font-size: 13px; font-weight: 700; padding: 12px 20px; border-radius: 10px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: var(--peach); color: var(--coral); }
        .nav-links a.logout { margin-top: 20px; color: #ff4757; }

        .main-content { margin-left: 300px; padding: 40px; width: calc(100% - 340px); }
        h2 { font-size: 32px; font-weight: 900; color: var(--coral); margin: 0 0 10px; text-transform: capitalize; }

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
        .bg-partial { background: #ffb74d; color: #5d3a00; }

        .btn-action { background: var(--coral); color: white; border: none; padding: 8px 15px; border-radius: 50px; cursor: pointer; font-size: 10px; font-weight: bold; transition: 0.3s; }
        .btn-action:hover { opacity: 0.8; transform: translateY(-2px); }
        .btn-outline { background: white; color: var(--coral); border: 2px solid var(--coral); }
        .btn-outline:hover { background: var(--peach); color: var(--coral); }

        .status-select { font-size: 11px; padding: 8px; border-radius: 5px; border: 1px solid var(--peach); width: 100%; outline: none; }

        .img-modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.85); backdrop-filter: blur(8px); justify-content: center; align-items: center; }
        .modal-container { background: white; padding: 30px; border-radius: 30px; position: relative; max-width: 90%; max-height: 90%; overflow-y: auto; text-align: center; }
        .modal-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px; }
        .modal-item { text-align: center; }
        .modal-item img { width: 100%; height: 250px; object-fit: contain; border-radius: 15px; border: 1px solid var(--peach); background: #f9f9f9; }
        .modal-item span { display: block; margin-top: 10px; font-size: 10px; font-weight: 900; color: var(--coral); text-transform: uppercase; }
        .close-modal { position: absolute; top: 15px; right: 20px; color: var(--text); font-size: 30px; font-weight: bold; cursor: pointer; }
        
        .details-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(5px); justify-content: center; align-items: center; }
        .details-content { background: white; padding: 30px; border-radius: 20px; width: 90%; max-width: 500px; position: relative; border: 1px solid var(--peach); box-shadow: 0 20px 50px rgba(0,0,0,0.1); }
        .details-section { margin-bottom: 20px; }
        .details-section h4 { color: var(--coral); margin: 0 0 10px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px dashed var(--peach); padding-bottom: 5px; }
        .details-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 5px; color: var(--text); }
        .details-row .val { font-weight: 700; text-align: right; max-width: 60%; }
        
        .customer-details-box { font-size: 10px; color: #666; margin-top: 8px; line-height: 1.5; background: #fffafb; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--peach); }

        .zoom-img-large {
            max-height: 85vh !important;
            height: auto !important;
            width: auto !important;
            max-width: 100% !important;
            object-fit: contain;
            border: none !important;
            background: transparent !important;
        }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

<?php include 'admin_sidebar.php' ?>

    <div class="main-content">
        <h2>Order Management</h2>

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
                            <strong style="color: var(--coral);">#<?= $o['order_id'] ?></strong> | <?= htmlspecialchars($o['order_type']) ?><br>
                            <?php
                                // BAGO: Logic para kunin lahat ng materials sa parehong Custom at Shop Orders
                                $materials_used = [];
                                $raw_items = json_decode($o['items_json'] ?? '[]', true);
                                
                                // 1. Process Custom Bouquet items
                                if (is_array($raw_items)) {
                                    foreach ($raw_items as &$it) {
                                        if (isset($it['type']) && $it['type'] === 'deduction_data' && isset($it['materials'])) {
                                            foreach ($it['materials'] as $m_name => $m_qty) {
                                                $materials_used[$m_name] = ($materials_used[$m_name] ?? 0) + $m_qty;
                                            }
                                        }
                                        // Auto-append colors para malinaw sa inclusions
                                        if (isset($it['type']) && $it['type'] === 'flower') {
                                            $col = getFlowerColor($it['file'] ?? '', $it['name'] ?? '');
                                            if ($col) {
                                                if (strpos($it['name'], "($col)") === false) {
                                                    $it['name'] .= " ($col)";
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $raw_items = [];
                                }

                                // 2. Process Shop Products (mula sa order_details at product_materials)
                                $oid = intval($o['order_id']);
                                $check_od = $conn->query("SHOW TABLES LIKE 'order_details'");
                                if ($check_od && $check_od->num_rows > 0) {
                                    $od_res = $conn->query("SELECT od.product_id, od.quantity, p.name FROM order_details od JOIN products p ON od.product_id = p.product_id WHERE od.order_id = $oid");
                                    if ($od_res && $od_res->num_rows > 0) {
                                        while ($od = $od_res->fetch_assoc()) {
                                            $pid = intval($od['product_id']);
                                            $p_qty = floatval($od['quantity']);
                                            $p_name = $od['name'];

                                            // Idagdag ang Shop Product sa Inclusions List
                                            $raw_items[] = [
                                                'type' => 'shop_product',
                                                'name' => $p_name,
                                                'qty' => $p_qty
                                            ];

                                            // I-break down at idagdag ang materials nila
                                            $pm_res = $conn->query("SELECT item_type, item_name, qty FROM product_materials WHERE product_id = $pid");
                                            if ($pm_res && $pm_res->num_rows > 0) {
                                                while ($pm = $pm_res->fetch_assoc()) {
                                                    $type = $pm['item_type'];
                                                    $item_name = $pm['item_name'];
                                                    $pm_qty = floatval($pm['qty']) * $p_qty;

                                                    if ($type === 'material') {
                                                        $materials_used[$item_name] = ($materials_used[$item_name] ?? 0) + $pm_qty;
                                                    } elseif ($type === 'flower') {
                                                        $recipe = getFlowerRecipeAdmin($item_name, $conn);
                                                        foreach ($recipe as $r_mat => $r_qty) {
                                                            $materials_used[$r_mat] = ($materials_used[$r_mat] ?? 0) + ($r_qty * $pm_qty);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }

                                // Ipasa ang lahat ng data sa JS Modal
                                $modal_data = [
                                    'order_id' => $o['order_id'],
                                    'order_type' => $o['order_type'],
                                    'size' => $o['size'],
                                    'customer_name' => $o['customer_name'],
                                    'phone' => $o['mobile_no'] ?? 'No phone provided',
                                    'address' => $o['address'] ?? 'No address provided',
                                    'items' => $raw_items,
                                    'materials_used' => $materials_used, // Pinasa na natin yung PHP calculated materials
                                    'receipt' => $o['receipt_image'] ?? '' 
                                ];
                                $modal_data_json = htmlspecialchars(json_encode($modal_data), ENT_QUOTES, 'UTF-8');
                            ?>
                            <button class="btn-action btn-outline" style="padding: 5px 12px; font-size: 9px; margin-top: 8px;" 
                                    data-order='<?= $modal_data_json ?>' 
                                    onclick="openDetailsModal(this)">View Details</button>
                        </td>

                        <td>
                            <strong style="color: var(--text);"><?= htmlspecialchars($o['customer_name']) ?></strong>
                            <div class="customer-details-box">
                                <strong>📱 Phone:</strong> <?= htmlspecialchars($o['mobile_no'] ?? 'No phone provided') ?><br>
                                <strong>📍 Address:</strong> <?= htmlspecialchars($o['address'] ?? 'No address provided') ?>
                            </div>
                        </td>
                        
                        <?php
                            // Ang COD at partial payments ay hindi pa bayad pero hindi rin naghihintay -
                            // sariling amber state sila para malinaw agad sa listahan.
                            if ($o['payment_status'] == 'Fully Paid') {
                                $pay_badge = 'bg-paid';
                            } elseif (in_array($o['payment_status'], ['COD', 'Paid 50% DP'])) {
                                $pay_badge = 'bg-partial';
                            } else {
                                $pay_badge = 'bg-pending';
                            }
                            $o_grand   = $o['grand_total'] !== null
                                       ? floatval($o['grand_total'])
                                       : floatval($o['total_price']) + floatval($o['shipping_fee']);
                            $o_balance = max(0, $o_grand - floatval($o['amount_paid']));
                        ?>
                        <td>
                            <span class="badge <?= $pay_badge ?>"><?= $o['payment_status'] ?></span>
                            <?php if($o_balance > 0 && !in_array($o['status'], ['Pending', 'Cancelled'])): ?>
                                <div style="font-size:9px; color:#a2695c; margin-top:5px; font-weight:700;">₱<?= number_format($o_balance, 2) ?> balance</div>
                            <?php endif; ?>
                            <?php if(!empty($o['payment_method'])): ?>
                                <div style="font-size:9px; color:#bbb; margin-top:3px;">
                                    <?= htmlspecialchars($o['payment_method']) ?><?= !empty($o['courier']) ? ' &middot; ' . htmlspecialchars($o['courier']) : '' ?>
                                </div>
                            <?php endif; ?>

                            <?php if($o['status'] == 'To Receive' && $o_balance > 0): ?>
                                <form method="POST" onsubmit="return confirm('Confirm that ₱<?= number_format($o_balance, 2) ?> was collected from the customer?');" style="margin-top:6px;">
                                    <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                                    <button type="submit" name="collect_balance" class="btn-action" style="background:#2ed573; font-size:9px; padding:5px 8px;">Collect Balance</button>
                                </form>
                            <?php endif; ?>

                            <?php if(!empty($pending_pay[$o['order_id']])): ?>
                                <form method="POST" style="margin-top:6px;" title="Ask PayMongo again. Use this if the customer paid but did not make it back to the site.">
                                    <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                                    <button type="submit" name="recheck_payment" class="btn-action" style="background:#5c8ff5; font-size:9px; padding:5px 8px;">Re-check Payment</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        
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
                                } elseif ($o['status'] == 'To Pay' && !in_array($o['payment_status'], ['Paid 50% DP', 'Fully Paid', 'COD'])) {
                                    // Kasama na ang COD dito - walang online na bayad na hihintayin,
                                    // kaya dapat makausad agad ito sa fulfillment.
                                    $can_proceed = false;
                                    $block_reason = "Waiting for Payment";
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
            <h3 style="color:var(--coral); font-weight:900; font-size:14px; text-transform:uppercase;">Preview</h3>
            <div id="modalContent" class="modal-grid"></div>
        </div>
    </div>

    <div id="detailsModal" class="details-modal">
        <div class="details-content">
            <span class="close-modal" style="position: absolute; top: 15px; right: 20px; font-size: 24px; font-weight: bold; cursor: pointer; color: var(--text);" onclick="document.getElementById('detailsModal').style.display='none'">&times;</span>
            <h3 style="color:var(--coral); font-weight:900; font-size:22px; margin-top:0; text-transform:lowercase;">order details.</h3>
            
            <div class="details-section">
                <h4>Customer Information</h4>
                <div class="details-row"><span>Name:</span> <span class="val" id="mdl-name"></span></div>
                <div class="details-row"><span>Phone:</span> <span class="val" id="mdl-phone"></span></div>
                <div class="details-row"><span>Address:</span> <span class="val" id="mdl-address"></span></div>
            </div>

            <div class="details-section">
                <h4>Order Specifications</h4>
                <div class="details-row"><span>Order ID:</span> <span class="val" id="mdl-oid"></span></div>
                <div class="details-row"><span>Type:</span> <span class="val" id="mdl-type"></span></div>
                <div class="details-row"><span>Size:</span> <span class="val" id="mdl-size"></span></div>
            </div>

            <div class="details-section" id="mdl-inclusions-section">
                <h4>Inclusions</h4>
                <div id="mdl-inclusions-list"></div>
            </div>

            <div class="details-section" id="mdl-materials-section" style="display:none; background: #fffcfd; padding: 15px; border-radius: 10px; border: 1px dashed var(--peach);">
                <h4>Materials Required</h4>
                <div id="mdl-materials-list"></div>
            </div>

            <div class="details-section" id="mdl-receipt-container" style="display:none; text-align:center; background: #fafafa; padding: 15px; border-radius: 10px; border: 1px dashed var(--coral);">
                <h4 style="margin-top:0;">Payment Receipt Uploaded</h4>
                <img id="mdl-receipt-img" src="" style="max-width:100%; max-height:200px; border-radius:10px; border:1px solid var(--peach); cursor:pointer;" onclick="openSingleModal(this.src)">
                <p style="font-size: 9px; color: #888; margin: 5px 0 0;">Click image to enlarge</p>
            </div>
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
            document.getElementById("modalContent").innerHTML = `<div class="modal-item" style="grid-column: 1/-1; display:flex; justify-content:center;"><img src="${src}" class="zoom-img-large"></div>`;
            document.getElementById("imageModal").style.display = "flex";
        }

        function openDetailsModal(btnElement) {
            const jsonStr = btnElement.getAttribute('data-order');
            const data = JSON.parse(jsonStr);
            
            document.getElementById('mdl-name').innerText = data.customer_name;
            document.getElementById('mdl-phone').innerText = data.phone;
            document.getElementById('mdl-address').innerText = data.address;
            document.getElementById('mdl-oid').innerText = "#" + data.order_id;
            document.getElementById('mdl-type').innerText = data.order_type;
            document.getElementById('mdl-size').innerText = data.size || 'N/A';

            const incList = document.getElementById('mdl-inclusions-list');
            const matList = document.getElementById('mdl-materials-list');
            incList.innerHTML = '';
            matList.innerHTML = '';
            
            // 1. I-render ang Inclusions
            if (data.items && data.items.length > 0) {
                document.getElementById('mdl-inclusions-section').style.display = 'block';
                
                let summary = {};
                data.items.forEach(item => {
                    if(item.type && item.type === 'deduction_data') return; // Skip logic dahil PHP na nag-handle
                    
                    let label = item.name || item.content || (item.type === 'graphic' ? 'Custom Graphic' : 'Item');
                    let qty = item.qty ? parseFloat(item.qty) : 1; 
                    
                    summary[label] = (summary[label] || 0) + qty;
                });
                
                let hasItems = false;
                for (const [name, qty] of Object.entries(summary)) {
                    hasItems = true;
                    incList.innerHTML += `<div class="details-row"><span>${name}</span> <span class="val" style="color:var(--coral);">x${qty}</span></div>`;
                }

                if(!hasItems) {
                    incList.innerHTML = '<div style="font-size:11px; color:#888; text-align:center; padding:10px;">No specific elements added.</div>';
                }
            } else {
                document.getElementById('mdl-inclusions-section').style.display = 'none';
            }

            // 2. I-render ang Materials Required (Calculated na galing PHP)
            let deductionData = data.materials_used;
            if (deductionData && Object.keys(deductionData).length > 0) {
                document.getElementById('mdl-materials-section').style.display = 'block';
                for (const [matName, matQty] of Object.entries(deductionData)) {
                    // Ginawang Number() para malinis ang output (e.g. 1 imbes na 1.00)
                    let cleanQty = Number(matQty).toString();
                    matList.innerHTML += `<div class="details-row"><span>${matName}</span> <span class="val" style="color:#888; font-weight:900;">${cleanQty}</span></div>`;
                }
            } else {
                document.getElementById('mdl-materials-section').style.display = 'none';
            }

            if (data.receipt) {
                document.getElementById('mdl-receipt-container').style.display = 'block';
                document.getElementById('mdl-receipt-img').src = data.receipt;
            } else {
                document.getElementById('mdl-receipt-container').style.display = 'none';
                document.getElementById('mdl-receipt-img').src = '';
            }

            document.getElementById('detailsModal').style.display = 'flex';
        }

        function closeModal() { document.getElementById("imageModal").style.display = "none"; }
        window.onclick = function(e) { 
            if (e.target == document.getElementById("imageModal")) closeModal(); 
            if (e.target == document.getElementById("detailsModal")) document.getElementById('detailsModal').style.display = 'none';
        }
    </script>
</body>
</html>