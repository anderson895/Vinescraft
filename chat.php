<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_logged_in'])) {
    header("Location: login.php");
    exit();
}

// --- AUTO-CREATE 'image_path' COLUMN ---
try {
    $check_col = $conn->query("SHOW COLUMNS FROM messages LIKE 'image_path'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE messages ADD image_path VARCHAR(255) NULL");
    }
} catch (Exception $e) {}

// POST HANDLER PARA SA AJAX (Nasa itaas na para mas malinis)
if (isset($_POST['send_msg'])) {
    $u_id = $_SESSION['user_id'];
    $msg = mysqli_real_escape_string($conn, $_POST['message'] ?? '');
    $image_path = "NULL";

    // Handle Image Upload
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
        $conn->query("INSERT INTO messages (user_id, sender_type, message_text, image_path) VALUES ($u_id, 'user', '$msg', $image_path)");
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Support | Vinescraft</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
        :root { --coral: #f18973; --peach: #fce0d8; --text: #444; --bg: #fdf2f2; }
        body { font-family: 'Montserrat', sans-serif; background-color: var(--bg); margin: 0; color: var(--text); display: flex; flex-direction: column; height: 100vh; }
        
        .nav-badge { background: #ff4757; color: white; padding: 2px 6px; border-radius: 50px; font-size: 8px; font-weight: 900; margin-left: 3px; vertical-align: super; box-shadow: 0 0 5px rgba(255, 71, 87, 0.5); }
        .top-nav { background: var(--coral); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; color: white; flex-shrink: 0; }
        .top-nav a { color: white; text-decoration: none; font-size: 10px; font-weight: 900; text-transform: uppercase; margin-left: 15px; letter-spacing: 1px; }

        .chat-container { flex-grow: 1; max-width: 600px; width: 90%; margin: 20px auto; display: flex; flex-direction: column; background: white; border-radius: 30px; border: 1px solid var(--peach); box-shadow: 0 15px 35px rgba(241, 137, 115, 0.1); overflow: hidden; position: relative; }
        .chat-header { padding: 20px; background: white; border-bottom: 1px solid var(--peach); text-align: center; }
        .chat-header h2 { font-size: 20px; font-weight: 900; color: var(--coral); margin: 0; }

        .chat-box { flex-grow: 1; padding: 25px; overflow-y: auto; background: #fff; display: flex; flex-direction: column; gap: 15px; }
        .chat-box::-webkit-scrollbar { width: 6px; }
        .chat-box::-webkit-scrollbar-thumb { background: var(--peach); border-radius: 10px; }

        .msg-bubble { max-width: 75%; padding: 12px 18px; font-size: 14px; line-height: 1.5; position: relative; }
        .user-msg { align-self: flex-end; background: var(--peach); color: var(--text); border-radius: 20px 20px 0 20px; text-align: right; }
        .admin-msg { align-self: flex-start; background: var(--bg); color: var(--text); border-radius: 20px 20px 20px 0; border: 1px solid var(--peach); }
        .msg-time { display: block; font-size: 8px; font-weight: 700; text-transform: uppercase; margin-top: 5px; opacity: 0.4; }
        
        .chat-img { max-width: 100%; border-radius: 10px; margin-top: 5px; border: 1px solid rgba(0,0,0,0.1); }

        .chat-footer { padding: 20px; background: white; border-top: 1px solid var(--peach); display: flex; flex-direction: column; gap: 10px; }
        .input-group { display: flex; gap: 10px; align-items: center; }
        input[type="text"] { flex-grow: 1; padding: 15px 25px; border: 1px solid var(--peach); border-radius: 50px; font-family: 'Montserrat'; outline: none; font-size: 13px; background: var(--bg); }
        
        .btn-attach { background: var(--peach); color: var(--coral); border: none; width: 45px; height: 45px; border-radius: 50%; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
        .btn-attach:hover { background: #f8c9bf; }
        .btn-send { background: var(--coral); color: white; border: none; padding: 0 30px; border-radius: 50px; height: 45px; font-weight: 900; text-transform: uppercase; font-size: 11px; cursor: pointer; transition: 0.3s; }
        .btn-send:hover { background: #e07661; transform: translateY(-2px); }
        
        #imagePreview { font-size: 11px; color: var(--coral); font-weight: 700; display: none; padding-left: 10px; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

<?php include 'notifications.php'; ?>
<?php include 'navbar.php'; ?>

<div class="chat-container">
    <div class="chat-header">
        <h2>Customer Support</h2>
    </div>
    
    <div class="chat-box" id="chatBox">
        <div style="text-align: center; color: #aaa; margin-top: 50px;">Loading chat...</div>
    </div>

    <div class="chat-footer">
        <div id="imagePreview"></div>
        <form id="chatForm" class="input-group" enctype="multipart/form-data">
            <input type="file" id="chatImage" name="chat_image" accept="image/*" style="display: none;">
            <button type="button" class="btn-attach" onclick="document.getElementById('chatImage').click()">📎</button>
            <input type="text" id="messageInput" name="message" placeholder="Type a message..." autocomplete="off">
            <button type="submit" class="btn-send">Send</button>
        </form>
    </div>
</div>

<script>
    function loadMessages() {
        $.ajax({
            url: 'fetch_messages.php',
            type: 'GET',
            success: function(data) {
                const chatBox = $('#chatBox');
                const oldScrollTop = chatBox.scrollTop();
                const isAtBottom = (oldScrollTop + chatBox.innerHeight() >= chatBox[0].scrollHeight - 50);

                chatBox.html(data);
                if (isAtBottom) chatBox.scrollTop(chatBox[0].scrollHeight);
            }
        });
    }

    $(document).ready(function() {
        loadMessages();
        setInterval(loadMessages, 2000);

        // Preview attached image filename
        $('#chatImage').on('change', function() {
            if(this.files && this.files[0]) {
                $('#imagePreview').text('Attached: ' + this.files[0].name).show();
            } else {
                $('#imagePreview').hide();
            }
        });

        $('#chatForm').on('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            formData.append('send_msg', true);

            var msgVal = $('#messageInput').val().trim();
            var imgVal = $('#chatImage').val();

            if (msgVal !== "" || imgVal !== "") {
                $.ajax({
                    url: 'chat.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function() {
                        $('#messageInput').val('');
                        $('#chatImage').val('');
                        $('#imagePreview').hide();
                        loadMessages(); 
                    }
                });
            }
        });
    });
</script>

</body>
</html>