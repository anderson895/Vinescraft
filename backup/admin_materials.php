<?php
session_start();
require_once 'db_connect.php';

// PROTECTION: Sinisiguro na admin lang ang may access
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// --- BAGO: AJAX LOGIC PARA SA LIVE UPDATE (NO REFRESH) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_update_stock'])) {
    header('Content-Type: application/json');
    $mat_id = intval($_POST['material_id']);
    // Gumamit tayo ng floatval para basahin niya yung decimal stocks tulad ng 0.01
    $new_stock = floatval($_POST['new_stock']); 

    $stmt = $conn->prepare("UPDATE materials SET stock = ? WHERE material_id = ?");
    $stmt->bind_param("di", $new_stock, $mat_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit(); // Ipapahinto na ang script dito para hindi isama yung HTML sa response
}

// Fetch materials na nakahiwalay agad by category
$bouquet_materials = $conn->query("SELECT * FROM materials WHERE category = 'Bouquet' ORDER BY name ASC");
$shirt_materials = $conn->query("SELECT * FROM materials WHERE category = 'Shirt' ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Management | Vinescraft Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
        :root { --coral: #f18973; --peach: #fce0d8; --text: #444; }
        
        body { font-family: 'Montserrat', sans-serif; background: #fffafb; margin: 0; display: flex; color: var(--text); }
        
        /* SIDEBAR STYLE */
        .sidebar { width: 260px; background: white; height: 100vh; border-right: 1px solid var(--peach); padding: 30px 20px; position: fixed; }
        .sidebar h1 { color: var(--coral); font-size: 20px; font-weight: 900; text-transform: lowercase; margin-bottom: 40px; }
        .sidebar h1::after { content: '.'; }
        
        .nav-links { display: flex; flex-direction: column; gap: 10px; }
        .nav-links a { text-decoration: none; color: #888; font-size: 13px; font-weight: 700; padding: 12px 20px; border-radius: 10px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: var(--peach); color: var(--coral); }
        .nav-links a.logout { margin-top: 20px; color: #ff4757; }

        /* MAIN CONTENT AREA */
        .main-content { margin-left: 300px; padding: 40px; width: calc(100% - 340px); }
        h2 { font-size: 32px; font-weight: 900; color: var(--coral); margin: 0 0 10px; text-transform: lowercase; }
        h2::after { content: '.'; }

        .back-container { margin-bottom: 25px; }
        .back-btn { text-decoration: none; color: var(--coral); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }

        /* CONTENT BOXES */
        .content-box { background: white; padding: 30px; border-radius: 20px; border: 1px solid var(--peach); margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .content-box h3 { font-size: 16px; text-transform: uppercase; color: var(--coral); margin-top: 0; margin-bottom: 20px; letter-spacing: 1px; }

        /* TABLE STYLES */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; color: #bbb; padding: 15px 10px; border-bottom: 1px solid var(--peach); letter-spacing: 1px; }
        td { padding: 15px 10px; font-size: 13px; border-bottom: 1px solid #fff5f8; }
        
        /* RESTOCK UI STYLES */
        .stock-input { width: 80px; padding: 8px; border: 1px solid var(--peach); border-radius: 8px; font-family: 'Montserrat', sans-serif; font-size: 13px; text-align: center; outline: none; transition: 0.3s; }
        .stock-input:focus { border-color: var(--coral); }
        .stock-warning { color: #ff4757; font-weight: 900; border-color: #ff4757; background: #fff2f2; }
        
        .btn-update { background: var(--peach); color: var(--coral); border: none; padding: 8px 15px; border-radius: 8px; font-weight: 900; font-size: 10px; cursor: pointer; transition: 0.3s; text-transform: uppercase; min-width: 60px; }
        .btn-update:hover:not(:disabled) { background: var(--coral); color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(241, 137, 115, 0.2); }
        .btn-update:disabled { opacity: 0.7; cursor: not-allowed; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

    <div class="sidebar">
        <h1>vinescraft.</h1>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard Home</a>
            <a href="admin_orders.php">Manage Orders</a>
            <a href="admin_products.php">Manage Products</a>
            <a href="admin_materials.php" class="active">Manage Materials</a>
            <a href="admin_reviews.php">Reviews</a>
            <a href="admin_messages.php">Messages</a>
            <a href="admin_history.php">Login History</a>
            <a href="admin_settings.php">Account Settings</a>
            <a href="admin_logout.php" class="logout">Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="back-container">
            <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <h2>inventory.</h2>
        <p style="font-size: 12px; color: #888; margin-top: -5px; margin-bottom: 25px;">Live updates enabled. Changes are saved immediately.</p>

        <!-- BOUQUET MATERIALS SECTION -->
        <div class="content-box">
            <h3>Bouquet Materials</h3>
            <table>
                <thead>
                    <tr>
                        <th>Material Details</th>
                        <th>Update Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($bouquet_materials->num_rows > 0): ?>
                        <?php while($row = $bouquet_materials->fetch_assoc()): ?>
                        <tr>
                            <td><strong style="color: var(--coral);"><?php echo htmlspecialchars($row['name']); ?></strong></td>
                            <td>
                                <!-- BAGO: Ginamitan ng onsubmit para dumaan sa JS imbes na mag-refresh -->
                                <form class="stock-form" onsubmit="updateStock(event, this)" style="display: flex; align-items: center; gap: 10px;">
                                    <input type="hidden" name="material_id" value="<?php echo $row['material_id']; ?>">
                                    <!-- Added step="0.01" para allowed ang butal (decimals) -->
                                    <input type="number" step="0.01" name="new_stock" value="<?php echo $row['stock']; ?>" min="0" class="stock-input <?php echo ($row['stock'] <= 5) ? 'stock-warning' : ''; ?>" title="Current Stock">
                                    <button type="submit" class="btn-update">Save</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2" style="text-align: center; color: #bbb; padding: 30px;">No bouquet materials registered in the database.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- SHIRT MATERIALS SECTION -->
        <div class="content-box">
            <h3>Shirt Materials</h3>
            <table>
                <thead>
                    <tr>
                        <th>Material Details</th>
                        <th>Update Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($shirt_materials->num_rows > 0): ?>
                        <?php while($row = $shirt_materials->fetch_assoc()): ?>
                        <tr>
                            <td><strong style="color: var(--coral);"><?php echo htmlspecialchars($row['name']); ?></strong></td>
                            <td>
                                <form class="stock-form" onsubmit="updateStock(event, this)" style="display: flex; align-items: center; gap: 10px;">
                                    <input type="hidden" name="material_id" value="<?php echo $row['material_id']; ?>">
                                    <input type="number" step="0.01" name="new_stock" value="<?php echo $row['stock']; ?>" min="0" class="stock-input <?php echo ($row['stock'] <= 5) ? 'stock-warning' : ''; ?>" title="Current Stock">
                                    <button type="submit" class="btn-update">Save</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2" style="text-align: center; color: #bbb; padding: 30px;">No shirt materials registered in the database.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- BAGO: JavaScript para sa Live Updates -->
    <script>
        function updateStock(event, formElement) {
            event.preventDefault(); // Pipigilan ang page na mag-refresh

            const formData = new FormData(formElement);
            formData.append('ajax_update_stock', '1'); // Trigger flag para sa PHP

            const btn = formElement.querySelector('.btn-update');
            const input = formElement.querySelector('.stock-input');
            const originalText = 'Save';

            // Loading state
            btn.innerText = '...';
            btn.disabled = true;

            fetch('admin_materials.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success visual feedback
                    btn.innerText = 'Saved!';
                    btn.style.background = '#2e7d32'; // Green
                    btn.style.color = 'white';

                    // I-update yung kulay ng input box kung low stock na (<= 5)
                    if (parseFloat(input.value) <= 5) {
                        input.classList.add('stock-warning');
                    } else {
                        input.classList.remove('stock-warning');
                    }

                    // Ibalik sa normal yung button after 1.5 seconds
                    setTimeout(() => {
                        btn.innerText = originalText;
                        btn.style.background = ''; // Reset CSS
                        btn.style.color = '';
                        btn.disabled = false;
                    }, 1500);
                } else {
                    alert('Error updating stock: ' + data.error);
                    btn.innerText = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Connection error. Please try again.');
                btn.innerText = originalText;
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>