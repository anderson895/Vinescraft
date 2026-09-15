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

$message = "";

// 1. AUTO-CREATE TABLES PARA SA DYNAMIC FLOWERS AT WRAPPERS
$check_table_flowers = $conn->query("SHOW TABLES LIKE 'dynamic_flowers'");
if ($check_table_flowers->num_rows == 0) {
    $conn->query("CREATE TABLE dynamic_flowers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE,
        image VARCHAR(255)
    )");
}

$check_table_wrappers = $conn->query("SHOW TABLES LIKE 'dynamic_wrappers'");
if ($check_table_wrappers->num_rows == 0) {
    $conn->query("CREATE TABLE dynamic_wrappers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        size VARCHAR(50),
        image_back VARCHAR(255),
        image_front VARCHAR(255)
    )");
}

// Siguraduhing may folders para sa uploads
if (!file_exists('uploads/custom_flowers')) { mkdir('uploads/custom_flowers', 0777, true); }
if (!file_exists('uploads/custom_wrappers')) { mkdir('uploads/custom_wrappers', 0777, true); }

// FETCH MATERIALS PARA SA DROPDOWN
$mats_res = $conn->query("SELECT name, stock FROM materials ORDER BY name ASC");
$materials_array = [];
while($m = $mats_res->fetch_assoc()) {
    $materials_array[] = $m;
}

// HARDCODED NAMES PARA HINDI MAG-CONFLICT SA SYSTEM DEFAULTS
$hardcoded_names = ['tulip', 'rose', 'small sunflower', 'big sunflower', 'lily', 'calla lily', 'spider lily', 'poppy', 'iris', 'cornflower', 'carnation', 'hyacinth', 'hydrangea', 'fuchsia', 'thistle', 'daisy', 'lavender', 'baby\'s breath', 'lily of the valley', 'statice', 'bell flower', 'eucalyptus leaf', 'gardenia leaf', 'small leaf'];

// ==============================================================================
// 1. FLOWER LOGIC
// ==============================================================================

// --- ADD NEW FLOWER LOGIC ---
if (isset($_POST['add_flower'])) {
    $name = trim($conn->real_escape_string($_POST['name']));
    
    if (in_array(strtolower($name), $hardcoded_names)) {
        $message = "Error: '$name' cannot be used because it is a default system flower. Please choose a different name (e.g., 'Blue Rose Variant').";
    } else {
        $image = $_FILES['image']['name'];
        $target = "uploads/custom_flowers/" . time() . "_" . basename($image);
        $img_filename = time() . "_" . basename($image);

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $conn->query("INSERT INTO dynamic_flowers (name, image) VALUES ('$name', '$img_filename')");
            
            if (isset($_POST['mat_name']) && isset($_POST['mat_qty'])) {
                foreach ($_POST['mat_name'] as $index => $mat_name) {
                    $qty = floatval($_POST['mat_qty'][$index]);
                    $mat_name_esc = $conn->real_escape_string($mat_name);
                    if (!empty($mat_name) && $qty > 0) {
                        $conn->query("INSERT INTO bouquet_recipes (element_name, material_name, qty) VALUES ('$name', '$mat_name_esc', $qty)");
                    }
                }
            }
            header("Location: admin_flowers.php?msg=success");
            exit();
        }
    }
}

// --- EDIT FLOWER LOGIC ---
if (isset($_POST['edit_flower'])) {
    $id = intval($_POST['id']);
    $old_name = $conn->real_escape_string($_POST['old_name']);
    $name = trim($conn->real_escape_string($_POST['name']));

    if (strtolower($old_name) !== strtolower($name) && in_array(strtolower($name), $hardcoded_names)) {
        $message = "Error: '$name' is a default system name and cannot be used.";
    } else {
        if (!empty($_FILES['image']['name'])) {
            $image = $_FILES['image']['name'];
            $img_filename = time() . "_" . basename($image);
            $target = "uploads/custom_flowers/" . $img_filename;
            move_uploaded_file($_FILES['image']['tmp_name'], $target);
            $conn->query("UPDATE dynamic_flowers SET name='$name', image='$img_filename' WHERE id=$id");
        } else {
            $conn->query("UPDATE dynamic_flowers SET name='$name' WHERE id=$id");
        }

        $conn->query("DELETE FROM bouquet_recipes WHERE element_name='$old_name'");
        if (isset($_POST['mat_name']) && isset($_POST['mat_qty'])) {
            foreach ($_POST['mat_name'] as $index => $mat_name) {
                $qty = floatval($_POST['mat_qty'][$index]);
                $mat_name_esc = $conn->real_escape_string($mat_name);
                if (!empty($mat_name) && $qty > 0) {
                    $conn->query("INSERT INTO bouquet_recipes (element_name, material_name, qty) VALUES ('$name', '$mat_name_esc', $qty)");
                }
            }
        }
        header("Location: admin_flowers.php?msg=updated");
        exit();
    }
}

