<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) exit;

$u_id = $_SESSION['user_id'];
$chat_history = $conn->query("SELECT * FROM messages WHERE user_id = $u_id ORDER BY created_at ASC");

if($chat_history->num_rows > 0) {
    while($row = $chat_history->fetch_assoc()): ?>
        <div class="msg-bubble <?php echo ($row['sender_type'] == 'user') ? 'user-msg' : 'admin-msg'; ?>">
            <strong><?php echo ($row['sender_type'] == 'user') ? 'You' : 'Florist'; ?></strong><br>
            <?php echo htmlspecialchars($row['message_text']); ?>
            <span class="msg-time"><?php echo date('h:i A', strtotime($row['created_at'])); ?></span>
        </div>
    <?php endwhile;
} else {
    echo '<div style="text-align: center; color: #aaa; margin-top: 50px; font-size: 12px;">Start a conversation with our florist.</div>';
}
?>