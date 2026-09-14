<?php
session_start();
require_once 'db_connect.php';

// PROTECTION: Independent admin session check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$message = "";

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

// --- 2. LOGIC PARA SA PAG-UPDATE ---
if (isset($_POST['save_update'])) {
    $id = $_POST['product_id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    
    if (!empty($_FILES['image']['name'])) {
        $image = $_FILES['image']['name'];
        $target = "uploads/" . basename($image);
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
        $sql = "UPDATE products SET name='$name', description='$description', price='$price', stock='$stock', image='$image' WHERE product_id=$id";
    } else {
        $sql = "UPDATE products SET name='$name', description='$description', price='$price', stock='$stock' WHERE product_id=$id";
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
    $stock = $_POST['stock'];
    if (!file_exists('uploads')) { mkdir('uploads', 0777, true); }

    $image = $_FILES['image']['name'];
    $target = "uploads/" . basename($image);

    $sql = "INSERT INTO products (name, description, price, stock, image) VALUES ('$name', '$description', '$price', '$stock', '$image')";
    if ($conn->query($sql) === TRUE) {
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
        $message = "Product added successfully!";
    }
}

// --- 4. LOGIC PARA SA DELETE ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM products WHERE product_id = $id");
    header("Location: admin_products.php");
    exit();
}

$products = $conn->query("SELECT * FROM products ORDER BY product_id DESC");

$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $res = $conn->query("SELECT * FROM products WHERE product_id = $edit_id");
    $edit_data = $res->fetch_assoc();
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
        
        /* SIDEBAR STYLE */
        .sidebar { width: 260px; background: white; height: 100vh; border-right: 1px solid var(--peach); padding: 30px 20px; position: fixed; }
        .sidebar h1 { color: var(--coral); font-size: 20px; font-weight: 900; text-transform: lowercase; margin-bottom: 40px; }
        .sidebar h1::after { content: '.'; }
        
        .nav-links { display: flex; flex-direction: column; gap: 10px; }
        .nav-links a { text-decoration: none; color: #888; font-size: 13px; font-weight: 700; padding: 12px 20px; border-radius: 10px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: var(--peach); color: var(--coral); }
        .nav-links a.logout { margin-top: 20px; color: #ff4757; }

        /* MAIN CONTENT */
        .main-content { margin-left: 300px; padding: 40px; width: calc(100% - 340px); }
        h2 { font-size: 32px; font-weight: 900; color: var(--coral); margin: 0 0 10px; text-transform: lowercase; }
        h2::after { content: '.'; }

        .back-container { margin-bottom: 25px; }
        .back-btn { text-decoration: none; color: var(--coral); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }

        /* FORM & BOX STYLES */
        .content-box { background: white; padding: 30px; border-radius: 20px; border: 1px solid var(--peach); margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .content-box h3 { font-size: 14px; text-transform: uppercase; color: var(--coral); margin-top: 0; margin-bottom: 20px; }
        
        input[type="text"], input[type="number"], textarea, input[type="file"] {
            width: 100%; padding: 12px; border: 1px solid var(--peach); border-radius: 10px; font-family: 'Montserrat', sans-serif; margin-bottom: 15px; box-sizing: border-box;
        }
        
        .btn-submit { background: var(--coral); color: white; border: none; padding: 12px 25px; border-radius: 50px; font-weight: 700; font-size: 12px; text-transform: uppercase; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { background: #e07661; transform: translateY(-2px); }

        /* TABLE STYLES */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; color: #bbb; padding: 15px 10px; border-bottom: 1px solid var(--peach); letter-spacing: 1px; }
        td { padding: 15px 10px; font-size: 13px; border-bottom: 1px solid #fff5f8; }
        
        .prod-img { width: 60px; height: 60px; object-fit: cover; border-radius: 10px; border: 1px solid var(--peach); }
        .badge-featured { font-size: 9px; font-weight: 900; padding: 4px 10px; border-radius: 50px; background: #e8f5e9; color: #2e7d32; text-transform: uppercase; }
        .action-link { text-decoration: none; color: var(--coral); font-weight: 700; font-size: 11px; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

    <!-- SIDEBAR NAVIGATION: Changed Chat Support to Login History -->
    <div class="sidebar">
        <h1>vinescraft.</h1>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard Home</a>
            <a href="admin_orders.php">Manage Orders</a>
            <a href="admin_products.php" class="active">Manage Products</a>
            <a href="admin_materials.php">Manage Materials</a>
            <a href="admin_reviews.php">Reviews</a>
            <a href="admin_messages.php">Messages</a>
            <a href="admin_history.php">Login History</a> <!-- Updated Link -->
            <a href="admin_settings.php">Account Settings</a>
            <a href="admin_logout.php" class="logout">Logout</a> <!-- Updated to admin_logout.php -->
        </div>
    </div>

    <div class="main-content">
        <div class="back-container">
            <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <h2>product list.</h2>

        <!-- EDIT/ADD FORM SECTION -->
        <div class="content-box">
            <?php if ($edit_data): ?>
                <h3>Edit Product: <?php echo htmlspecialchars($edit_data['name']); ?></h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" value="<?php echo $edit_data['product_id']; ?>">
                    <input type="text" name="name" value="<?php echo htmlspecialchars($edit_data['name']); ?>" placeholder="Product Name" required>
                    <textarea name="description" placeholder="Description" rows="3" required><?php echo htmlspecialchars($edit_data['description']); ?></textarea>
                    <div style="display: flex; gap: 15px;">
                        <input type="number" step="0.01" name="price" value="<?php echo $edit_data['price']; ?>" placeholder="Price (₱)" required>
                        <input type="number" name="stock" value="<?php echo $edit_data['stock']; ?>" placeholder="Stock Quantity" required>
                    </div>
                    <label style="font-size: 11px; color: #888; display: block; margin-bottom: 5px;">Change Product Image:</label>
                    <input type="file" name="image">
                    <button type="submit" name="save_update" class="btn-submit">Save Changes</button>
                    <a href="admin_products.php" style="font-size: 11px; margin-left: 15px; color: #888; text-decoration: none;">Cancel</a>
                </form>
            <?php else: ?>
                <h3>Add New Product</h3>
                <?php if ($message) echo "<p style='color: var(--coral); font-size: 12px; font-weight: 700;'>$message</p>"; ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="text" name="name" placeholder="Product Name" required>
                    <textarea name="description" placeholder="Description" rows="3" required></textarea>
                    <div style="display: flex; gap: 15px;">
                        <input type="number" step="0.01" name="price" placeholder="Price (₱)" required>
                        <input type="number" name="stock" placeholder="Initial Stock" required>
                    </div>
                    <input type="file" name="image" required>
                    <button type="submit" name="add_product" class="btn-submit">Add Product</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- PRODUCT TABLE SECTION -->
        <div class="content-box">
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product Details</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Featured</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $products->fetch_assoc()): ?>
                    <tr>
                        <td><img src="uploads/<?php echo $row['image']; ?>" class="prod-img"></td>
                        <td>
                            <strong style="color: var(--coral);"><?php echo htmlspecialchars($row['name']); ?></strong><br>
                            <small style="color: #aaa; font-size: 10px;"><?php echo substr(htmlspecialchars($row['description']), 0, 40); ?>...</small>
                        </td>
                        <td style="font-weight: 700;">₱<?php echo number_format($row['price'], 2); ?></td>
                        <td><?php echo $row['stock']; ?></td>
                        <td>
                            <?php if ($row['is_featured'] == 1): ?>
                                <span class="badge-featured">⭐ Featured</span><br>
                                <a href="admin_products.php?toggle_featured=<?php echo $row['product_id']; ?>&status=1" style="font-size: 9px; color: #888;">Remove</a>
                            <?php else: ?>
                                <a href="admin_products.php?toggle_featured=<?php echo $row['product_id']; ?>&status=0" style="font-size: 9px; color: var(--coral); text-decoration: none; font-weight: 700;">Set Featured</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="admin_products.php?edit=<?php echo $row['product_id']; ?>" class="action-link">Edit</a> | 
                            <a href="admin_products.php?delete=<?php echo $row['product_id']; ?>" class="action-link" style="color: #ff4757;" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>