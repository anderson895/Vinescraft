<?php
session_start();
require_once 'db_connect.php';

// PROTECTION: Admin session check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$message = "";
$error = "";

// 1. KUNIN ANG CURRENT DATA NG ADMIN
$stmt = $conn->prepare("SELECT name, email FROM users WHERE user_id = ? AND role = 'admin'");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_data = $stmt->get_result()->fetch_assoc();

// 2. LOGIC PARA SA UPDATE
if (isset($_POST['update_settings'])) {
    $new_name = mysqli_real_escape_string($conn, $_POST['name']);
    $new_email = mysqli_real_escape_string($conn, $_POST['email']);
    $new_password = $_POST['new_password'];

    // Update basic info muna
    $update_sql = "UPDATE users SET name = '$new_name', email = '$new_email' WHERE user_id = $admin_id";
    
    if ($conn->query($update_sql)) {
        $_SESSION['admin_name'] = $new_name; // I-update ang session para mag-reflect sa sidebar
        $message = "Settings updated successfully!";

        // Kung may nilagay na bagong password, i-hash at i-update
        if (!empty($new_password)) {
            $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password = '$hashed_pass' WHERE user_id = $admin_id");
            $message .= " Password changed.";
        }
    } else {
        $error = "Error updating settings: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Settings | Vinescraft Admin</title>
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

        /* SETTINGS BOX */
        .content-box { background: white; padding: 40px; border-radius: 20px; border: 1px solid var(--peach); max-width: 600px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        
        .alert { padding: 15px; border-radius: 10px; font-size: 13px; font-weight: 700; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        /* FORM STYLES */
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 11px; font-weight: 900; text-transform: uppercase; color: #aaa; margin-bottom: 8px; letter-spacing: 1px; }
        
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%; padding: 12px 15px; border: 1px solid var(--peach); border-radius: 12px; font-family: 'Montserrat', sans-serif; font-size: 14px; box-sizing: border-box; outline: none; transition: 0.3s;
        }
        input:focus { border-color: var(--coral); box-shadow: 0 0 0 3px rgba(241, 137, 115, 0.1); }
        
        .btn-save { 
            background: var(--coral); color: white; border: none; padding: 15px 30px; border-radius: 50px; 
            font-weight: 900; font-size: 12px; text-transform: uppercase; cursor: pointer; transition: 0.3s; width: 100%; margin-top: 10px;
        }
        .btn-save:hover { background: #e07661; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(241, 137, 115, 0.3); }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

    <!-- SIDEBAR NAVIGATION -->
    <div class="sidebar">
        <h1>vinescraft.</h1>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard Home</a>
            <a href="admin_orders.php">Manage Orders</a>
            <a href="admin_products.php">Manage Products</a>
            <a href="admin_materials.php">Manage Materials</a>
            <a href="admin_reviews.php">Reviews</a>
            <a href="admin_messages.php">Messages</a>
            <a href="admin_history.php">Login History</a>
            <a href="admin_settings.php" class="active">Account Settings</a>
            <a href="admin_logout.php" class="logout">Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="back-container">
            <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <h2>account settings.</h2>

        <div class="content-box">
            <p style="font-size: 13px; color: #888; margin-bottom: 30px;">Update your login credentials and personal information for **Gazette in Vines**.</p>

            <?php if($message != ""): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if($error != ""): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($admin_data['name']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($admin_data['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" placeholder="Leave blank to keep current password">
                </div>

                <button type="submit" name="update_settings" class="btn-save">Save Changes</button>
            </form>
        </div>
    </div>

</body>
</html>