// --- DELETE FLOWER LOGIC ---
if (isset($_GET['delete_flower'])) {
    $id = intval($_GET['delete_flower']);
    $res = $conn->query("SELECT name, image FROM dynamic_flowers WHERE id=$id");
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $name_esc = $conn->real_escape_string($row['name']);
        if (file_exists("uploads/custom_flowers/" . $row['image'])) {
            unlink("uploads/custom_flowers/" . $row['image']);
        }
        $conn->query("DELETE FROM bouquet_recipes WHERE element_name='$name_esc'");
        $conn->query("DELETE FROM dynamic_flowers WHERE id=$id");
    }
    header("Location: admin_flowers.php");
    exit();
}

// ==============================================================================
// 2. WRAPPER LOGIC
// ==============================================================================

// --- ADD NEW WRAPPER LOGIC ---
if (isset($_POST['add_wrapper'])) {
    $w_name = trim($conn->real_escape_string($_POST['w_name']));
    $w_size = $conn->real_escape_string($_POST['w_size']);
    
    $image_back = $_FILES['w_image_back']['name'];
    $image_front = $_FILES['w_image_front']['name'];
    
    $back_filename = time() . "_back_" . basename($image_back);
    $front_filename = time() . "_front_" . basename($image_front);
    
    $target_back = "uploads/custom_wrappers/" . $back_filename;
    $target_front = "uploads/custom_wrappers/" . $front_filename;

    if (move_uploaded_file($_FILES['w_image_back']['tmp_name'], $target_back) && move_uploaded_file($_FILES['w_image_front']['tmp_name'], $target_front)) {
        $conn->query("INSERT INTO dynamic_wrappers (name, size, image_back, image_front) VALUES ('$w_name', '$w_size', '$back_filename', '$front_filename')");
        header("Location: admin_flowers.php?msg=wrapper_success");
        exit();
    } else {
        $message = "Error: Failed to upload wrapper images.";
    }
}

// --- EDIT WRAPPER LOGIC ---
if (isset($_POST['edit_wrapper_submit'])) {
    $w_id = intval($_POST['w_id']);
    $w_name = trim($conn->real_escape_string($_POST['w_name']));
    $w_size = $conn->real_escape_string($_POST['w_size']);

    // Update Name at Size
    $conn->query("UPDATE dynamic_wrappers SET name='$w_name', size='$w_size' WHERE id=$w_id");

    // Check kung may in-upload na bagong Back Image
    if (!empty($_FILES['w_image_back']['name'])) {
        $image_back = $_FILES['w_image_back']['name'];
        $back_filename = time() . "_back_" . basename($image_back);
        $target_back = "uploads/custom_wrappers/" . $back_filename;
        if (move_uploaded_file($_FILES['w_image_back']['tmp_name'], $target_back)) {
            $conn->query("UPDATE dynamic_wrappers SET image_back='$back_filename' WHERE id=$w_id");
        }
    }

    // Check kung may in-upload na bagong Front Image
    if (!empty($_FILES['w_image_front']['name'])) {
        $image_front = $_FILES['w_image_front']['name'];
        $front_filename = time() . "_front_" . basename($image_front);
        $target_front = "uploads/custom_wrappers/" . $front_filename;
        if (move_uploaded_file($_FILES['w_image_front']['tmp_name'], $target_front)) {
            $conn->query("UPDATE dynamic_wrappers SET image_front='$front_filename' WHERE id=$w_id");
        }
    }

    header("Location: admin_flowers.php?msg=wrapper_updated");
    exit();
}

// --- DELETE WRAPPER LOGIC ---
if (isset($_GET['delete_wrapper'])) {
    $id = intval($_GET['delete_wrapper']);
    $res = $conn->query("SELECT image_back, image_front FROM dynamic_wrappers WHERE id=$id");
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (file_exists("uploads/custom_wrappers/" . $row['image_back'])) { unlink("uploads/custom_wrappers/" . $row['image_back']); }
        if (file_exists("uploads/custom_wrappers/" . $row['image_front'])) { unlink("uploads/custom_wrappers/" . $row['image_front']); }
        $conn->query("DELETE FROM dynamic_wrappers WHERE id=$id");
    }
    header("Location: admin_flowers.php");
    exit();
}

