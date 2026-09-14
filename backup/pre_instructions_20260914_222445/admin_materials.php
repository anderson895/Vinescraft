<?php
session_start();
require_once 'db_connect.php';

// FETCH: Kunin yung total na unread para sa Sidebar Badge
$unread_total_query = $conn->query("SELECT COUNT(*) as unread FROM messages WHERE sender_type = 'user' AND is_read = 0");
$unread_count = $unread_total_query ? $unread_total_query->fetch_assoc()['unread'] : 0;

// PROTECTION: Sinisiguro na admin lang ang may access
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// --- BAGO: AUTO-CREATE LOGS TABLE PARA SA INVENTORY HISTORY ---
$check_log_table = $conn->query("SHOW TABLES LIKE 'material_logs'");
if ($check_log_table && $check_log_table->num_rows == 0) {
    $conn->query("CREATE TABLE material_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        material_name VARCHAR(100),
        action_type VARCHAR(50),
        qty_changed DECIMAL(10,2),
        new_stock DECIMAL(10,2),
        log_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// --- BAGO: AUTO-UPDATE DATABASE TRIGGER (FIXED INACCURATE QTY LOGS) ---
// I-drop muna natin yung lumang trigger para ma-force ang database na gamitin yung tamang computation.
$conn->query("DROP TRIGGER IF EXISTS after_material_update");

$trigger_sql = "
CREATE TRIGGER after_material_update 
AFTER UPDATE ON materials
FOR EACH ROW 
BEGIN
    IF NEW.stock <> OLD.stock THEN
        INSERT INTO material_logs (material_name, action_type, qty_changed, new_stock)
        VALUES (
            NEW.name, 
            IF(NEW.stock > OLD.stock, 'Restock', 'Deduction'), 
            ABS(NEW.stock - OLD.stock), 
            NEW.stock
        );
    END IF;
END;";
$conn->query($trigger_sql);

// --- AJAX LOGIC PARA SA LIVE UPDATE (NO REFRESH) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_update_stock'])) {
    header('Content-Type: application/json');
    $mat_id = intval($_POST['material_id']);
    $new_stock = floatval($_POST['new_stock']); 

    // STRICT RULE: Bawal bumaba sa 0 ang quantity. If negative, force it to 0.
    if ($new_stock < 0) {
        $new_stock = 0;
    }

    $stmt = $conn->prepare("UPDATE materials SET stock = ? WHERE material_id = ?");
    $stmt->bind_param("di", $new_stock, $mat_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit(); 
}

// --- CRUD LOGIC PARA SA ADD, EDIT, DELETE ---

// ADD MATERIAL
if (isset($_POST['add_material'])) {
    $name = trim($_POST['m_name']);
    $category = trim($_POST['m_category']);
    $stock = floatval($_POST['m_stock']);
    if ($stock < 0) $stock = 0; // Prevent negative inputs
    
    $stmt = $conn->prepare("INSERT INTO materials (name, category, stock) VALUES (?, ?, ?)");
    $stmt->bind_param("ssd", $name, $category, $stock);
    $stmt->execute();
    $_SESSION['msg'] = "Material added successfully!";
    header("Location: admin_materials.php");
    exit();
}

// EDIT MATERIAL
if (isset($_POST['edit_material'])) {
    $id = intval($_POST['m_id']);
    $name = trim($_POST['m_name']);
    $category = trim($_POST['m_category']);
    
    $stmt = $conn->prepare("UPDATE materials SET name = ?, category = ? WHERE material_id = ?");
    $stmt->bind_param("ssi", $name, $category, $id);
    $stmt->execute();
    $_SESSION['msg'] = "Material details updated!";
    header("Location: admin_materials.php");
    exit();
}

// DELETE MATERIAL
if (isset($_POST['delete_material'])) {
    $id = intval($_POST['delete_id']);
    $in_use = false;
    
    // Check kung may gumagamit na product sa material gamit ang cross-reference table mo
    $check_table = $conn->query("SHOW TABLES LIKE 'product_materials'");
    if ($check_table && $check_table->num_rows > 0) {
        $check_usage = $conn->query("SELECT * FROM product_materials WHERE material_id = $id");
        if ($check_usage && $check_usage->num_rows > 0) {
            $in_use = true;
        }
    }
    
    if ($in_use) {
        $_SESSION['msg'] = "ERROR: Bawal iremove yung material kasi ginagamit pa ito sa isang product!";
    } else {
        $conn->query("DELETE FROM materials WHERE material_id = $id");
        $_SESSION['msg'] = "Material removed successfully.";
    }
    header("Location: admin_materials.php");
    exit();
}

// INAYOS NA QUERIES: Ginawa nating case-insensitive para sure na lalabas kahit ano pang caps ng text
$bouquet_materials = $conn->query("SELECT * FROM materials WHERE LOWER(category) = 'bouquet' ORDER BY name ASC");
$shirt_materials = $conn->query("SELECT * FROM materials WHERE LOWER(category) = 'shirt' ORDER BY name ASC");

// Heto ang kukuha sa lahat ng bagong materials na ginawa natin (Sticker Paper, Glass, atbp.)
$other_materials = $conn->query("SELECT * FROM materials WHERE LOWER(category) NOT IN ('bouquet', 'shirt') OR category IS NULL OR category = '' ORDER BY name ASC");

// Fetch History Logs
$history_logs = $conn->query("SELECT * FROM material_logs ORDER BY log_date DESC LIMIT 50");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Materials | Vinescraft Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
        :root { --coral: #f18973; --peach: #fce0d8; --text: #444; }
        
        body { font-family: 'Montserrat', sans-serif; background: #fffafb; margin: 0; display: flex; color: var(--text); }
        
        /* BADGE CSS PARA SA SIDEBAR */
        .msg-badge { background: #ff4757; color: white; padding: 2px 6px; border-radius: 50px; font-size: 10px; font-weight: 900; margin-left: 5px; vertical-align: top; }

        /* SIDEBAR STYLE */
        .sidebar { width: 260px; background: white; height: 100vh; border-right: 1px solid var(--peach); padding: 30px 20px; position: fixed; overflow-y: auto; }
        .sidebar h1 { color: var(--coral); font-size: 20px; font-weight: 900; text-transform: lowercase; margin-bottom: 40px; }
        
        .nav-links { display: flex; flex-direction: column; gap: 10px; }
        .nav-links a { text-decoration: none; color: #888; font-size: 13px; font-weight: 700; padding: 12px 20px; border-radius: 10px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: var(--peach); color: var(--coral); }
        .nav-links a.logout { margin-top: 20px; color: #ff4757; }

        /* MAIN CONTENT AREA */
        .main-content { margin-left: 300px; padding: 40px; width: calc(100% - 340px); }
        .header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        h2 { font-size: 32px; font-weight: 900; color: var(--coral); margin: 0; }

        .back-container { margin-bottom: 25px; }
        .back-btn { text-decoration: none; color: var(--coral); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }

        /* BUTTONS */
        .btn-add { background: var(--coral); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-add:hover { opacity: 0.8; }
        .btn-action { border: none; padding: 8px 12px; border-radius: 5px; font-weight: 700; font-size: 11px; cursor: pointer; color: white; text-transform: uppercase; }
        .btn-save { background: #2ed573; }
        .btn-edit { background: #f1c40f; color: #333; }
        .btn-delete { background: #ff4757; }

        /* TABLES */
        .content-box { background: white; padding: 30px; border-radius: 20px; border: 1px solid var(--peach); margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .content-box h3 { font-size: 14px; text-transform: uppercase; color: var(--coral); margin-top: 0; margin-bottom: 20px; }
        
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .data-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #bbb; padding: 15px 10px; border-bottom: 2px solid var(--peach); }
        .data-table td { padding: 15px 10px; font-size: 13px; border-bottom: 1px solid #fff5f8; vertical-align: middle; }

        /* STOCK INPUT CONTROLS */
        .stock-input-group { display: flex; align-items: center; gap: 10px; }
        .stock-input { width: 80px; padding: 8px 12px; border: 2px solid #eee; border-radius: 8px; font-family: 'Montserrat'; font-weight: 700; font-size: 13px; outline: none; transition: 0.3s; }
        .stock-input:focus { border-color: var(--coral); }
        .stock-warning { border-color: #ff4757; color: #ff4757; background: #fff0f1; }

        /* MODAL */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 30px; border-radius: 15px; width: 350px; }
        .close-btn { float: right; cursor: pointer; font-weight: 900; color: #aaa; }
        .modal input, .modal select { width: 100%; padding: 10px; margin-top: 5px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        
        /* HISTORY LOGS CSS */
        .log-badge { padding: 4px 10px; border-radius: 50px; font-size: 9px; font-weight: 900; text-transform: uppercase; }
        .status-restock { background: #e8f8f5; color: #2ed573; border: 1px solid #2ed573; }
        .status-deduct { background: #ffebee; color: #ff4757; border: 1px solid #ff4757; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

    <?php if (isset($_SESSION['msg'])): ?>
        <script>alert("<?php echo $_SESSION['msg']; ?>");</script>
        <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>

<?php include 'admin_sidebar.php' ?>

    <div class="main-content">
        <div class="back-container">
            <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <div class="header-row">
            <h2>Material Inventory</h2>
            <button class="btn-add" onclick="openModal('addModal')">+ Add New Material</button>
        </div>

        <div class="content-box">
            <h3>Bouquet Materials</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Stock</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if($bouquet_materials && $bouquet_materials->num_rows > 0): while($row = $bouquet_materials->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                            <td>
                                <div class="stock-input-group">
                                    <input type="number" step="0.01" min="0" oninput="if(parseFloat(value)<0) value='0';" id="stock-<?php echo $row['material_id']; ?>" 
                                           class="stock-input <?php echo ($row['stock'] <= 5) ? 'stock-warning' : ''; ?>" 
                                           value="<?php echo $row['stock']; ?>">
                                </div>
                            </td>
                            <td>
                                <button class="btn-action btn-save" onclick="updateStock(<?php echo $row['material_id']; ?>, this)">Save</button>
                                <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $row['material_id']; ?>, '<?php echo addslashes($row['name']); ?>', '<?php echo addslashes($row['category']); ?>')">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Sigurado ka bang gusto mo burahin ito?');">
                                    <input type="hidden" name="delete_id" value="<?php echo $row['material_id']; ?>">
                                    <button type="submit" name="delete_material" class="btn-action btn-delete">Remove</button>
                               </form>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="3" style="text-align: center; color: #aaa; padding:15px;">No bouquet materials.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="content-box">
            <h3>T-Shirt Print Materials</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Stock</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if($shirt_materials && $shirt_materials->num_rows > 0): while($row = $shirt_materials->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                            <td>
                                <div class="stock-input-group">
                                    <input type="number" step="0.01" min="0" oninput="if(parseFloat(value)<0) value='0';" id="stock-<?php echo $row['material_id']; ?>" 
                                           class="stock-input <?php echo ($row['stock'] <= 5) ? 'stock-warning' : ''; ?>" 
                                           value="<?php echo $row['stock']; ?>">
                                </div>
                            </td>
                            <td>
                                <button class="btn-action btn-save" onclick="updateStock(<?php echo $row['material_id']; ?>, this)">Save</button>
                                <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $row['material_id']; ?>, '<?php echo addslashes($row['name']); ?>', '<?php echo addslashes($row['category']); ?>')">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Sigurado ka bang gusto mo burahin ito?');">
                                    <input type="hidden" name="delete_id" value="<?php echo $row['material_id']; ?>">
                                    <button type="submit" name="delete_material" class="btn-action btn-delete">Remove</button>
                               </form>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="3" style="text-align: center; color: #aaa; padding:15px;">No shirt materials.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="content-box" style="border-color: #f1c40f;">
            <h3 style="color: #d35400;">Product Materials (Crafts, Packaging & Accessories)</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Category</th><th>Stock</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if($other_materials && $other_materials->num_rows > 0): while($row = $other_materials->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                            <td style="font-size:11px; color:#888; text-transform: uppercase;"><?php echo htmlspecialchars($row['category']); ?></td>
                            <td>
                                <div class="stock-input-group">
                                    <input type="number" step="0.01" min="0" oninput="if(parseFloat(value)<0) value='0';" id="stock-<?php echo $row['material_id']; ?>" 
                                           class="stock-input <?php echo ($row['stock'] <= 5) ? 'stock-warning' : ''; ?>" 
                                           value="<?php echo $row['stock']; ?>">
                                </div>
                            </td>
                            <td>
                                <button class="btn-action btn-save" onclick="updateStock(<?php echo $row['material_id']; ?>, this)">Save</button>
                                <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $row['material_id']; ?>, '<?php echo addslashes($row['name']); ?>', '<?php echo addslashes($row['category']); ?>')">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Sigurado ka bang gusto mo burahin ito?');">
                                    <input type="hidden" name="delete_id" value="<?php echo $row['material_id']; ?>">
                                    <button type="submit" name="delete_material" class="btn-action btn-delete">Remove</button>
                               </form>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="4" style="text-align: center; color: #aaa; padding: 20px;">Wala pang ibang materials dito. Lumabas dapat ang mga sinave mo sa SQL dito.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="content-box">
            <h3>Materials Stock History</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Material Name</th>
                            <th>Action</th>
                            <th>Qty Changed</th>
                            <th>Ending Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($history_logs && $history_logs->num_rows > 0): while($log = $history_logs->fetch_assoc()): ?>
                            <?php 
                                $is_restock = ($log['action_type'] == 'Restock');
                                $badge_class = $is_restock ? 'status-restock' : 'status-deduct';
                                $sign = $is_restock ? '+' : '-';
                                $color = $is_restock ? '#2ed573' : '#ff4757';
                            ?>
                            <tr>
                                <td style="color:#aaa; font-size: 11px;"><?php echo date('M d, Y - h:i A', strtotime($log['log_date'])); ?></td>
                                <td><strong style="color: var(--text);"><?php echo htmlspecialchars($log['material_name']); ?></strong></td>
                                <td><span class="log-badge <?php echo $badge_class; ?>"><?php echo $log['action_type']; ?></span></td>
                                <td style="font-weight: 900; color: <?php echo $color; ?>;"><?php echo $sign . floatval($log['qty_changed']); ?></td>
                                <td style="font-weight: 700;"><?php echo floatval($log['new_stock']); ?></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" style="text-align: center; color: #aaa; padding: 40px;">No stock changes recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('addModal')">&times;</span>
            <h3 style="color:var(--coral); margin-top:0;">Add New Material</h3>
            <form method="POST">
                <label>Material Name:</label>
                <input type="text" name="m_name" required placeholder="e.g. Cardstock">
                <label>Category:</label>
                <input type="text" name="m_category" required placeholder="e.g. Crafts, Packaging, Accessories">
                <label>Initial Stock:</label>
                <input type="number" name="m_stock" step="0.01" min="0" oninput="if(parseFloat(value)<0) value='0';" required value="0">
                <button type="submit" name="add_material" class="btn-add" style="width:100%;">Add Material</button>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('editModal')">&times;</span>
            <h3 style="color:var(--coral); margin-top:0;">Edit Material Info</h3>
            <form method="POST">
                <input type="hidden" name="m_id" id="edit_id">
                <label>Material Name:</label>
                <input type="text" name="m_name" id="edit_name" required>
                <label>Category:</label>
                <input type="text" name="m_category" id="edit_category" required>
                <button type="submit" name="edit_material" class="btn-add" style="width:100%; background:#f1c40f; color:#333;">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function openEditModal(id, name, category) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_category').value = category;
            openModal('editModal');
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('addModal')) closeModal('addModal');
            if (event.target == document.getElementById('editModal')) closeModal('editModal');
        }

        function updateStock(materialId, btn) {
            const input = document.getElementById('stock-' + materialId);
            let newStock = parseFloat(input.value);
            const originalText = btn.innerText;

            if (newStock < 0) {
                newStock = 0;
                input.value = 0;
            }

            btn.innerText = 'Wait...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('ajax_update_stock', '1');
            formData.append('material_id', materialId);
            formData.append('new_stock', newStock);

            fetch('admin_materials.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    btn.innerText = 'Saved!';
                    if (parseFloat(input.value) <= 5) {
                        input.classList.add('stock-warning');
                    } else {
                        input.classList.remove('stock-warning');
                    }
                    setTimeout(() => { location.reload(); }, 500);
                } else {
                    alert('Error: ' + data.error);
                    btn.innerText = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                alert('Connection error. Please try again.');
                btn.innerText = originalText;
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>