<?php
session_start();
require_once 'db_connect.php';

// FETCH: Kunin yung total na unread para sa Sidebar Badge
$unread_total_query = $conn->query("SELECT COUNT(*) as unread FROM messages WHERE sender_type = 'user' AND is_read = 0");
$unread_count = $unread_total_query ? $unread_total_query->fetch_assoc()['unread'] : 0;

// PROTECTION: Independent admin session check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$message = "";

// --- AUTO-CREATE/ALTER PRODUCT MATERIALS TABLE (WITH ERROR HANDLING) ---
try {
    $check_pm = $conn->query("SHOW TABLES LIKE 'product_materials'");
    if ($check_pm && $check_pm->num_rows == 0) {
        $conn->query("CREATE TABLE product_materials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT,
            item_type VARCHAR(50) DEFAULT 'material',
            item_name VARCHAR(100) DEFAULT NULL,
            material_id INT NULL,
            qty DECIMAL(10,2)
        )");
    } else {
        $check_col = $conn->query("SHOW COLUMNS FROM product_materials LIKE 'item_type'");
        if ($check_col && $check_col->num_rows == 0) {
            $conn->query("ALTER TABLE product_materials ADD item_type VARCHAR(50) DEFAULT 'material'");
            $conn->query("ALTER TABLE product_materials ADD item_name VARCHAR(100) DEFAULT NULL");
            $conn->query("ALTER TABLE product_materials MODIFY material_id INT NULL");
        }
    }
} catch (Exception $e) {}

// AUTO-ADD CATEGORY COLUMN
try {
    $check_cat = $conn->query("SHOW COLUMNS FROM products LIKE 'category'");
    if ($check_cat && $check_cat->num_rows == 0) {
        $conn->query("ALTER TABLE products ADD category VARCHAR(50) DEFAULT 'Others'");
    }
} catch (Exception $e) {}

// ==========================================================
// KUNIN ANG MGA PAGPIPILIAN PARA SA DROPDOWN
// ==========================================================
$default_flowers = [];
$f_colors = [
    'Tulip' => ["Red","Pink","Orange","Yellow","Purple","Blue","Green","White"],
    'Rose' => ["Red","Pink","Orange","Yellow","Purple","Blue","Green","White","Black"],
    'Small Sunflower' => ["Yellow","Pink","Purple","Red"],
    'Big Sunflower' => ["Yellow","Pink","Purple","Red"],
    'Lily' => ["Red","Pink","Yellow","Orange","White"],
    'Calla lily' => ["Magenta","Maroon","Orange","Yellow","White"],
    'Spider lily' => ["Red","Blue","Purple","Pink","Black"],
    'Poppy' => ["Red","Orange","Yellow","Pink","White"],
    'Iris' => ["Purple","Pink","Orange","Yellow"],
    'Cornflower' => ["Blue","Purple","Pink","White Pink","White Purple"], 
    'Carnation' => ["Red","Orange","Yellow","Pink","Purple","Green","White"],
    'Hyacinth' => ["Red","Yellow","Pink","Purple","White"],
    'Hydrangea' => ["Blue","Pink","Purple","White","Orange"],
    'Fuchsia' => ["Red","Pink","Purple"],
    'Thistle' => ["Pink","Purple","Blue"]
];
foreach ($f_colors as $fname => $colors) {
    foreach ($colors as $c) { $default_flowers[] = "$fname ($c)"; }
}
$fillers = ["Daisy", "Lavender", "Baby's Breath", "Lily of the Valley", "Statice", "Bell flower", "Eucalyptus Leaf", "Gardenia Leaf", "Small Leaf"];
foreach ($fillers as $fil) { $default_flowers[] = $fil; }

$dynamic_flowers = [];
try {
    $check_df = $conn->query("SHOW TABLES LIKE 'dynamic_flowers'");
    if ($check_df && $check_df->num_rows > 0) {
        $df_res = $conn->query("SELECT name FROM dynamic_flowers ORDER BY name ASC");
        while ($r = $df_res->fetch_assoc()) { $dynamic_flowers[] = $r['name']; }
    }
} catch (Exception $e) {}

