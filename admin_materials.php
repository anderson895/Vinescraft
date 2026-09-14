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
    $new_reorder = floatval($_POST['new_reorder'] ?? 0);

    // STRICT RULE: Bawal bumaba sa 0 ang quantity. If negative, force it to 0.
    if ($new_stock < 0) {
        $new_stock = 0;
    }
    if ($new_reorder < 0) {
        $new_reorder = 0;
    }

    // Hindi nag-lo-log ang after_material_update trigger kapag ang reorder_level lang
    // ang nabago (NEW.stock <> OLD.stock ang condition nito), kaya safe itong isabay.
    $stmt = $conn->prepare("UPDATE materials SET stock = ?, reorder_level = ? WHERE material_id = ?");
    $stmt->bind_param("ddi", $new_stock, $new_reorder, $mat_id);

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
    $reorder = floatval($_POST['m_reorder'] ?? 100);
    if ($reorder < 0) $reorder = 0;

    $stmt = $conn->prepare("INSERT INTO materials (name, category, stock, reorder_level) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssdd", $name, $category, $stock, $reorder);
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
    $reorder = floatval($_POST['m_reorder'] ?? 0);
    if ($reorder < 0) $reorder = 0;

    $stmt = $conn->prepare("UPDATE materials SET name = ?, category = ?, reorder_level = ? WHERE material_id = ?");
    $stmt->bind_param("ssdi", $name, $category, $reorder, $id);
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

/**
 * Isang row ng material table. Iisa ang renderer para sa tatlong table sa ibaba
 * para hindi mag-iba-iba ang stock/re-order logic sa bawat isa.
 */
