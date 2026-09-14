<?php
session_start();
require_once 'db_connect.php';

// PROTECTION: Admin session check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// --- AUTO-CREATE 'is_read' COLUMN PARA HINDI KA NA MAG-MANUAL SA DATABASE ---
$check_col = $conn->query("SHOW COLUMNS FROM messages LIKE 'is_read'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE messages ADD is_read TINYINT(1) DEFAULT 0");
}

// FETCH: Kunin lahat ng users na may message history at bilangin ang unread
$chat_list = $conn->query("
    SELECT u.user_id, u.name, MAX(m.created_at) as last_msg_time,
           SUM(CASE WHEN m.sender_type = 'user' AND m.is_read = 0 THEN 1 ELSE 0 END) as unread_count
    FROM messages m 
    JOIN users u ON m.user_id = u.user_id 
    GROUP BY u.user_id, u.name 
    ORDER BY last_msg_time DESC
");

// FETCH: Kunin yung total na unread para sa Sidebar Badge
$unread_total_query = $conn->query("SELECT COUNT(*) as unread FROM messages WHERE sender_type = 'user' AND is_read = 0");
$unread_count = $unread_total_query ? $unread_total_query->fetch_assoc()['unread'] : 0;
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
        
        /* SIDEBAR STYLE */
        .sidebar { width: 260px; background: white; height: 100vh; border-right: 1px solid var(--peach); padding: 30px 20px; position: fixed; }
        .sidebar h1 { color: var(--coral); font-size: 20px; font-weight: 900; text-transform: lowercase; margin-bottom: 40px; }
        
        .nav-links { display: flex; flex-direction: column; gap: 10px; }
        .nav-links a { text-decoration: none; color: #888; font-size: 13px; font-weight: 700; padding: 12px 20px; border-radius: 10px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: var(--peach); color: var(--coral); }
        .nav-links a.logout { margin-top: 20px; color: #ff4757; }

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

        /* MAIN CONTENT AREA */
        .main-content { margin-left: 300px; padding: 40px; width: calc(100% - 340px); }
        h2 { font-size: 32px; font-weight: 900; color: var(--coral); margin: 0 0 10px; }
       
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
        
        .user-name { font-weight: 700; color: var(--text); font-size: 15px; display: flex; align-items: center; }
        
        /* RED DOT PARA SA CHAT LIST */
        .red-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            background-color: #ff4757;
            border-radius: 50%;
            margin-left: 8px;
            box-shadow: 0 0 8px rgba(255, 71, 87, 0.6);
        }
        
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

<?php include 'admin_sidebar.php' ?>

    <div class="main-content">
        <div class="back-container">
            <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <h2>Customer Inquiries</h2>

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
                                    
                                    <?php if ($user['unread_count'] > 0): ?>
                                        <span class="red-dot" title="New Message"></span>
                                    <?php endif; ?>
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
    </div>

</body>
</html>