$materials_list = [];
$mats_res = $conn->query("SELECT material_id, name, stock FROM materials WHERE name NOT LIKE '%Fuzzy Wire%' ORDER BY name ASC");
if ($mats_res) {
    while($m = $mats_res->fetch_assoc()){ $materials_list[] = $m; }
}

function getFlowerRecipe($flower_string, $conn) {
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

// --- 1. LOGIC PARA SA TOGGLE FEATURED ---
if (isset($_GET['toggle_featured'])) {
    $id = intval($_GET['toggle_featured']);
    $current_status = intval($_GET['status']);
    $new_status = ($current_status == 1) ? 0 : 1;

    if ($new_status == 1) {
        $count_res = $conn->query("SELECT COUNT(*) as total FROM products WHERE is_featured = 1");
        $total_featured = $count_res->fetch_assoc()['total'];
        
        if ($total_featured >= 5) {
            echo "<script>alert('Maximum of 5 featured products reached! Remove one first.'); window.location.href='admin_products.php';</script>";
            exit();
        }
    }

    $conn->query("UPDATE products SET is_featured = $new_status WHERE product_id = $id");
    header("Location: admin_products.php");
    exit();
}

// --- 2. LOGIC PARA SA PAG-UPDATE AT PAG-DEDUCT NG MATERIALS ---
if (isset($_POST['save_update'])) {
    $id = intval($_POST['product_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = $_POST['price'];
    $stock_to_add = intval($_POST['stock_to_add']); // Inayos: Kinukuha na lang yung idadagdag na quantity
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $reorder = max(0, intval($_POST['reorder_level'] ?? 10));
    
    $conn->query("DELETE FROM product_materials WHERE product_id = $id");
    if (isset($_POST['item_selection']) && isset($_POST['item_qty'])) {
        foreach ($_POST['item_selection'] as $index => $selection) {
            $qty = floatval($_POST['item_qty'][$index]);
            if (!empty($selection) && $qty > 0) {
                $parts = explode('|', $selection);
                $type = $parts[0];
                if ($type === 'flower') {
                    $item_name = $conn->real_escape_string($parts[1]);
                    $conn->query("INSERT INTO product_materials (product_id, item_type, item_name, material_id, qty) VALUES ($id, 'flower', '$item_name', NULL, $qty)");
                } elseif ($type === 'material') {
                    $mat_id = intval($parts[1]);
                    $item_name = $conn->real_escape_string($parts[2]);
                    $conn->query("INSERT INTO product_materials (product_id, item_type, item_name, material_id, qty) VALUES ($id, 'material', '$item_name', $mat_id, $qty)");
                }
            }
        }
    }

    $old_res = $conn->query("SELECT stock FROM products WHERE product_id = $id");
    $old_stock = intval($old_res->fetch_assoc()['stock']);
    
    $new_stock = $old_stock + $stock_to_add; // I-aadd na natin directly sa current stock
    $stock_diff = $stock_to_add; // Yung nilagay mo sa form ang mismong difference na ibabawas sa materials

    if ($stock_diff != 0) {
        $pm_res = $conn->query("SELECT item_type, item_name, material_id, qty FROM product_materials WHERE product_id = $id");
        while ($pm = $pm_res->fetch_assoc()) {
            $type = $pm['item_type'];
            $qty_per_product = floatval($pm['qty']);
            $total_qty_to_change = abs($stock_diff) * $qty_per_product;
            
            if ($type === 'material' && $pm['material_id'] > 0) {
                $mat_id = intval($pm['material_id']);
                if ($stock_diff > 0) { 
                    // Ginagamit natin ang GREATEST para kung sumobra man ang deduct, 0 lang ang limit
                    $conn->query("UPDATE materials SET stock = GREATEST(0, stock - $total_qty_to_change) WHERE material_id = $mat_id");
                } else { 
                    $conn->query("UPDATE materials SET stock = stock + $total_qty_to_change WHERE material_id = $mat_id");
                }
            } elseif ($type === 'flower') {
                $flower_name = $pm['item_name'];
                $recipe = getFlowerRecipe($flower_name, $conn);
                foreach ($recipe as $mat_name => $mat_qty) {
                    $total_mat_change = $total_qty_to_change * $mat_qty;
                    $mat_esc = $conn->real_escape_string($mat_name);
                    if ($stock_diff > 0) {
                        // FIXED: Tinanggal yung LIKE wildcards para exact match lang ang ide-deduct (iwas issue sa lahat ng kulay ng fuzzy wire na nababawasan)
                        $conn->query("UPDATE materials SET stock = GREATEST(0, stock - $total_mat_change) WHERE LOWER(TRIM(name)) = LOWER('$mat_esc')");
                    } else {
                        $conn->query("UPDATE materials SET stock = stock + $total_mat_change WHERE LOWER(TRIM(name)) = LOWER('$mat_esc')");
                    }
                }
            }
        }
    }
    
    if (!empty($_FILES['image']['name'])) {
        $image = $_FILES['image']['name'];
        $target = "uploads/" . basename($image);
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
        $sql = "UPDATE products SET name='$name', category='$category', description='$description', price='$price', stock='$new_stock', reorder_level='$reorder', image='$image' WHERE product_id=$id";
    } else {
        $sql = "UPDATE products SET name='$name', category='$category', description='$description', price='$price', stock='$new_stock', reorder_level='$reorder' WHERE product_id=$id";
    }

    if ($conn->query($sql) === TRUE) {
        header("Location: admin_products.php?msg=Product updated successfully");
        exit();
    }
}

// --- 3. LOGIC PARA SA PAG-ADD ---
if (isset($_POST['add_product'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = $_POST['price'];
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    
    $stock = 0; 
    
    if (!file_exists('uploads')) { mkdir('uploads', 0777, true); }
    $image = $_FILES['image']['name'];
    $target = "uploads/" . basename($image);

    // FIXED: hindi tugma dati ang column list sa values (5 columns, 6 values, at literal
    // na 'category' ang nakalagay) kaya palaging pumapalya ang pag-add ng bagong product.
    $reorder = max(0, intval($_POST['reorder_level'] ?? 10));
    $sql = "INSERT INTO products (name, category, description, price, stock, reorder_level, image)
            VALUES ('$name', '$category', '$description', '$price', '$stock', '$reorder', '$image')";
    
    if ($conn->query($sql) === TRUE) {
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
        $new_prod_id = $conn->insert_id;

        if (isset($_POST['item_selection']) && isset($_POST['item_qty'])) {
            foreach ($_POST['item_selection'] as $index => $selection) {
                $qty = floatval($_POST['item_qty'][$index]);
                if (!empty($selection) && $qty > 0) {
                    $parts = explode('|', $selection);
                    $type = $parts[0];
                    if ($type === 'flower') {
                        $item_name = $conn->real_escape_string($parts[1]);
                        $conn->query("INSERT INTO product_materials (product_id, item_type, item_name, material_id, qty) VALUES ($new_prod_id, 'flower', '$item_name', NULL, $qty)");
                    } elseif ($type === 'material') {
                        $mat_id = intval($parts[1]);
                        $item_name = $conn->real_escape_string($parts[2]);
                        $conn->query("INSERT INTO product_materials (product_id, item_type, item_name, material_id, qty) VALUES ($new_prod_id, 'material', '$item_name', $mat_id, $qty)");
                    }
                }
            }
        }
        $message = "Product added successfully! Stock is currently 0. Please edit to add stocks and deduct materials.";
    }
}

// --- BAGONG LOGIC: CLEAR ORDER RECORDS NG ISANG PRODUCT ---
if (isset($_GET['clear_orders'])) {
    $id = intval($_GET['clear_orders']);
    $conn->query("DELETE FROM order_details WHERE product_id = $id");
    echo "<script>alert('Order records cleared successfully! You can now delete the product.'); window.location.href='admin_products.php';</script>";
    exit();
}

// --- 4. LOGIC PARA SA DELETE (MAY POPUP WARNING NA) ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Check ulit sa backend kung may orders pa bago mag-delete para safe
    $check_orders = $conn->query("SELECT COUNT(*) as total_orders FROM order_details WHERE product_id = $id");
    $order_count = $check_orders ? $check_orders->fetch_assoc()['total_orders'] : 0;

    if ($order_count > 0) {
        echo "<script>alert('This product cannot be deleted because it has linked orders (pending/completed). Please click the \"Clear Records\" button first.'); window.location.href='admin_products.php';</script>";
        exit();
    } else {
        $conn->query("DELETE FROM product_materials WHERE product_id = $id");
        $conn->query("DELETE FROM products WHERE product_id = $id");
        header("Location: admin_products.php");
        exit();
    }
}

// In-update ang query para bilangin agad kung may order ang bawat product
$products = $conn->query("
    SELECT p.*, 
    (SELECT COUNT(*) FROM order_details od WHERE od.product_id = p.product_id) as order_count 
    FROM products p ORDER BY p.product_id DESC
");

$edit_data = null;
$edit_materials = [];
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM products WHERE product_id = $edit_id");
    if ($res && $res->num_rows > 0) {
        $edit_data = $res->fetch_assoc();

        $pm_req = $conn->query("SELECT * FROM product_materials WHERE product_id = $edit_id");
        if ($pm_req) {
            while ($pm = $pm_req->fetch_assoc()) {
                $edit_materials[] = $pm;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Management | Vinescraft Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
        :root { --coral: #f18973; --peach: #fce0d8; --text: #444; }
        
        body { font-family: 'Montserrat', sans-serif; background: #fffafb; margin: 0; display: flex; color: var(--text); }
        
        /* BADGE CSS PARA SA SIDEBAR */
        .msg-badge { background: #ff4757; color: white; padding: 2px 6px; border-radius: 50px; font-size: 10px; font-weight: 900; margin-left: 5px; vertical-align: top; }

        /* SIDEBAR STYLE */
        .sidebar { width: 260px; background: white; height: 100vh; border-right: 1px solid var(--peach); padding: 30px 20px; position: fixed; }
        .sidebar h1 { color: var(--coral); font-size: 20px; font-weight: 900; text-transform: lowercase; margin-bottom: 40px; }
        
        .nav-links { display: flex; flex-direction: column; gap: 10px; }
        .nav-links a { text-decoration: none; color: #888; font-size: 13px; font-weight: 700; padding: 12px 20px; border-radius: 10px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: var(--peach); color: var(--coral); }
        .nav-links a.logout { margin-top: 20px; color: #ff4757; }

        /* MAIN CONTENT */
        .main-content { margin-left: 300px; padding: 40px; width: calc(100% - 340px); }
        h2 { font-size: 32px; font-weight: 900; color: var(--coral); margin: 0 0 10px; }

        .back-container { margin-bottom: 25px; }
        .back-btn { text-decoration: none; color: var(--coral); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }

        /* FORM & BOX STYLES */
        .content-box { background: white; padding: 30px; border-radius: 20px; border: 1px solid var(--peach); margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .content-box h3 { font-size: 14px; text-transform: uppercase; color: var(--coral); margin-top: 0; margin-bottom: 20px; }
        
        input[type="text"], input[type="number"], textarea, input[type="file"], select {
            width: 100%; padding: 12px; border: 1px solid var(--peach); border-radius: 10px; font-family: 'Montserrat', sans-serif; margin-bottom: 15px; box-sizing: border-box; outline: none;
        }
        
        .btn-submit { background: var(--coral); color: white; border: none; padding: 12px 25px; border-radius: 50px; font-weight: 700; font-size: 12px; text-transform: uppercase; cursor: pointer; transition: 0.3s; margin-top: 10px; }
        .btn-submit:hover { background: #e07661; transform: translateY(-2px); }

        .btn-add-mat { background: var(--peach); color: var(--coral); border: none; padding: 8px 15px; border-radius: 8px; font-weight: 700; font-size: 10px; cursor: pointer; transition: 0.3s; margin-bottom: 15px; display: inline-block; }
        .btn-add-mat:hover { background: var(--coral); color: white; }

        .mat-row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }

        /* TABLE STYLES */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; color: #bbb; padding: 15px 10px; border-bottom: 1px solid var(--peach); letter-spacing: 1px; }
        td { padding: 15px 10px; font-size: 13px; border-bottom: 1px solid #fff5f8; }
        
        .prod-img { width: 60px; height: 60px; object-fit: cover; border-radius: 10px; border: 1px solid var(--peach); }
        .badge-featured { font-size: 9px; font-weight: 900; padding: 4px 10px; border-radius: 50px; background: #e8f5e9; color: #2e7d32; text-transform: uppercase; }
        /* Sariling class - inooverride ng .status-badge sa floral-theme.css ang inline colors gamit ang !important */
        .stock-badge { display: inline-block; min-width: 28px; text-align: center; padding: 5px 10px; border-radius: 50px; font-size: 11px; font-weight: 900; }
        .action-link { text-decoration: none; color: var(--coral); font-weight: 700; font-size: 11px; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

<?php include 'admin_sidebar.php' ?>

    <div class="main-content">
        <div class="back-container">
            <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <h2>Product List</h2>

        <div class="content-box">
            <?php if ($edit_data): ?>
                <h3>Edit Product: <?php echo htmlspecialchars($edit_data['name']); ?></h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" value="<?php echo $edit_data['product_id']; ?>">
                    <input type="text" name="name" value="<?php echo htmlspecialchars($edit_data['name']); ?>" placeholder="Product Name" required>
                    
                    <!-- CATEGORY DROPDOWN PARA SA EDIT -->
                    <select name="category" required style="margin-bottom: 15px;">
                        <option value="Bouquet" <?php echo (isset($edit_data['category']) && $edit_data['category'] == 'Bouquet') ? 'selected' : ''; ?>>Bouquet</option>
                        <option value="Shirt" <?php echo (isset($edit_data['category']) && $edit_data['category'] == 'Shirt') ? 'selected' : ''; ?>>Shirt</option>
                        <option value="Crafts" <?php echo (isset($edit_data['category']) && $edit_data['category'] == 'Crafts') ? 'selected' : ''; ?>>Crafts</option>
                        <option value="Others" <?php echo (!isset($edit_data['category']) || $edit_data['category'] == 'Others') ? 'selected' : ''; ?>>Others</option>
                    </select>

                    <textarea name="description" placeholder="Description" rows="3" required><?php echo htmlspecialchars($edit_data['description']); ?></textarea>
                    
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <input type="number" step="0.01" name="price" value="<?php echo $edit_data['price']; ?>" placeholder="Price (₱)" required>
                        <div style="flex: 1; border: 1px solid var(--peach); padding: 5px 12px; border-radius: 10px; background: #fff;">
                            <span style="font-size: 10px; color: #aaa; text-transform: uppercase; font-weight: 700;">Current Stock: <?php echo $edit_data['stock']; ?></span>
                            <input type="number" name="stock_to_add" value="0" placeholder="Enter stock to add..." required style="border: none; padding: 5px 0 0 0; margin: 0; outline: none; width: 100%; font-family: 'Montserrat', sans-serif;">
                        </div>
                    </div>
                    <p style="font-size:10px; color:#aaa; margin-top:5px; margin-bottom:15px;">*Input a positive number to add stock (deducts materials) or a negative number to reduce stock (returns materials).</p>

                    <label style="font-size: 11px; color: #888; display: block; margin-bottom: 5px;">Re-order Level (alerts when stock falls to this, 0 to mute):</label>
                    <input type="number" name="reorder_level" min="0" value="<?php echo isset($edit_data['reorder_level']) ? intval($edit_data['reorder_level']) : 10; ?>" required style="margin-bottom: 15px;">

                    <div style="background: var(--bg); padding: 15px; border-radius: 10px; border: 1px dashed var(--peach); margin-bottom: 15px;">
                        <label style="font-size: 11px; font-weight: 700; color: var(--coral); display: block; margin-bottom: 10px; text-transform: uppercase;">Composition per 1 Product</label>
                        <div id="mat-container-edit"></div>
                        <button type="button" class="btn-add-mat" onclick="addMaterialRow('mat-container-edit')">+ Add Flower or Material</button>
                    </div>

                    <label style="font-size: 11px; color: #888; display: block; margin-bottom: 5px;">Change Product Image:</label>
                    <input type="file" name="image">
                    
                    <button type="submit" name="save_update" class="btn-submit">Save Changes</button>
                    <a href="admin_products.php" style="font-size: 11px; margin-left: 15px; color: #888; text-decoration: none;">Cancel</a>
                </form>
            <?php else: ?>
                <h3>Add New Product</h3>
                <?php if ($message) echo "<p style='color: #2ed573; font-size: 12px; font-weight: 700; background: #e8f8f5; padding: 10px; border-radius: 10px;'>$message</p>"; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="text" name="name" placeholder="Product Name" required>
                    <select name="category" required>
                        <option value="Bouquet" <?php echo (isset($edit_data['category']) && $edit_data['category'] == 'Bouquet') ? 'selected' : ''; ?>>Bouquet</option>
                        <option value="Shirt" <?php echo (isset($edit_data['category']) && $edit_data['category'] == 'Shirt') ? 'selected' : ''; ?>>Shirt</option>
                        <option value="Crafts" <?php echo (isset($edit_data['category']) && $edit_data['category'] == 'Crafts') ? 'selected' : ''; ?>>Crafts</option>
                        <option value="Others" <?php echo (!isset($edit_data['category']) || $edit_data['category'] == 'Others') ? 'selected' : ''; ?>>Others</option>
                    </select>
                    <textarea name="description" placeholder="Description" rows="3" required></textarea>
                    <input type="number" step="0.01" name="price" placeholder="Price (₱)" required>
                    
                    <label style="font-size: 11px; color: #888; display: block; margin-bottom: 5px;">Re-order Level (alerts when stock falls to this, 0 to mute):</label>
                    <input type="number" name="reorder_level" min="0" value="10" required>

                    <p style="font-size: 11px; color: var(--coral); font-weight: 700; background: var(--peach); padding: 10px; border-radius: 8px;">
                        *Initial stock will be set to 0. You can increase it later by clicking Edit, which will automatically deduct the needed materials.
                    </p>

                    <div style="background: var(--bg); padding: 15px; border-radius: 10px; border: 1px dashed var(--peach); margin-bottom: 15px;">
                        <label style="font-size: 11px; font-weight: 700; color: var(--coral); display: block; margin-bottom: 10px; text-transform: uppercase;">Composition per 1 Product</label>
                        <div id="mat-container-add"></div>
                        <button type="button" class="btn-add-mat" onclick="addMaterialRow('mat-container-add')">+ Add Flower or Material</button>
                    </div>

                    <input type="file" name="image" required>
                    <button type="submit" name="add_product" class="btn-submit">Save New Product</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="content-box">
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product Details</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Re-order</th>
                        <th>Featured</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products): while($row = $products->fetch_assoc()): ?>
                    <tr>
                        <td><img src="uploads/<?php echo $row['image']; ?>" class="prod-img"></td>
                        <td>
                            <strong style="color: var(--coral);"><?php echo htmlspecialchars($row['name']); ?></strong><br>
                            <small style="color: #aaa; font-size: 10px;"><?php echo substr(htmlspecialchars($row['description']), 0, 40); ?>...</small>
                        </td>
                        <td>
                            <span style="background: var(--peach); color: var(--coral); padding: 4px 10px; border-radius: 50px; font-size: 10px; font-weight: 900; text-transform: uppercase;">
                                <?php echo htmlspecialchars($row['category'] ?? 'Others'); ?>
                            </span>
                        </td>
                        <td style="font-weight: 700;">₱<?php echo number_format($row['price'], 2); ?></td>
                        <?php $p_badge = stock_badge(stock_state($row['stock'], $row['reorder_level'] ?? 0)); ?>
                        <td>
                            <span class="stock-badge" style="background:<?php echo $p_badge['bg']; ?>; color:<?php echo $p_badge['color']; ?>;" title="<?php echo $p_badge['text']; ?>"><?php echo $row['stock']; ?></span>
                        </td>
                        <td style="color:#bbb; font-size:12px;"><?php echo intval($row['reorder_level'] ?? 0); ?></td>
                        <td>
                            <?php if ($row['is_featured'] == 1): ?>
                                <span class="badge-featured">⭐ Featured</span><br>
                                <a href="admin_products.php?toggle_featured=<?php echo $row['product_id']; ?>&status=1" style="font-size: 9px; color: #888;">Remove</a>
                            <?php else: ?>
                                <a href="admin_products.php?toggle_featured=<?php echo $row['product_id']; ?>&status=0" style="font-size: 9px; color: var(--coral); text-decoration: none; font-weight: 700;">Set Featured</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="admin_products.php?edit=<?php echo $row['product_id']; ?>" class="action-link">Edit / Add Stock</a> | 
                            
                            <?php if ($row['order_count'] > 0): ?>
                                <a href="admin_products.php?clear_orders=<?php echo $row['product_id']; ?>" class="action-link" style="color: #e67e22;" onclick="return confirm('WARNING: Are you sure you want to clear <?php echo $row['order_count']; ?> order record(s) for this product? This will affect the transaction history.')">Clear Records (<?php echo $row['order_count']; ?>)</a> |
                                <a href="javascript:void(0);" class="action-link" style="color: #ff4757; opacity: 0.4; cursor: not-allowed;" onclick="alert('This product cannot be deleted because it has <?php echo $row['order_count']; ?> linked order(s). Click \'Clear Records\' first if you really want to remove it.')">Delete</a>
                            <?php else: ?>
                                <a href="admin_products.php?delete=<?php echo $row['product_id']; ?>" class="action-link" style="color: #ff4757;" onclick="return confirm('Are you sure you want to delete this product? The linked material recipe will also be removed.')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const defaultFlowers = <?php echo json_encode($default_flowers); ?>;
        const dynamicFlowers = <?php echo json_encode($dynamic_flowers); ?>;
        const materialsList = <?php echo json_encode($materials_list); ?>;
        
        function addMaterialRow(containerId, itemType = '', matId = '', itemName = '', qty = '') {
            let options = '<option value="">Select Flower or Material...</option>';
            
            options += '<optgroup label="Default Flowers & Fillers">';
            defaultFlowers.forEach(f => {
                let val = 'flower|' + f;
                let selected = (itemType === 'flower' && itemName === f) ? 'selected' : '';
                options += `<option value="${val}" ${selected}>${f}</option>`;
            });
            options += '</optgroup>';

            if (dynamicFlowers && dynamicFlowers.length > 0) {
                options += '<optgroup label="Custom Flowers">';
                dynamicFlowers.forEach(f => {
                    let val = 'flower|' + f;
                    let selected = (itemType === 'flower' && itemName === f) ? 'selected' : '';
                    options += `<option value="${val}" ${selected}>${f}</option>`;
                });
                options += '</optgroup>';
            }

            options += '<optgroup label="Raw Materials">';
            materialsList.forEach(m => {
                let val = 'material|' + m.material_id + '|' + m.name;
                let selected = (itemType === 'material' && matId == m.material_id) ? 'selected' : '';
                options += `<option value="${val}" ${selected}>${m.name} (Stock: ${m.stock})</option>`;
            });
            options += '</optgroup>';
            
            let row = document.createElement('div');
            row.className = 'mat-row';
            row.innerHTML = `
                <select name="item_selection[]" required style="flex:2; margin-bottom:0;">${options}</select>
                <input type="number" step="0.01" name="item_qty[]" value="${qty}" placeholder="Qty Needed" required style="flex:1; margin-bottom:0;">
                <button type="button" onclick="this.parentElement.remove()" style="background:#ff4757; color:white; border:none; border-radius:8px; padding:10px 15px; cursor:pointer; font-weight:bold;">X</button>
            `;
            document.getElementById(containerId).appendChild(row);
        }

        <?php if ($edit_data && count($edit_materials) > 0): ?>
            <?php foreach ($edit_materials as $pm): ?>
                addMaterialRow('mat-container-edit', '<?php echo $pm['item_type']; ?>', '<?php echo $pm['material_id']; ?>', '<?php echo addslashes($pm['item_name']); ?>', '<?php echo $pm['qty']; ?>');
            <?php endforeach; ?>
        <?php endif; ?>
    </script>

</body>
</html>