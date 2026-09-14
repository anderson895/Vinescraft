<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$target_user = intval($_GET['user_id']);
$user_info = $conn->query("SELECT name FROM users WHERE user_id = $target_user")->fetch_assoc();

// Reply Logic for AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_reply'])) {
    $msg = mysqli_real_escape_string($conn, $_POST['message']);
    if (!empty(trim($msg))) {
        $conn->query("INSERT INTO messages (user_id, sender_type, message_text) VALUES ($target_user, 'admin', '$msg')");
    }
    exit; // Stop executing to prevent HTML output during AJAX call
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat with <?php echo htmlspecialchars($user_info['name']); ?> | Admin</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
        :root { --coral: #f18973; --peach: #fce0d8; --text: #444; }
        body { font-family: 'Montserrat', sans-serif; background: #fffafb; margin: 0; display: flex; color: var(--text); height: 100vh; overflow: hidden; }
        
        .sidebar { width: 260px; background: white; height: 100vh; border-right: 1px solid var(--peach); padding: 30px 20px; position: fixed; }
        .sidebar h1 { color: var(--coral); font-size: 20px; font-weight: 900; text-transform: lowercase; margin-bottom: 40px; }
        .sidebar h1::after { content: '.'; }
        .nav-links { display: flex; flex-direction: column; gap: 10px; }
        .nav-links a { text-decoration: none; color: #888; font-size: 13px; font-weight: 700; padding: 12px 20px; border-radius: 10px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: var(--peach); color: var(--coral); }
        .nav-links a.logout { margin-top: 20px; color: #ff4757; }

        .main-content { margin-left: 300px; padding: 40px; width: calc(100% - 340px); display: flex; flex-direction: column; height: 100vh; box-sizing: border-box; }
        .chat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-shrink: 0; }
        .chat-header h2 { font-size: 24px; font-weight: 900; color: var(--coral); margin: 0; text-transform: lowercase; }
        .chat-header h2::after { content: '.'; }
        .back-btn { text-decoration: none; color: var(--coral); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }

        .chat-container { flex-grow: 1; background: white; border: 1px solid var(--peach); border-radius: 20px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .chat-box { flex-grow: 1; padding: 25px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; background: #fffcfd; }
        
        .msg { max-width: 70%; padding: 12px 18px; border-radius: 15px; font-size: 14px; line-height: 1.5; position: relative; }
        .user-msg { align-self: flex-start; background: #eee; color: #555; border-bottom-left-radius: 2px; }
        .admin-msg { align-self: flex-end; background: var(--coral); color: white; border-bottom-right-radius: 2px; box-shadow: 0 5px 15px rgba(241,137,115,0.2); }
        .msg-meta { font-size: 9px; margin-top: 5px; opacity: 0.7; display: block; }

        .input-area { padding: 20px; background: white; border-top: 1px solid var(--peach); flex-shrink: 0; }
        .input-form { display: flex; gap: 10px; }
        textarea { flex-grow: 1; padding: 15px; border: 1px solid var(--peach); border-radius: 15px; font-family: 'Montserrat'; font-size: 13px; resize: none; height: 50px; outline: none; }
        .btn-send { background: var(--coral); color: white; border: none; padding: 0 25px; border-radius: 15px; font-weight: 900; font-size: 11px; text-transform: uppercase; cursor: pointer; transition: 0.3s; }
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
            <a href="admin_reviews.php">Reviews</a>
            <a href="admin_messages.php" class="active">Messages</a>
            <a href="admin_settings.php">Account Settings</a>
            <a href="admin_logout.php" class="logout">Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="chat-header">
            <a href="admin_messages.php" class="back-btn">← Back to Messages</a>
            <h2>chatting with: <?php echo htmlspecialchars($user_info['name']); ?></h2>
        </div>

        <div class="chat-container">
            <!-- AJAX will load messages here -->
            <div class="chat-box" id="chatBox">
                <div style="text-align: center; color: #ccc; margin-top: 50px;">Loading chat...</div>
            </div>

            <div class="input-area">
                <form id="adminChatForm" class="input-form">
                    <textarea id="adminMsgInput" name="message" placeholder="Type your response here..." required></textarea>
                    <button type="submit" class="btn-send">Send</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function loadMessages() {
            $.ajax({
                url: 'fetch_admin_messages.php?user_id=<?php echo $target_user; ?>',
                type: 'GET',
                success: function(data) {
                    const chatBox = $('#chatBox');
                    const oldScrollHeight = chatBox[0].scrollHeight;
                    const oldScrollTop = chatBox.scrollTop();
                    const isAtBottom = (oldScrollTop + chatBox.innerHeight() >= oldScrollHeight - 50);

                    chatBox.html(data);

                    if (isAtBottom) {
                        chatBox.scrollTop(chatBox[0].scrollHeight);
                    }
                }
            });
        }

        $(document).ready(function() {
            loadMessages();
            setInterval(loadMessages, 2000);

            $('#adminChatForm').on('submit', function(e) {
                e.preventDefault();
                const msg = $('#adminMsgInput').val();

                if (msg.trim() !== "") {
                    $.ajax({
                        url: 'admin_chat_view.php?user_id=<?php echo $target_user; ?>',
                        type: 'POST',
                        data: { admin_reply: true, message: msg },
                        success: function() {
                            $('#adminMsgInput').val(''); 
                            loadMessages(); 
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>