<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. KUNIN ANG MGA COUNTS PARA SA BADGES
$nav_active_orders_count = 0;
$unread_chat_count = 0;
$cart_count = 0;

// Count items in Cart
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += is_array($item) ? ($item['qty'] ?? 1) : $item;
    }
}

if (isset($_SESSION['user_id']) && isset($conn)) {
    $nav_uid = $_SESSION['user_id'];
    
    // Active Orders Count
    $nav_ord_res = $conn->query("SELECT COUNT(*) as active_count FROM orders WHERE user_id = $nav_uid AND status NOT IN ('Completed', 'Cancelled')");
    if ($nav_ord_res) {
        $nav_active_orders_count = $nav_ord_res->fetch_assoc()['active_count'];
    }

    // Unread Chat Messages (Admin to User)
    $chat_res = $conn->query("SELECT COUNT(*) as unread FROM messages WHERE user_id = $nav_uid AND sender_type = 'admin' AND is_read = 0");
    if ($chat_res) {
        $unread_chat_count = $chat_res->fetch_assoc()['unread'];
    }
}
?>
<style>
    /* TOP BAR CONTAINER */
    .top-nav-container {
        background: var(--coral, #f18973);
        padding: 15px 5%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: 0 4px 15px rgba(241, 137, 115, 0.2);
    }
    
    .top-nav-container .nav-brand {
        font-family: 'Montserrat', sans-serif;
        font-weight: 900; 
        font-size: 20px; 
        text-transform: lowercase;
        letter-spacing: 1px;
        color: white;
        text-decoration: none;
    }

    /* HAMBURGER BUTTON */
    .hamburger-btn {
        background: none;
        border: none;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        gap: 5px;
        padding: 5px;
        z-index: 1002;
    }
    .hamburger-btn span {
        display: block;
        width: 25px;
        height: 3px;
        background-color: white;
        border-radius: 3px;
        transition: transform 0.3s ease, opacity 0.3s ease, background-color 0.3s ease;
    }
    
    /* SIDEBAR MENU */
    .side-menu {
        position: fixed;
        top: 0;
        right: -300px;
        width: 250px;
        height: 100vh;
        background: white;
        box-shadow: -5px 0 25px rgba(0,0,0,0.15);
        z-index: 1001;
        transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        padding: 80px 30px 30px;
        box-sizing: border-box;
    }
    
    .side-menu.open {
        right: 0;
    }

    .side-menu a {
        color: var(--text, #444);
        text-decoration: none;
        font-family: 'Montserrat', sans-serif;
        font-size: 13px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 15px 0;
        border-bottom: 1px solid var(--peach, #fce0d8);
        transition: 0.3s;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .side-menu a:hover {
        color: var(--coral, #f18973);
        padding-left: 10px;
        border-bottom-color: var(--coral, #f18973);
    }
    
    /* HAMBURGER ANIMATION (Turns into X) */
    .hamburger-btn.open span:nth-child(1) {
        transform: translateY(8px) rotate(45deg);
        background-color: var(--coral, #f18973);
    }
    .hamburger-btn.open span:nth-child(2) {
        opacity: 0;
    }
    .hamburger-btn.open span:nth-child(3) {
        transform: translateY(-8px) rotate(-45deg);
        background-color: var(--coral, #f18973);
    }

    /* BACKGROUND OVERLAY */
    .menu-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(241, 137, 115, 0.4);
        backdrop-filter: blur(3px);
        z-index: 1000;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .menu-overlay.show {
        display: block;
        opacity: 1;
    }

    /* MENU BADGES */
    .side-menu-badge, .nav-cart-badge {
        background: #ff4757;
        color: white;
        padding: 3px 8px;
        border-radius: 50px;
        font-size: 10px;
        font-weight: 900;
        box-shadow: 0 2px 5px rgba(255, 71, 87, 0.3);
    }

    /* FLOATING CHAT BUTTON */
    .floating-chat-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 65px;
        height: 65px;
        background: var(--coral, #f18973);
        color: white;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        text-decoration: none;
        box-shadow: 0 10px 30px rgba(241, 137, 115, 0.4);
        z-index: 9999;
        font-size: 30px;
        transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }
    .floating-chat-btn:hover {
        transform: scale(1.1) translateY(-5px);
        background: #e07661;
    }
    .floating-badge {
        position: absolute;
        top: -2px;
        right: -2px;
        background: #ff4757;
        color: white;
        font-size: 11px;
        font-weight: 900;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        border: 2px solid white;
        box-shadow: 0 4px 10px rgba(255,71,87,0.3);
    }

    /* CHAT POPUP WINDOW STYLES */
    .chat-popup {
        display: none;
        position: fixed;
        bottom: 110px;
        right: 30px;
        width: 360px;
        height: 520px;
        background: white;
        border-radius: 20px;
        box-shadow: 0 15px 40px rgba(241, 137, 115, 0.25);
        border: 1px solid var(--peach, #fce0d8);
        z-index: 10000;
        flex-direction: column;
        overflow: hidden;
        font-family: 'Montserrat', sans-serif;
    }
    @media (max-width: 450px) {
        .chat-popup {
            width: 90%;
            right: 5%;
            height: 480px;
            bottom: 100px;
        }
    }
    .chat-popup-header {
        padding: 15px 20px;
        background: white;
        border-bottom: 1px solid var(--peach, #fce0d8);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .chat-popup-body {
        flex-grow: 1;
        padding: 15px;
        overflow-y: auto;
        background: #fdf2f2;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .chat-popup-body::-webkit-scrollbar { width: 6px; }
    .chat-popup-body::-webkit-scrollbar-thumb { background: var(--peach, #fce0d8); border-radius: 10px; }
    
    .msg-bubble {
        max-width: 80%;
        padding: 12px 16px;
        font-size: 13px;
        line-height: 1.4;
        position: relative;
    }
    .user-msg { align-self: flex-end; background: var(--peach, #fce0d8); color: var(--text, #444); border-radius: 18px 18px 0 18px; text-align: right; }
    .admin-msg { align-self: flex-start; background: white; color: var(--text, #444); border-radius: 18px 18px 18px 0; border: 1px solid var(--peach, #fce0d8); }
    .msg-time { display: block; font-size: 8px; font-weight: 700; text-transform: uppercase; margin-top: 5px; opacity: 0.5; }
    .chat-img { max-width: 100%; border-radius: 8px; margin-top: 5px; border: 1px solid rgba(0,0,0,0.1); }
    
    .chat-popup-footer {
        padding: 15px;
        background: white;
        border-top: 1px solid var(--peach, #fce0d8);
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .popup-input-group { display: flex; gap: 8px; align-items: center; }
    #popupMessageInput {
        flex-grow: 1; padding: 12px 15px; border: 1px solid var(--peach, #fce0d8); border-radius: 50px;
        font-family: 'Montserrat', sans-serif; font-size: 12px; outline: none; background: #fdf2f2;
    }
    .btn-popup-attach {
        background: var(--peach, #fce0d8); color: var(--coral, #f18973); border: none; width: 40px; height: 40px;
        border-radius: 50%; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s;
    }
    .btn-popup-attach:hover { background: #f8c9bf; }
    .btn-popup-send {
        background: var(--coral, #f18973); color: white; border: none; padding: 0 18px; border-radius: 50px; height: 40px;
        font-weight: 900; text-transform: uppercase; font-size: 10px; cursor: pointer; transition: 0.3s;
    }
    .btn-popup-send:hover { background: #e07661; transform: translateY(-2px); }
</style>

<!-- TOP NAV BAR -->
<div class="top-nav-container">
    <a href="index.php" class="nav-brand" style="border: none; background: none; box-shadow: none; padding: 0 !important;">
        <img src="img/chubs_logo.png" alt="Chub's Handicrafts" style="max-height: 65px; width: auto; object-fit: contain;">
    </a>
    <button class="hamburger-btn" id="hamburgerBtn">
        <span></span>
        <span></span>
        <span></span>
    </button>
</div>

<!-- BACKGROUND OVERLAY -->
<div class="menu-overlay" id="menuOverlay"></div>

<!-- HIDDEN SIDE MENU -->
<div class="side-menu" id="sideMenu">
    <a href="index.php">Home</a>
    <a href="shop.php">Shop</a>
    <a href="customizer.php">Custom Bouquet</a>
    <a href="customize_tshirt.php">Custom Shirt</a>
    <a href="about.php">Our Story</a>
    
    <a href="cart.php" id="navCartLink">
        Cart
        <?php if($cart_count > 0): ?>
            <span class="nav-cart-badge"><?php echo $cart_count; ?></span>
        <?php endif; ?>
    </a>

    <a href="my_orders.php" id="navOrderLink">
        Orders
        <?php if(!empty($nav_active_orders_count) && $nav_active_orders_count > 0): ?>
            <span class="side-menu-badge"><?php echo $nav_active_orders_count; ?></span>
        <?php endif; ?>
    </a>
    
    <?php if(!empty($_SESSION['user_logged_in'])): ?>
        <a href="profile.php">Account</a>
        <a href="logout.php" style="font-size: 11px; opacity: 0.8; color: #ff4757;">Logout</a>
    <?php else: ?>
        <a href="login.php">Login / Register</a>
    <?php endif; ?>
</div>

<!-- FLOATING CHAT BUTTON & POPUP -->
<?php if(!empty($_SESSION['user_logged_in'])): ?>
    <a href="javascript:void(0);" class="floating-chat-btn" id="floatingChatBtn" onclick="toggleChatPopup()">
        💬
        <?php if($unread_chat_count > 0): ?>
            <span class="floating-badge" id="chatNavBadge"><?php echo $unread_chat_count; ?></span>
        <?php endif; ?>
    </a>

    <!-- CHAT POPUP WINDOW -->
    <div id="chatPopup" class="chat-popup">
        <div class="chat-popup-header">
            <span style="font-weight: 900; color: var(--coral, #f18973); font-size: 16px;">Customer Support</span>
            <button onclick="toggleChatPopup()" style="background:none; border:none; font-size:18px; cursor:pointer; color:#aaa; font-weight:bold;">✕</button>
        </div>
        
        <div class="chat-popup-body" id="popupChatBox">
            <div style="text-align: center; color: #aaa; margin-top: 50px; font-size:12px;">Loading chat...</div>
        </div>

        <div class="chat-popup-footer">
            <div id="popupImagePreview" style="font-size: 10px; color: var(--coral); font-weight: 700; display: none; padding-left: 5px;"></div>
            <form id="popupChatForm" class="popup-input-group" enctype="multipart/form-data">
                <input type="file" id="popupChatImage" name="chat_image" accept="image/*" style="display: none;" onchange="previewPopupImage(this)">
                <button type="button" class="btn-popup-attach" onclick="document.getElementById('popupChatImage').click()">📎</button>
                <input type="text" id="popupMessageInput" name="message" placeholder="Type a message..." autocomplete="off">
                <button type="submit" class="btn-popup-send">Send</button>
            </form>
        </div>
    </div>

    <script>
        let chatInterval;
        let lastChatHTML = ""; // Variable to track if HTML actually changed

        function toggleChatPopup() {
            const popup = document.getElementById('chatPopup');
            if(popup.style.display === 'flex') {
                popup.style.display = 'none';
                clearInterval(chatInterval);
            } else {
                popup.style.display = 'flex';
                lastChatHTML = ""; // Reset HTML tracker when opened
                loadPopupMessages(true); // true = force scroll to bottom on open
                
                // Refresh messages every 2 seconds while popup is open
                chatInterval = setInterval(() => loadPopupMessages(false), 2000);
                
                // Hide the notification badge visually once the chat is opened
                let badge = document.getElementById('chatNavBadge');
                if(badge) badge.style.display = 'none';
            }
        }

        function loadPopupMessages(forceScroll = false) {
            fetch('fetch_messages.php')
            .then(response => response.text())
            .then(data => {
                const chatBox = document.getElementById('popupChatBox');
                
                // ONLY update the chat box if the new data is different from the old data
                if (data !== lastChatHTML) {
                    // Check if user is scrolled near the bottom BEFORE replacing HTML
                    const isAtBottom = (chatBox.scrollTop + chatBox.clientHeight >= chatBox.scrollHeight - 50);
                    
                    chatBox.innerHTML = data;
                    lastChatHTML = data;
                    
                    // Auto-scroll to bottom if they were already at the bottom, or if we force it
                    if (isAtBottom || forceScroll) {
                        setTimeout(() => {
                            chatBox.scrollTop = chatBox.scrollHeight;
                        }, 50); // slight delay to allow images/DOM to render properly before scrolling
                    }
                }
            });
        }

        function previewPopupImage(input) {
            const preview = document.getElementById('popupImagePreview');
            if(input.files && input.files[0]) {
                preview.innerText = 'Attached: ' + input.files[0].name;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }

        // Handle sending the message via AJAX without reloading
        document.getElementById('popupChatForm').addEventListener('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            formData.append('send_msg', 'true');

            // Send to chat.php which already handles the database insertion
            fetch('chat.php', {
                method: 'POST',
                body: formData
            })
            .then(() => {
                document.getElementById('popupMessageInput').value = '';
                document.getElementById('popupChatImage').value = '';
                document.getElementById('popupImagePreview').style.display = 'none';
                
                // Force an immediate reload and scroll to bottom
                loadPopupMessages(true); 
            });
        });
    </script>
<?php else: ?>
    <a href="login.php" class="floating-chat-btn" onclick="alert('Please login to use the chat feature.');">
        💬
    </a>
<?php endif; ?>

<script>
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sideMenu = document.getElementById('sideMenu');
    const menuOverlay = document.getElementById('menuOverlay');

    function toggleMenu() {
        hamburgerBtn.classList.toggle('open');
        sideMenu.classList.toggle('open');
        
        if (menuOverlay.classList.contains('show')) {
            menuOverlay.classList.remove('show');
            setTimeout(() => menuOverlay.style.display = 'none', 300);
        } else {
            menuOverlay.style.display = 'block';
            setTimeout(() => menuOverlay.classList.add('show'), 10);
        }
    }

    hamburgerBtn.addEventListener('click', toggleMenu);
    menuOverlay.addEventListener('click', toggleMenu);
</script>