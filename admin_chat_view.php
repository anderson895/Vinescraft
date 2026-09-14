<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$target_user = intval($_GET['user_id']);
$user_info = $conn->query("SELECT name FROM users WHERE user_id = $target_user")->fetch_assoc();

// --- AUTO-CREATE 'image_path' COLUMN KUNG WALA PA ---
try {
    $check_col = $conn->query("SHOW COLUMNS FROM messages LIKE 'image_path'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE messages ADD image_path VARCHAR(255) NULL");
    }
} catch (Exception $e) {}

// POST HANDLER PARA SA AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_reply'])) {
    $msg = mysqli_real_escape_string($conn, $_POST['message'] ?? '');
    $image_path = "NULL";

    if (isset($_FILES['chat_image']) && $_FILES['chat_image']['error'] == 0) {
        $dir = 'uploads/chat_images/';
        if (!file_exists($dir)) mkdir($dir, 0777, true);
        
        $filename = time() . '_' . basename($_FILES['chat_image']['name']);
        $target = $dir . $filename;
        
        if (move_uploaded_file($_FILES['chat_image']['tmp_name'], $target)) {
            $image_path = "'" . mysqli_real_escape_string($conn, $filename) . "'";
        }
    }

    if (trim($msg) !== "" || $image_path !== "NULL") {
        $conn->query("INSERT INTO messages (user_id, sender_type, message_text, image_path) VALUES ($target_user, 'admin', '$msg', $image_path)");
    }
    exit; 
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
        .nav-links { display: flex; flex-direction: column; gap: 10px; }
        .nav-links a { text-decoration: none; color: #888; font-size: 13px; font-weight: 700; padding: 12px 20px; border-radius: 10px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: var(--peach); color: var(--coral); }
        .nav-links a.logout { margin-top: 20px; color: #ff4757; }

        .main-content { margin-left: 300px; padding: 40px; width: calc(100% - 340px); display: flex; flex-direction: column; height: 100vh; box-sizing: border-box; }
        .chat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-shrink: 0; }
        .chat-header h2 { font-size: 24px; font-weight: 900; color: var(--coral); margin: 0; }
        .back-btn { text-decoration: none; color: var(--coral); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }

        .chat-container { flex-grow: 1; background: white; border: 1px solid var(--peach); border-radius: 20px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .chat-box { flex-grow: 1; padding: 25px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; background: #fffcfd; }
        
        .msg { max-width: 70%; padding: 12px 18px; border-radius: 15px; font-size: 14px; line-height: 1.5; position: relative; }
        .user-msg { align-self: flex-start; background: #eee; color: #555; border-bottom-left-radius: 2px; }
        .admin-msg { align-self: flex-end; background: var(--coral); color: white; border-bottom-right-radius: 2px; box-shadow: 0 5px 15px rgba(241,137,115,0.2); }
        .msg-meta { font-size: 9px; margin-top: 5px; opacity: 0.7; display: block; }
        
        .chat-img { max-width: 100%; border-radius: 10px; margin-top: 5px; border: 1px solid rgba(0,0,0,0.1); }

        .input-area { padding: 20px; background: white; border-top: 1px solid var(--peach); flex-shrink: 0; display: flex; flex-direction: column; gap: 10px; }
        .input-form { display: flex; gap: 10px; align-items: center; }
        textarea { flex-grow: 1; padding: 15px; border: 1px solid var(--peach); border-radius: 15px; font-family: 'Montserrat'; font-size: 13px; resize: none; height: 20px; outline: none; }
        
        .btn-attach { background: var(--peach); color: var(--coral); border: none; width: 50px; height: 50px; border-radius: 15px; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
        .btn-attach:hover { background: #f8c9bf; }
        .btn-send { background: var(--coral); color: white; border: none; padding: 0 25px; border-radius: 15px; font-weight: 900; font-size: 11px; height: 50px; text-transform: uppercase; cursor: pointer; transition: 0.3s; }
        
        #imagePreview { font-size: 11px; color: var(--coral); font-weight: 700; display: none; padding-left: 5px; }
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
            <h2><?php echo htmlspecialchars($user_info['name']); ?></h2>
        </div>

        <div class="chat-container">
            <div class="chat-box" id="chatBox">
                <div style="text-align: center; color: #ccc; margin-top: 50px;">Loading chat...</div>
            </div>

            <div class="input-area">
                <div id="imagePreview"></div>
                <form id="adminChatForm" class="input-form" enctype="multipart/form-data">
                    <input type="file" id="chatImage" name="chat_image" accept="image/*" style="display: none;">
                    <button type="button" class="btn-attach" onclick="document.getElementById('chatImage').click()">📎</button>
                    <textarea id="adminMsgInput" name="message" placeholder="Type your response here..."></textarea>
                    <button type="submit" class="btn-send">Send</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let lastAdminChatHTML = "";

        function loadMessages(forceScroll = false) {
            $.ajax({
                url: 'fetch_admin_messages.php?user_id=<?php echo $target_user; ?>',
                type: 'GET',
                success: function(data) {
                    if (data !== lastAdminChatHTML) {
                        const chatBox = $('#chatBox');
                        const chatBoxEl = chatBox[0];
                        
                        const isAtBottom = (chatBoxEl.scrollTop + chatBoxEl.clientHeight >= chatBoxEl.scrollHeight - 50);

                        chatBox.html(data);
                        lastAdminChatHTML = data;

                        if (isAtBottom || forceScroll) {
                            setTimeout(() => {
                                chatBoxEl.scrollTop = chatBoxEl.scrollHeight;
                            }, 50); 
                        }
                    }
                }
            });
        }

        $(document).ready(function() {
            loadMessages(true);
            setInterval(() => loadMessages(false), 2000);

            $('#chatImage').on('change', function() {
                if(this.files && this.files[0]) {
                    $('#imagePreview').text('Attached: ' + this.files[0].name).show();
                } else {
                    $('#imagePreview').hide();
                }
            });

            $('#adminChatForm').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('admin_reply', true);

                var msgVal = $('#adminMsgInput').val().trim();
                var imgVal = $('#chatImage').val();

                if (msgVal !== "" || imgVal !== "") {
                    $.ajax({
                        url: 'admin_chat_view.php?user_id=<?php echo $target_user; ?>',
                        type: 'POST',
                        data: formData,
                        contentType: false,
                        processData: false,
                        success: function() {
                            $('#adminMsgInput').val(''); 
                            $('#chatImage').val('');
                            $('#imagePreview').hide();
                            loadMessages(true); 
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>