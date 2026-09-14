<?php
// Siguraduhin na naka-start ang session bago mag-execute ng logic
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php'; 

// =========================================================================
// 1. BACKGROUND CHECKER (AJAX) - Ito ang tatakbo nang palihim sa background
// =========================================================================
if (isset($_GET['ajax_check'])) {
    header('Content-Type: application/json');
    
    $active_orders_count = 0;
    $unread_chat_count = 0;
    $new_notifications = [];
    
    // --- BAGO: Cart count logic ---
    $cart_count = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $cart_count += is_array($item) ? ($item['qty'] ?? 1) : $item;
        }
    }

    if (isset($_SESSION['user_id'])) {
        $nav_uid = $_SESSION['user_id'];
        
        // Kunin ang active orders count para sa navigation badge
        $nav_ord_res = $conn->query("SELECT COUNT(*) as active_count FROM orders WHERE user_id = $nav_uid AND status NOT IN ('Completed', 'Cancelled')");
        if ($nav_ord_res) {
            $active_orders_count = $nav_ord_res->fetch_assoc()['active_count'];
        }

        // --- BAGO: Unread chat messages from admin ---
        $chat_res = $conn->query("SELECT COUNT(*) as unread FROM messages WHERE user_id = $nav_uid AND sender_type = 'admin' AND is_read = 0");
        if ($chat_res) {
            $unread_chat_count = $chat_res->fetch_assoc()['unread'];
        }

        // Silipin ang kasalukuyang statuses mula sa database
        $current_statuses = [];
        $stat_res = $conn->query("SELECT order_id, status FROM orders WHERE user_id = $nav_uid");
        if ($stat_res) {
            while ($row = $stat_res->fetch_assoc()) {
                $current_statuses[$row['order_id']] = $row['status'];
            }
        }

        // I-compare ang stored status sa pinakabagong status mula sa database
        if (isset($_SESSION['known_statuses'])) {
            foreach ($current_statuses as $oid => $status) {
                if (isset($_SESSION['known_statuses'][$oid])) {
                    if ($_SESSION['known_statuses'][$oid] !== $status) {
                        $msg = "";
                        switch($status) {
                            case 'To Pay': $msg = "Your order #$oid has been confirmed and is waiting for payment."; break;
                            case 'Processing': $msg = "Your order #$oid is now being processed."; break;
                            case 'To Ship': $msg = "Your order #$oid is prepared and ready to ship."; break;
                            case 'To Receive': $msg = "Your order #$oid is out for delivery."; break;
                            case 'Completed': $msg = "Your order #$oid has been completed. Thank you!"; break;
                            case 'Cancelled': $msg = "Your order #$oid has been cancelled."; break;
                            default: $msg = "Your order #$oid status updated to $status."; break;
                        }
                        $new_notifications[] = $msg;
                    }
                }
            }
        } else {
            $_SESSION['known_statuses'] = $current_statuses;
        }
        
        $_SESSION['known_statuses'] = $current_statuses;
    }
    
    // Ibalik ang update as JSON file
    echo json_encode([
        'count' => $active_orders_count,
        'cart_count' => $cart_count,
        'chat_count' => $unread_chat_count,
        'notifications' => $new_notifications
    ]);
    exit();
}

// =========================================================================
// 2. NORMAL PAGE LOAD LOGIC - Setup ng Variables para sa Unang Display
// =========================================================================
if (isset($_SESSION['user_id'])) {
    $nav_uid = $_SESSION['user_id'];
    
    if (!isset($_SESSION['known_statuses'])) {
        $current_statuses = [];
        $stat_res = $conn->query("SELECT order_id, status FROM orders WHERE user_id = $nav_uid");
        if ($stat_res) {
            while ($row = $stat_res->fetch_assoc()) {
                $current_statuses[$row['order_id']] = $row['status'];
            }
        }
        $_SESSION['known_statuses'] = $current_statuses;
    }
}
?>

<style>
    /* TOAST NOTIFICATION CSS */
    #dynamic-toast-wrapper { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
    .status-toast { background: white; border-left: 5px solid #2ed573; padding: 15px 25px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 15px; animation: slideDown 0.5s ease forwards; transition: opacity 0.5s ease; min-width: 300px; pointer-events: auto; }
    .toast-content { font-size: 13px; font-weight: 700; color: #444; flex-grow: 1; line-height: 1.4; }
    .toast-close { cursor: pointer; font-size: 18px; color: #aaa; font-weight: bold; }
    .toast-close:hover { color: #f18973; }
    @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>

<div id="dynamic-toast-wrapper"></div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    function checkOrderUpdates() {
        fetch('notifications.php?ajax_check=1')
            .then(response => response.json())
            .then(data => {
                
                // 1. UPDATE NAV BADGE (Orders)
                let orderLink = document.getElementById('navOrderLink');
                if (orderLink) {
                    if (data.count > 0) {
                        orderLink.innerHTML = 'Orders <span class="side-menu-badge">' + data.count + '</span>';
                    } else {
                        orderLink.innerHTML = 'Orders';
                    }
                }

                // 2. UPDATE CART BADGE (Real-time kapag nag-add-to-cart)
                let cartLink = document.getElementById('navCartLink');
                if (cartLink) {
                    if (data.cart_count > 0) {
                        cartLink.innerHTML = 'Cart <span class="nav-cart-badge">' + data.cart_count + '</span>';
                    } else {
                        cartLink.innerHTML = 'Cart';
                    }
                }

                // 3. UPDATE CHAT BADGE (Floating Button Real-time)
                let chatBtn = document.getElementById('floatingChatBtn');
                if (chatBtn) {
                    if (data.chat_count > 0) {
                        chatBtn.innerHTML = '💬 <span class="floating-badge">' + data.chat_count + '</span>';
                    } else {
                        chatBtn.innerHTML = '💬';
                    }
                }

                // 4. MAGPALITAW NG POPUP KUNG MAY BAGONG UPDATE
                if (data.notifications && data.notifications.length > 0) {
                    const wrapper = document.getElementById('dynamic-toast-wrapper');
                    
                    data.notifications.forEach(msg => {
                        let toast = document.createElement('div');
                        toast.className = 'status-toast';
                        toast.innerHTML = `
                            <div class="toast-content">${msg}</div>
                            <div class="toast-close" onclick="this.parentElement.style.display='none'">×</div>
                        `;
                        wrapper.appendChild(toast);

                        setTimeout(() => {
                            toast.style.opacity = '0';
                            setTimeout(() => toast.remove(), 500);
                        }, 6000);
                    });
                }
            })
            .catch(err => {});
    }

    // Patakbuhin ang status checker bawat 3 segundo
    setInterval(checkOrderUpdates, 3000);
});
</script>