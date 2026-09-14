<?php
// Low stock counts para sa mga badge. Nasa scope na ang $conn sa lahat ng admin page
// na nag-include nito, pero naka-guard pa rin para hindi bumagsak ang sidebar.
$__low_counts = ['products_low' => 0, 'products_out' => 0, 'materials_low' => 0, 'materials_out' => 0];
if (isset($conn) && function_exists('get_low_stock_counts')) {
    try { $__low_counts = get_low_stock_counts($conn); } catch (Throwable $e) {}
}
$__mat_alerts  = $__low_counts['materials_low'] + $__low_counts['materials_out'];
$__prod_alerts = $__low_counts['products_low'] + $__low_counts['products_out'];
?>
<div class="sidebar">
    <h1>Chub's Handicrafts</h1>
    <div class="nav-links">
        <a href="admin_dashboard.php">Dashboard Home</a>
        <a href="admin_orders.php">Manage Orders</a>
        <a href="admin_store_settings.php">Store Settings</a>
        <a href="admin_products.php">
            Manage Products
            <?php if ($__prod_alerts > 0) { ?>
                <span class="msg-badge" title="<?= $__prod_alerts ?> product(s) low or out of stock"><?= $__prod_alerts ?></span>
            <?php } ?>
        </a>
        <a href="admin_flowers.php">Custom Assets</a>
        <a href="admin_materials.php">
            Manage Materials
            <?php if ($__mat_alerts > 0) { ?>
                <span class="msg-badge" title="<?= $__mat_alerts ?> material(s) low or out of stock"><?= $__mat_alerts ?></span>
            <?php } ?>
        </a>
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