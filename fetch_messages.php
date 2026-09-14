<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) exit;

$u_id = $_SESSION['user_id'];

// --- BAGO: Set messages from admin as READ kapag nai-load na sa screen ng user ---
$conn->query("UPDATE messages SET is_read = 1 WHERE user_id = $u_id AND sender_type = 'admin' AND is_read = 0");

$chat_history = $conn->query("SELECT * FROM messages WHERE user_id = $u_id ORDER BY created_at ASC");

if($chat_history->num_rows > 0) {
    while($row = $chat_history->fetch_assoc()): ?>
        <div class="msg-bubble <?php echo ($row['sender_type'] == 'user') ? 'user-msg' : 'admin-msg'; ?>">
            <strong><?php echo ($row['sender_type'] == 'user') ? 'You' : 'Florist'; ?></strong><br>
            <?php echo htmlspecialchars($row['message_text']); ?>
            
            <?php if (!empty($row['image_path']) && $row['image_path'] !== 'NULL'): ?>
                <br><img src="uploads/chat_images/<?php echo htmlspecialchars($row['image_path']); ?>" class="chat-img" style="max-width: 100%; border-radius: 10px; margin-top: 5px; border: 1px solid rgba(0,0,0,0.1);">
            <?php endif; ?>
            
            <span class="msg-time"><?php echo date('h:i A', strtotime($row['created_at'])); ?></span>
        </div>
    <?php endwhile;
} else {
    echo '<div style="text-align: center; color: #aaa; margin-top: 50px; font-size: 12px;">Start a conversation with our florist.</div>';
}
?>