<div class="sidebar">
    <h1>Chub's Handicrafts</h1>
    <div class="nav-links">
        <a href="admin_dashboard.php">Dashboard Home</a>
        <a href="admin_orders.php">Manage Orders</a>
        <a href="admin_products.php">Manage Products</a>
        <a href="admin_flowers.php">Custom Assets</a>
        <a href="admin_materials.php">Manage Materials</a>
        <a href="admin_reviews.php">Reviews</a>
        <a href="admin_messages.php">
            Messages
            <?php if (!empty($unread_count) && $unread_count > 0) { ?>
                <span class="msg-badge"><?= $unread_count ?></span>
            <?php } ?>
        </a>
        <a href="admin_history.php">Login History</a>
        <a href="admin_settings.php">Account Settings</a>
        <a href="admin_logout.php" class="logout">Logout</a>
    </div>
</div>

<script>
    let pagePath = window.location.pathname
    let sidebarLinks = document.querySelectorAll('.nav-links a')
    sidebarLinks.forEach(link => {
        if (pagePath.includes(link.getAttribute('href'))) {
            link.classList.add('active')
        }
    })
</script>