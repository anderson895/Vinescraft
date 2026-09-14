<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_logged_in'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Support | Vinescraft</title>
    <!-- Idinagdag natin ang jQuery para sa AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
        :root { --coral: #f18973; --peach: #fce0d8; --text: #444; --bg: #fdf2f2; }
        body { font-family: 'Montserrat', sans-serif; background-color: var(--bg); margin: 0; color: var(--text); display: flex; flex-direction: column; height: 100vh; }
        
        .top-nav { background: var(--coral); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; color: white; flex-shrink: 0; }
        .top-nav a { color: white; text-decoration: none; font-size: 10px; font-weight: 900; text-transform: uppercase; margin-left: 15px; letter-spacing: 1px; }

        .chat-container { flex-grow: 1; max-width: 600px; width: 90%; margin: 20px auto; display: flex; flex-direction: column; background: white; border-radius: 30px; border: 1px solid var(--peach); box-shadow: 0 15px 35px rgba(241, 137, 115, 0.1); overflow: hidden; position: relative; }
        .chat-header { padding: 20px; background: white; border-bottom: 1px solid var(--peach); text-align: center; }
        .chat-header h2 { font-size: 20px; font-weight: 900; color: var(--coral); margin: 0; text-transform: lowercase; }
        .chat-header h2::after { content: '.'; }

        .chat-box { flex-grow: 1; padding: 25px; overflow-y: auto; background: #fff; display: flex; flex-direction: column; gap: 15px; }
        .chat-box::-webkit-scrollbar { width: 6px; }
        .chat-box::-webkit-scrollbar-thumb { background: var(--peach); border-radius: 10px; }

        .msg-bubble { max-width: 75%; padding: 12px 18px; font-size: 14px; line-height: 1.5; position: relative; }
        .user-msg { align-self: flex-end; background: var(--peach); color: var(--text); border-radius: 20px 20px 0 20px; text-align: right; }
        .admin-msg { align-self: flex-start; background: var(--bg); color: var(--text); border-radius: 20px 20px 20px 0; border: 1px solid var(--peach); }
        .msg-time { display: block; font-size: 8px; font-weight: 700; text-transform: uppercase; margin-top: 5px; opacity: 0.4; }

        .chat-footer { padding: 20px; background: white; border-top: 1px solid var(--peach); }
        .input-group { display: flex; gap: 10px; }
        input[type="text"] { flex-grow: 1; padding: 15px 25px; border: 1px solid var(--peach); border-radius: 50px; font-family: 'Montserrat'; outline: none; font-size: 13px; background: var(--bg); }
        .btn-send { background: var(--coral); color: white; border: none; padding: 0 30px; border-radius: 50px; font-weight: 900; text-transform: uppercase; font-size: 11px; cursor: pointer; transition: 0.3s; }
        .btn-send:hover { background: #e07661; transform: translateY(-2px); }
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

<div class="chat-container">
    <div class="chat-header">
        <h2>customer support</h2>
    </div>
    
    <!-- Dito lilitaw ang messages galing sa fetch_messages.php -->
    <div class="chat-box" id="chatBox">
        <div style="text-align: center; color: #aaa; margin-top: 50px;">Loading chat...</div>
    </div>

    <div class="chat-footer">
        <form id="chatForm" class="input-group">
            <input type="text" id="messageInput" name="message" placeholder="Type a message..." required autocomplete="off">
            <button type="submit" class="btn-send">Send</button>
        </form>
    </div>
</div>

<script>
    // 1. Function para kuhanin ang messages nang walang refresh
    function loadMessages() {
        $.ajax({
            url: 'fetch_messages.php',
            type: 'GET',
            success: function(data) {
                const chatBox = $('#chatBox');
                const oldScrollHeight = chatBox[0].scrollHeight;
                const oldScrollTop = chatBox.scrollTop();
                const isAtBottom = (oldScrollTop + chatBox.innerHeight() >= oldScrollHeight - 50);

                chatBox.html(data);

                // Auto-scroll pababa kung ang user ay nasa bottom na dati
                if (isAtBottom) {
                    chatBox.scrollTop(chatBox[0].scrollHeight);
                }
            }
        });
    }

    // 2. I-load ang messages agad pagbukas ng page
    $(document).ready(function() {
        loadMessages();
        
        // I-set ang timer para mag-check tuwing 2 seconds (2000ms)
        setInterval(loadMessages, 2000);

        // 3. Handle sending message gamit ang AJAX para hindi mag-reload
        $('#chatForm').on('submit', function(e) {
            e.preventDefault();
            const msg = $('#messageInput').val();

            if (msg.trim() !== "") {
                $.ajax({
                    url: 'chat.php', // I-sesend natin sa sarili nya (o gawan mo ng hiwalay na save_chat.php)
                    type: 'POST',
                    data: { send_msg: true, message: msg },
                    success: function() {
                        $('#messageInput').val(''); // Clear input
                        loadMessages(); // Refresh agad ang view
                    }
                });
            }
        });
    });

    // Code for handling POST request directly in chat.php (Backend Part)
    <?php
    if (isset($_POST['send_msg'])) {
        $u_id = $_SESSION['user_id'];
        $msg = mysqli_real_escape_string($conn, $_POST['message']);
        $conn->query("INSERT INTO messages (user_id, sender_type, message_text) VALUES ($u_id, 'user', '$msg')");
        exit; // Itigil ang script dito para hindi na mag-output ng HTML sa AJAX call
    }
    ?>
</script>

</body>
</html>