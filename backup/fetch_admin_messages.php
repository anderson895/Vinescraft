<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['admin_logged_in'])) exit;

$target_user = intval($_GET['user_id']);
$history = $conn->query("SELECT * FROM messages WHERE user_id = $target_user ORDER BY created_at ASC");
$user_info = $conn->query("SELECT name FROM users WHERE user_id = $target_user")->fetch_assoc();

if ($history->num_rows > 0) {
    while($row = $history->fetch_assoc()): ?>
        <div class="msg <?php echo ($row['sender_type'] == 'user') ? 'user-msg' : 'admin-msg'; ?>">
            <strong><?php echo ($row['sender_type'] == 'user') ? htmlspecialchars($user_info['name']) : 'You'; ?></strong><br>
            <?php echo htmlspecialchars($row['message_text']); ?>
            <span class="msg-meta"><?php echo date('M d, h:i A', strtotime($row['created_at'])); ?></span>
        </div>
    <?php endwhile;
} else {
    echo '<div style="text-align: center; color: #ccc; margin-top: 50px; font-size: 13px;">No messages yet.</div>';
}
?>