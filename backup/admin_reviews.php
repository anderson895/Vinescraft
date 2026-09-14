<?php
session_start();
require_once 'db_connect.php';

// PROTECTION: Admin session check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// LOGIC: Admin Reply
if (isset($_POST['send_reply'])) {
    $rid = intval($_POST['review_id']);
    $reply = mysqli_real_escape_string($conn, $_POST['reply_text']);
    $conn->query("UPDATE reviews SET admin_reply = '$reply' WHERE review_id = $rid");
}

// FETCH: Reviews joined with users and products
$all_reviews = $conn->query("SELECT reviews.*, users.name as customer, products.name as prod_name 
                             FROM reviews 
                             JOIN users ON reviews.user_id = users.user_id 
                             JOIN products ON reviews.product_id = products.product_id 
                             ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Reviews | Vinescraft Admin</title>
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

        /* REVIEW CARDS */
        .review-card { background: white; padding: 25px; border-radius: 20px; border: 1px solid var(--peach); margin-bottom: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
        .review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .product-tag { font-size: 10px; font-weight: 900; text-transform: uppercase; color: #aaa; letter-spacing: 1px; }
        .customer-name { color: var(--coral); font-weight: 900; font-size: 16px; margin: 5px 0; display: block; }
        
        .rating-stars { color: #ffca28; font-size: 14px; margin-bottom: 10px; }
        .comment-text { font-size: 14px; line-height: 1.6; color: #666; font-style: italic; }

        /* REPLY BOX STYLES */
        .reply-section { margin-top: 20px; padding-top: 20px; border-top: 1px dashed var(--peach); }
        .admin-reply-box { background: #fffafb; padding: 15px; border-radius: 12px; border-left: 4px solid var(--coral); font-size: 13px; }
        .reply-input { width: 100%; padding: 12px; border: 1px solid var(--peach); border-radius: 10px; font-family: 'Montserrat', sans-serif; font-size: 12px; margin-bottom: 10px; box-sizing: border-box; }
        .btn-reply { background: var(--coral); color: white; border: none; padding: 10px 20px; border-radius: 50px; font-weight: 900; font-size: 10px; text-transform: uppercase; cursor: pointer; transition: 0.3s; }
        .btn-reply:hover { background: #e07661; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

    <!-- SIDEBAR NAVIGATION: Changed Chat Support to Login History and Logout to admin_logout.php -->
    <div class="sidebar">
        <h1>vinescraft.</h1>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard Home</a>
            <a href="admin_orders.php">Manage Orders</a>
            <a href="admin_products.php">Manage Products</a>
            <a href="admin_materials.php">Manage Materials</a>
            <a href="admin_reviews.php" class="active">Reviews</a>
            <a href="admin_messages.php">Messages</a>
            <a href="admin_history.php">Login History</a> <!-- Updated Link -->
            <a href="admin_settings.php">Account Settings</a>
            <a href="admin_logout.php" class="logout">Logout</a> <!-- Updated Link -->
        </div>
    </div>

    <div class="main-content">
        <div class="back-container">
            <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <h2>customer reviews.</h2>

        <?php if ($all_reviews->num_rows > 0): ?>
            <?php while($rev = $all_reviews->fetch_assoc()): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div>
                            <span class="product-tag">Product: <?php echo htmlspecialchars($rev['prod_name']); ?></span>
                            <span class="customer-name"><?php echo htmlspecialchars($rev['customer']); ?></span>
                        </div>
                        <div class="rating-stars">
                            <?php 
                                for($i=1; $i<=5; $i++) {
                                    echo ($i <= $rev['rating']) ? "★" : "☆";
                                }
                            ?>
                        </div>
                    </div>
                    
                    <p class="comment-text">"<?php echo htmlspecialchars($rev['comment']); ?>"</p>

                    <div class="reply-section">
                        <?php if(empty($rev['admin_reply'])): ?>
                            <form method="POST">
                                <input type="hidden" name="review_id" value="<?php echo $rev['review_id']; ?>">
                                <input type="text" name="reply_text" class="reply-input" placeholder="Type your response to this customer..." required>
                                <button type="submit" name="send_reply" class="btn-reply">Send Reply</button>
                            </form>
                        <?php else: ?>
                            <div class="admin-reply-box">
                                <strong style="color: var(--coral); font-size: 10px; text-transform: uppercase; display: block; margin-bottom: 5px;">Admin Response:</strong>
                                <?php echo htmlspecialchars($rev['admin_reply']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="background: white; padding: 50px; border-radius: 20px; text-align: center; border: 1px solid var(--peach);">
                <p style="color: #bbb;">No reviews received yet. Your flowers are still blooming!</p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>