function render_material_row($row, $show_category = false) {
    $state = stock_state($row['stock'], $row['reorder_level']);
    $badge = stock_badge($state);
    $cls = $state === 'out' ? 'stock-danger' : ($state === 'low' ? 'stock-warning' : '');
    $id = $row['material_id'];
    ?>
    <tr>
        <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
        <?php if ($show_category): ?>
            <td style="font-size:11px; color:#888; text-transform: uppercase;"><?php echo htmlspecialchars($row['category']); ?></td>
        <?php endif; ?>
        <td>
            <div class="stock-input-group">
                <input type="number" step="0.01" min="0" oninput="if(parseFloat(value)<0) value='0';" id="stock-<?php echo $id; ?>"
                       class="stock-input <?php echo $cls; ?>"
                       value="<?php echo fmt_stock($row['stock']); ?>">
            </div>
        </td>
        <td>
            <input type="number" step="0.01" min="0" oninput="if(parseFloat(value)<0) value='0';" id="reorder-<?php echo $id; ?>"
                   class="stock-input reorder-input" value="<?php echo fmt_stock($row['reorder_level']); ?>"
                   title="Kapag ang stock ay nasa o mas mababa pa dito, mag-aalerto ang dashboard. Ilagay ang 0 para i-mute.">
        </td>
        <td>
            <span class="stock-badge" style="background:<?php echo $badge['bg']; ?>; color:<?php echo $badge['color']; ?>;"><?php echo $badge['text']; ?></span>
        </td>
        <td>
            <button class="btn-action btn-save" onclick="updateStock(<?php echo $id; ?>, this)">Save</button>
            <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $id; ?>, '<?php echo addslashes($row['name']); ?>', '<?php echo addslashes($row['category']); ?>', <?php echo floatval($row['reorder_level']); ?>)">Edit</button>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Sigurado ka bang gusto mo burahin ito?');">
                <input type="hidden" name="delete_id" value="<?php echo $id; ?>">
                <button type="submit" name="delete_material" class="btn-action btn-delete">Remove</button>
            </form>
        </td>
    </tr>
    <?php
}
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
        .stock-input { width: 105px; padding: 8px 12px; border: 2px solid #eee; border-radius: 8px; font-family: 'Montserrat'; font-weight: 700; font-size: 13px; outline: none; transition: 0.3s; box-sizing: border-box; }
        .stock-input:focus { border-color: var(--coral); }
        .stock-warning { border-color: #ff4757; color: #ff4757; background: #fff0f1; }
        .stock-danger { border-color: #c62828; color: #c62828; background: #ffebee; }
        .reorder-input { border-style: dashed; color: #888; }
        /* Sariling class - inooverride ng .status-badge sa floral-theme.css ang inline colors gamit ang !important */
        .stock-badge { display: inline-block; padding: 5px 12px; border-radius: 50px; font-size: 9px; font-weight: 900; text-transform: uppercase; white-space: nowrap; }

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
                    <thead><tr><th>Name</th><th>Stock</th><th>Re-order Level</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if($bouquet_materials && $bouquet_materials->num_rows > 0): while($row = $bouquet_materials->fetch_assoc()): ?>
                            <?php render_material_row($row); ?>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" style="text-align: center; color: #aaa; padding:15px;">No bouquet materials.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="content-box">
            <h3>T-Shirt Print Materials</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Stock</th><th>Re-order Level</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if($shirt_materials && $shirt_materials->num_rows > 0): while($row = $shirt_materials->fetch_assoc()): ?>
                            <?php render_material_row($row); ?>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" style="text-align: center; color: #aaa; padding:15px;">No shirt materials.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="content-box" style="border-color: #f1c40f;">
            <h3 style="color: #d35400;">Product Materials (Crafts, Packaging & Accessories)</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Category</th><th>Stock</th><th>Re-order Level</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if($other_materials && $other_materials->num_rows > 0): while($row = $other_materials->fetch_assoc()): ?>
                            <?php render_material_row($row, true); ?>
                        <?php endwhile; else: ?>
                            <tr><td colspan="6" style="text-align: center; color: #aaa; padding: 20px;">Wala pang ibang materials dito. Lumabas dapat ang mga sinave mo sa SQL dito.</td></tr>
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
                <label>Re-order Level:</label>
                <input type="number" name="m_reorder" step="0.01" min="0" oninput="if(parseFloat(value)<0) value='0';" required value="100">
                <small style="display:block; margin:-10px 0 15px; color:#aaa; font-size:10px;">Mag-aalerto ang dashboard kapag ang stock ay nasa o mas mababa pa dito. Ilagay ang 0 para i-mute.</small>
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
                <label>Re-order Level:</label>
                <input type="number" name="m_reorder" id="edit_reorder" step="0.01" min="0" oninput="if(parseFloat(value)<0) value='0';" required>
                <button type="submit" name="edit_material" class="btn-add" style="width:100%; background:#f1c40f; color:#333;">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function openEditModal(id, name, category, reorder) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_reorder').value = reorder;
            openModal('editModal');
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('addModal')) closeModal('addModal');
            if (event.target == document.getElementById('editModal')) closeModal('editModal');
        }

        function updateStock(materialId, btn) {
            const input = document.getElementById('stock-' + materialId);
            const reorderInput = document.getElementById('reorder-' + materialId);
            let newStock = parseFloat(input.value);
            let newReorder = parseFloat(reorderInput.value);
            const originalText = btn.innerText;

            if (newStock < 0 || isNaN(newStock)) {
                newStock = 0;
                input.value = 0;
            }
            if (newReorder < 0 || isNaN(newReorder)) {
                newReorder = 0;
                reorderInput.value = 0;
            }

            btn.innerText = 'Wait...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('ajax_update_stock', '1');
            formData.append('material_id', materialId);
            formData.append('new_stock', newStock);
            formData.append('new_reorder', newReorder);

            fetch('admin_materials.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    btn.innerText = 'Saved!';
                    // Mag-re-reload din naman sa ibaba, pero agad na i-reflect ang bagong state.
                    input.classList.remove('stock-warning', 'stock-danger');
                    if (newStock <= 0) {
                        input.classList.add('stock-danger');
                    } else if (newReorder > 0 && newStock <= newReorder) {
                        input.classList.add('stock-warning');
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