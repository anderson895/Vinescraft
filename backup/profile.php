<?php
session_start();
require_once 'db_connect.php';

// 1. PROTECTION: Siguraduhin na naka-login ang user
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = "";

// 2. DATABASE UPDATE LOGIC
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $new_name = mysqli_real_escape_string($conn, $_POST['name']);
    $new_phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $new_address = mysqli_real_escape_string($conn, $_POST['address']);

    $update_sql = "UPDATE users SET name = ?, phone = ?, address = ? WHERE user_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssi", $new_name, $new_phone, $new_address, $user_id);

    if ($stmt->execute()) {
        $_SESSION['user_name'] = $new_name; 
        $success_msg = "Profile updated successfully!";
    }
}

// 3. FETCH DATA
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Vinescraft</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');

        :root {
            --coral: #f18973;
            --peach: #fce0d8;
            --text: #444;
            --bg: #fdf2f2;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--bg);
            margin: 0;
            color: var(--text);
        }

        /* NAVIGATION BAR - Standardized across pages */
        .top-nav { 
            background: var(--coral); 
            padding: 15px 5%; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            color: white; 
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 15px rgba(241, 137, 115, 0.2);
        }
        .top-nav .logo {
            font-weight: 900; 
            font-size: 18px; 
            text-transform: lowercase;
            letter-spacing: 1px;
        }
        .nav-links {
            display: flex;
            gap: 15px;
        }
        .top-nav a { 
            color: white; 
            text-decoration: none; 
            font-size: 10px; 
            font-weight: 900; 
            text-transform: uppercase; 
            letter-spacing: 2px;
            transition: 0.3s;
        }
        .top-nav a:hover, .top-nav a.active { 
            opacity: 0.8;
            text-decoration: underline;
        }

        .container {
            max-width: 550px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .profile-card {
            background: white;
            padding: 40px;
            border-radius: 35px;
            border: 1px solid var(--peach);
            box-shadow: 0 20px 40px rgba(241, 137, 115, 0.1);
            text-align: center;
        }

        h2 {
            font-size: 32px;
            font-weight: 900;
            color: var(--coral);
            text-transform: lowercase;
            margin: 0;
        }
        h2::after { content: '.'; }
        
        .sub-tag {
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            color: #bbb;
            letter-spacing: 2px;
            display: block;
            margin-bottom: 30px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 25px;
            border: 1px solid #a5d6a7;
        }

        /* FORM STYLES */
        .form-group {
            text-align: left;
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--coral);
            margin-bottom: 10px;
            margin-left: 10px;
            letter-spacing: 1px;
        }

        input[type="text"], input[type="tel"], textarea {
            width: 100%;
            padding: 15px 25px;
            border: 2px solid var(--bg);
            border-radius: 50px;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
            background: #fffcfc;
            color: var(--text);
            transition: 0.3s;
        }

        input:focus, textarea:focus {
            border-color: var(--peach);
            background: #fff;
        }

        textarea { 
            resize: none; 
            height: 120px; 
            border-radius: 25px; 
        }

        /* BUTTONS */
        .btn-save {
            width: 100%;
            padding: 18px;
            background: var(--coral);
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 900;
            font-size: 12px;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 10px 20px rgba(241, 137, 115, 0.2);
        }
        .btn-save:hover {
            background: #e07661;
            transform: translateY(-2px);
        }

        .logout-link {
            display: inline-block;
            margin-top: 30px;
            text-decoration: none;
            color: #ccc;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            transition: 0.3s;
            letter-spacing: 1px;
        }
        .logout-link:hover { color: #ff4757; }

        footer {
            text-align: center;
            font-size: 10px;
            color: #bbb;
            padding: 40px 20px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

<!-- TOP NAV: Updated content as requested -->
<nav class="top-nav">
    <div style="font-weight: 900; font-size: 18px; text-transform: lowercase;">vinescraft.</div>
    <div>
        <a href="index.php">Home</a>
        <a href="shop.php">Shop</a>
        <a href="customizer.php">Custom Bouquet</a>
        <a href="customize_tshirt.php">Custom Shirt</a>
        <a href="about.php">Our Story</a>
        <a href="cart.php">Cart</a>
        <a href="chat.php">Chat</a>
        <a href="my_orders.php">Orders</a>
        
        <?php if(!empty($_SESSION['user_logged_in'])): ?>
            <a href="profile.php">Account</a>
            <!-- Small optional logout link or keep strictly as requested -->
            <a href="logout.php" style="font-size: 9px; opacity: 0.6; margin-left: 5px;">(Logout)</a>
        <?php else: ?>
            <a href="login.php">Account</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">
    <div class="profile-card">
        <h2>my profile.</h2>
        <span class="sub-tag">Bloom your information</span>

        <?php if ($success_msg): ?>
            <div class="alert-success"><?= $success_msg ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g. 09123456789">
            </div>

            <div class="form-group">
                <label>Shipping Address</label>
                <textarea name="address" placeholder="Enter your full delivery address"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
            </div>

            <button type="submit" name="update_profile" class="btn-save">Update Profile</button>
        </form>

        <a href="logout.php" class="logout-link">Sign Out</a>
    </div>
</div>

<footer>
    &copy; 2026 Gazette in Vines Flower and Craft Shop | Las Piñas
</footer>

</body>
</html>