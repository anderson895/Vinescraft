<?php
session_start();
require_once 'db_connect.php';

// PROTECTION: Admin session check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// FETCH: Kunin ang logs at i-join sa users table
$logs = $conn->query("SELECT login_history.*, users.name 
                      FROM login_history 
                      LEFT JOIN users ON login_history.user_id = users.user_id 
                      ORDER BY login_time DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login History | Vinescraft Admin</title>
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

        /* CONTENT BOX & TABLE */
        .content-box { background: white; border-radius: 20px; border: 1px solid var(--peach); overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--coral); color: white; padding: 18px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; }
        td { padding: 15px; border-bottom: 1px solid var(--peach); font-size: 13px; }

        .role-badge { padding: 4px 10px; border-radius: 50px; font-size: 9px; font-weight: 900; text-transform: uppercase; }
        .role-admin { background: var(--peach); color: var(--coral); }
        .role-user { background: #eee; color: #888; }
        
        .ip-text { font-family: monospace; color: #888; font-size: 12px; }
        .time-text { color: #aaa; font-size: 11px; }
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
            <a href="admin_history.php" class="active">Login History</a>
            <a href="admin_settings.php">Account Settings</a>
            <a href="index.php">View Website</a>
            <a href="admin_logout.php" class="logout">Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="back-container">
            <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <h2>login history.</h2>

        <div class="content-box">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name / User ID</th>
                        <th>Role</th>
                        <th>IP Address</th>
                        <th>Login Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($logs->num_rows > 0): while($row = $logs->fetch_assoc()): ?>
                    <tr>
                        <td style="color: #ccc; font-weight: 700;">#<?php echo $row['history_id']; ?></td>
                        <td>
                            <strong style="color: var(--text);">
                                <?php echo ($row['role'] == 'admin') ? "Admin (ID: ".$row['user_id'].")" : htmlspecialchars($row['name']); ?>
                            </strong>
                        </td>
                        <td>
                            <span class="role-badge <?php echo ($row['role'] == 'admin') ? 'role-admin' : 'role-user'; ?>">
                                <?php echo $row['role']; ?>
                            </span>
                        </td>
                        <td class="ip-text"><?php echo $row['ip_address']; ?></td>
                        <td class="time-text"><?php echo date('M d, Y - h:i A', strtotime($row['login_time'])); ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:50px; color:#aaa;">No login logs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>