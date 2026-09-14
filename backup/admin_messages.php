<?php
session_start();
require_once 'db_connect.php';

// PROTECTION: Admin session check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// FETCH: Kunin lahat ng users na may message history[cite: 6]
$chat_list = $conn->query("SELECT DISTINCT m.user_id, u.name 
                           FROM messages m 
                           JOIN users u ON m.user_id = u.user_id 
                           ORDER BY m.created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Inquiries | Vinescraft Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
        :root { --coral: #f18973; --peach: #fce0d8; --text: #444; }
        
        body { font-family: 'Montserrat', sans-serif; background: #fffafb; margin: 0; display: flex; color: var(--text); }
        
        /* SIDEBAR STYLE[cite: 6] */
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

        /* INQUIRY LIST BOX */
        .content-box { background: white; padding: 30px; border-radius: 20px; border: 1px solid var(--peach); box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        
        .chat-list { list-style: none; padding: 0; margin: 0; }
        .chat-item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 20px; 
            border-bottom: 1px solid #fff5f8; 
            transition: 0.3s; 
        }
        .chat-item:last-child { border-bottom: none; }
        .chat-item:hover { background: #fffafb; }

        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar { 
            width: 45px; height: 45px; 
            background: var(--peach); 
            color: var(--coral); 
            border-radius: 50%; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            font-weight: 900; 
            font-size: 18px; 
        }
        
        .user-name { font-weight: 700; color: var(--text); font-size: 15px; }
        
        .btn-view { 
            text-decoration: none; 
            background: var(--coral); 
            color: white; 
            padding: 10px 20px; 
            border-radius: 50px; 
            font-size: 11px; 
            font-weight: 900; 
            text-transform: uppercase; 
            transition: 0.3s; 
            box-shadow: 0 5px 15px rgba(241,137,115,0.2); 
        }
        .btn-view:hover { background: #e07661; transform: scale(1.05); }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

    <!-- SIDEBAR NAVIGATION[cite: 6] -->
    <div class="sidebar">
        <h1>vinescraft.</h1>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard Home</a>
            <a href="admin_orders.php">Manage Orders</a>
            <a href="admin_products.php">Manage Products</a>
            <a href="admin_materials.php">Manage Materials</a>
            <a href="admin_reviews.php">Reviews</a>
            <a href="admin_messages.php" class="active">Messages</a>
            <a href="admin_history.php">Login History</a>
            <a href="admin_settings.php">Account Settings</a>
            <a href="admin_logout.php" class="logout">Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="back-container">
            <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <h2>customer inquiries.</h2>

        <div class="content-box">
            <ul class="chat-list">
                <?php if ($chat_list->num_rows > 0): ?>
                    <?php while($user = $chat_list->fetch_assoc()): ?>
                        <li class="chat-item">
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                                <div class="user-name">
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </div>
                            </div>
                            <a href="admin_chat_view.php?user_id=<?php echo $user['user_id']; ?>" class="btn-view">View Chat</a>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #bbb;">
                        <p>No active inquiries found. Quiet day at the shop!</p>
                    </div>
                <?php endif; ?>
            </ul>
        </div>
    </dhistoryadmin_istoryLogin 