// FETCH DATA FOR TABLES
$dynamic_flowers = $conn->query("SELECT * FROM dynamic_flowers ORDER BY id DESC");
$dynamic_wrappers = $conn->query("SELECT * FROM dynamic_wrappers ORDER BY id DESC");

// FETCH EDIT DATA FOR FLOWER
$edit_data = null;
$edit_materials = [];
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_res = $conn->query("SELECT * FROM dynamic_flowers WHERE id = $edit_id");
    if ($edit_res->num_rows > 0) {
        $edit_data = $edit_res->fetch_assoc();
        $e_name = $conn->real_escape_string($edit_data['name']);
        $pm_req = $conn->query("SELECT material_name, qty FROM bouquet_recipes WHERE element_name = '$e_name'");
        while ($pm = $pm_req->fetch_assoc()) {
            $edit_materials[] = $pm;
        }
    }
}

// FETCH EDIT DATA FOR WRAPPER
$edit_wrapper_data = null;
if (isset($_GET['edit_wrapper'])) {
    $edit_w_id = intval($_GET['edit_wrapper']);
    $edit_w_res = $conn->query("SELECT * FROM dynamic_wrappers WHERE id = $edit_w_id");
    if ($edit_w_res->num_rows > 0) {
        $edit_wrapper_data = $edit_w_res->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Custom Assets | Vinescraft Admin</title>
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

        .sidebar { width: 260px; background: white; height: 100vh; border-right: 1px solid var(--peach); padding: 30px 20px; position: fixed; overflow-y: auto; }
        .sidebar h1 { color: var(--coral); font-size: 20px; font-weight: 900; text-transform: lowercase; margin-bottom: 40px; }
        .nav-links { display: flex; flex-direction: column; gap: 10px; }
        .nav-links a { text-decoration: none; color: #888; font-size: 13px; font-weight: 700; padding: 12px 20px; border-radius: 10px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: var(--peach); color: var(--coral); }
        .nav-links a.logout { margin-top: 20px; color: #ff4757; }

        .main-content { margin-left: 300px; padding: 40px; width: calc(100% - 340px); }
        h2 { font-size: 32px; font-weight: 900; color: var(--coral); margin: 0 0 10px; }

        .content-box { background: white; padding: 30px; border-radius: 20px; border: 1px solid var(--peach); margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .content-box h3 { font-size: 14px; text-transform: uppercase; color: var(--coral); margin-top: 0; margin-bottom: 20px; }
        
        input[type="text"], input[type="file"], select, input[type="number"] { width: 100%; padding: 12px; border: 1px solid var(--peach); border-radius: 10px; font-family: inherit; margin-bottom: 15px; outline: none; box-sizing: border-box; }
        .btn-submit { background: var(--coral); color: white; border: none; padding: 12px 25px; border-radius: 50px; font-weight: 900; font-size: 12px; text-transform: uppercase; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { background: #e07661; transform: translateY(-2px); }
        .btn-add-mat { background: var(--peach); color: var(--coral); border: none; padding: 8px 15px; border-radius: 8px; font-weight: 700; font-size: 10px; cursor: pointer; display: inline-block; margin-bottom: 15px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; color: #bbb; padding: 15px 10px; border-bottom: 1px solid var(--peach); }
        td { padding: 15px 10px; font-size: 13px; border-bottom: 1px solid #fff5f8; }
        .prod-img { width: 60px; height: 60px; object-fit: contain; border-radius: 10px; border: 1px solid var(--peach); background: #fafafa; }
        .action-link { text-decoration: none; color: var(--coral); font-weight: 700; font-size: 11px; }

        .mat-row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
        
        .alert { padding: 10px; background: #ffebee; color: #c62828; border-radius: 8px; font-size: 12px; font-weight: bold; margin-bottom: 15px; }
        .success { background: #e8f8f5; color: #2ed573; }

        .grid-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

<?php include 'admin_sidebar.php' ?>

    <div class="main-content">
        <h2>Custom Assets</h2>

        <div class="content-box" style="background: var(--peach); border: none;">
            <p style="font-size: 12px; color: var(--text); margin: 0; line-height: 1.5;">
                Dito ka magdadagdag ng mga bagong bulaklak at wrappers na lalabas sa Bouquet Customizer ng mga customers mo.
            </p>
        </div>

        <?php if ($message) echo "<div class='alert'>$message</div>"; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'success') echo "<div class='alert success'>New flower added to customizer successfully!</div>"; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'updated') echo "<div class='alert success'>Flower updated successfully!</div>"; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'wrapper_success') echo "<div class='alert success'>New wrapper added successfully!</div>"; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'wrapper_updated') echo "<div class='alert success'>Wrapper updated successfully!</div>"; ?>

        <div class="grid-2col">
            <div>
                <div class="content-box">
                    <?php if ($edit_data): ?>
                        <h3>Edit Flower: <?php echo htmlspecialchars($edit_data['name']); ?></h3>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                            <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($edit_data['name']); ?>">
                            <input type="text" name="name" value="<?php echo htmlspecialchars($edit_data['name']); ?>" required>
                            
                            <label style="font-size: 11px; color: #888;">Change Image (Leave blank to keep current):</label>
                            <input type="file" name="image" accept="image/png, image/jpeg, image/jpg">

                            <div style="background: #fafafa; padding: 15px; border-radius: 10px; border: 1px dashed var(--peach); margin-bottom: 15px;">
                                <label style="font-size: 11px; font-weight: 700; color: var(--coral); display: block; margin-bottom: 10px;">Materials Needed (Recipe)</label>
                                <div id="mat-container-edit"></div>
                                <button type="button" class="btn-add-mat" onclick="addMaterialRow('mat-container-edit')">+ Add Material</button>
                            </div>

                            <button type="submit" name="edit_flower" class="btn-submit">Save Changes</button>
                            <a href="admin_flowers.php" style="font-size: 11px; margin-left: 15px; color: #888; text-decoration: none;">Cancel</a>
                        </form>
                    <?php else: ?>
                        <h3>Add New Flower Variant</h3>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="text" name="name" placeholder="Flower Name (e.g., 'Blue Velvet Rose')" required>
                            <label style="font-size: 11px; color: #888;">Upload Flower Image (Transparent PNG recommended):</label>
                            <input type="file" name="image" accept="image/png, image/jpeg, image/jpg" required>

                            <div style="background: #fafafa; padding: 15px; border-radius: 10px; border: 1px dashed var(--peach); margin-bottom: 15px;">
                                <label style="font-size: 11px; font-weight: 700; color: var(--coral); display: block; margin-bottom: 10px;">Materials Needed (Recipe)</label>
                                <div id="mat-container-add"></div>
                                <button type="button" class="btn-add-mat" onclick="addMaterialRow('mat-container-add')">+ Add Material</button>
                            </div>

                            <button type="submit" name="add_flower" class="btn-submit">Add Flower</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="content-box">
                    <h3>Added Dynamic Flowers</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($dynamic_flowers->num_rows > 0): while($row = $dynamic_flowers->fetch_assoc()): ?>
                            <tr>
                                <td><img src="uploads/custom_flowers/<?php echo $row['image']; ?>" class="prod-img"></td>
                                <td><strong style="color: var(--coral);"><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td>
                                    <a href="admin_flowers.php?edit=<?php echo $row['id']; ?>" class="action-link">Edit</a> | 
                                    <a href="admin_flowers.php?delete_flower=<?php echo $row['id']; ?>" class="action-link" style="color: #ff4757;" onclick="return confirm('Delete this flower?')">Delete</a>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="3" style="text-align: center; color: #aaa; padding: 20px;">No custom flowers added yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <div class="content-box">
                    <?php if ($edit_wrapper_data): ?>
                        <h3>Edit Wrapper: <?php echo htmlspecialchars($edit_wrapper_data['name']); ?></h3>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="w_id" value="<?php echo $edit_wrapper_data['id']; ?>">
                            
                            <input type="text" name="w_name" value="<?php echo htmlspecialchars($edit_wrapper_data['name']); ?>" required>
                            
                            <label style="font-size: 11px; color: #888;">Select Applicable Size:</label>
                            <select name="w_size" required>
                                <option value="Small" <?php echo ($edit_wrapper_data['size'] == 'Small') ? 'selected' : ''; ?>>Small</option>
                                <option value="Medium" <?php echo ($edit_wrapper_data['size'] == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="Large" <?php echo ($edit_wrapper_data['size'] == 'Large') ? 'selected' : ''; ?>>Large</option>
                            </select>

                            <label style="font-size: 11px; color: #888; margin-top: 10px; display: block;">Change Bottom Layer Image (Leave blank to keep):</label>
                            <input type="file" name="w_image_back" accept="image/png, image/jpeg, image/jpg">

                            <label style="font-size: 11px; color: #888;">Change Top Layer Image (Leave blank to keep):</label>
                            <input type="file" name="w_image_front" accept="image/png, image/jpeg, image/jpg">

                            <button type="submit" name="edit_wrapper_submit" class="btn-submit">Save Changes</button>
                            <a href="admin_flowers.php" style="font-size: 11px; margin-left: 15px; color: #888; text-decoration: none;">Cancel</a>
                        </form>
                    <?php else: ?>
                        <h3>Add New Wrapper</h3>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="text" name="w_name" placeholder="Wrapper Name (e.g., 'Pink Ribbon Wrapper')" required>
                            
                            <label style="font-size: 11px; color: #888;">Select Applicable Size:</label>
                            <select name="w_size" required>
                                <option value="" disabled selected>Choose Size</option>
                                <option value="Small">Small</option>
                                <option value="Medium">Medium</option>
                                <option value="Large">Large</option>
                            </select>

                            <label style="font-size: 11px; color: #888; margin-top: 10px; display: block;">Bottom Layer Image (Back part):</label>
                            <input type="file" name="w_image_back" accept="image/png, image/jpeg, image/jpg" required>

                            <label style="font-size: 11px; color: #888;">Top Layer Image (Front part):</label>
                            <input type="file" name="w_image_front" accept="image/png, image/jpeg, image/jpg" required>

                            <button type="submit" name="add_wrapper" class="btn-submit">Add Wrapper</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="content-box">
                    <h3>Added Wrappers</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Front & Back</th>
                                <th>Details</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($dynamic_wrappers->num_rows > 0): while($row = $dynamic_wrappers->fetch_assoc()): ?>
                            <tr>
                                <td style="display: flex; gap: 5px;">
                                    <img src="uploads/custom_wrappers/<?php echo $row['image_back']; ?>" class="prod-img" title="Back">
                                    <img src="uploads/custom_wrappers/<?php echo $row['image_front']; ?>" class="prod-img" title="Front">
                                </td>
                                <td>
                                    <strong style="color: var(--coral);"><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                    <span style="font-size: 9px; color: #888; text-transform: uppercase;"><?php echo $row['size']; ?></span>
                                </td>
                                <td>
                                    <a href="admin_flowers.php?edit_wrapper=<?php echo $row['id']; ?>" class="action-link">Edit</a> | 
                                    <a href="admin_flowers.php?delete_wrapper=<?php echo $row['id']; ?>" class="action-link" style="color: #ff4757;" onclick="return confirm('Delete this wrapper?')">Delete</a>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="3" style="text-align: center; color: #aaa; padding: 20px;">No custom wrappers added yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const materialsList = <?php echo json_encode($materials_array); ?>;
        
        function addMaterialRow(containerId, matName = '', qty = '') {
            let options = '<option value="">Select Material...</option>';
            materialsList.forEach(m => {
                let selected = (m.name === matName) ? 'selected' : '';
                options += `<option value="${m.name}" ${selected}>${m.name} (Stock: ${m.stock})</option>`;
            });
            
            let row = document.createElement('div');
            row.className = 'mat-row';
            row.innerHTML = `
                <select name="mat_name[]" required style="flex:2; margin-bottom:0;">${options}</select>
                <input type="number" step="0.01" name="mat_qty[]" value="${qty}" placeholder="Qty" required style="flex:1; margin-bottom:0;">
                <button type="button" onclick="this.parentElement.remove()" style="background:#ff4757; color:white; border:none; border-radius:8px; padding:10px 15px; cursor:pointer; font-weight:bold;">X</button>
            `;
            document.getElementById(containerId).appendChild(row);
        }

        <?php if ($edit_data && count($edit_materials) > 0): ?>
            <?php foreach ($edit_materials as $pm): ?>
                addMaterialRow('mat-container-edit', '<?php echo htmlspecialchars(addslashes($pm['material_name'])); ?>', '<?php echo $pm['qty']; ?>');
            <?php endforeach; ?>
        <?php endif; ?>
    </script>
</body>
</html>