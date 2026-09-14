<?php
session_start();
require_once 'db_connect.php';

// FETCH: Kunin yung total na unread para sa Sidebar Badge
$unread_total_query = $conn->query("SELECT COUNT(*) as unread FROM messages WHERE sender_type = 'user' AND is_read = 0");
$unread_count = $unread_total_query ? $unread_total_query->fetch_assoc()['unread'] : 0;

// PROTECTION: Admin session check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// 1. GET STATS (ALL TIME)
$res_rev = $conn->query("SELECT SUM(total_price) as total FROM orders WHERE status = 'Completed'");
$total_revenue = $res_rev->fetch_assoc()['total'] ?? 0;

$res_orders = $conn->query("SELECT COUNT(*) as total FROM orders");
$total_orders = $res_orders->fetch_assoc()['total'] ?? 0;

$res_prod = $conn->query("SELECT COUNT(*) as total FROM products");
$total_products = $res_prod->fetch_assoc()['total'] ?? 0;

// --- BAGO: CALCULATE PERCENTAGE CHANGE (Last 7 Days vs Previous 7 Days) ---
// 1. REVENUE COMPARISON
$rev_current_q = $conn->query("SELECT SUM(total_price) as total FROM orders WHERE status = 'Completed' AND order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$rev_current = $rev_current_q->fetch_assoc()['total'] ?? 0;

$rev_prev_q = $conn->query("SELECT SUM(total_price) as total FROM orders WHERE status = 'Completed' AND order_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND order_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$rev_prev = $rev_prev_q->fetch_assoc()['total'] ?? 0;

$rev_pct = 0;
if ($rev_prev > 0) {
    $rev_pct = (($rev_current - $rev_prev) / $rev_prev) * 100;
} else if ($rev_current > 0) {
    $rev_pct = 100; // Kung 0 ang benta last week pero may benta ngayon, 100% increase
}

// Green pag increase, Orange pag decrease
$rev_color = $rev_pct >= 0 ? '#2ed573' : '#ffa502'; 
$rev_sign = $rev_pct >= 0 ? '▲ +' : '▼ ';
$rev_text = $rev_sign . number_format($rev_pct, 1) . "% vs last week";

// 2. ORDERS COMPARISON
$ord_current_q = $conn->query("SELECT COUNT(*) as total FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$ord_current = $ord_current_q->fetch_assoc()['total'] ?? 0;

$ord_prev_q = $conn->query("SELECT COUNT(*) as total FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND order_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$ord_prev = $ord_prev_q->fetch_assoc()['total'] ?? 0;

$ord_pct = 0;
if ($ord_prev > 0) {
    $ord_pct = (($ord_current - $ord_prev) / $ord_prev) * 100;
} else if ($ord_current > 0) {
    $ord_pct = 100;
}

// Green pag increase, Orange pag decrease
$ord_color = $ord_pct >= 0 ? '#2ed573' : '#ffa502';
$ord_sign = $ord_pct >= 0 ? '▲ +' : '▼ ';
$ord_text = $ord_sign . number_format($ord_pct, 1) . "% vs last week";
// -------------------------------------------------------------------------

// Pagsamahin ang Products at Materials sa iisang Stock Alert Query
$low_stock_query = "
    SELECT name, stock, 'Product' AS type FROM products WHERE stock <= 10
    UNION ALL
    SELECT name, stock, 'Material' AS type FROM materials WHERE stock <= 10
    ORDER BY stock ASC
";
$res_low_stock = $conn->query($low_stock_query);

// --- FETCH DATA FOR SALES GRAPH (Last 7 Days) ---
$sales_dates = [];
$sales_totals = [];

// Gumawa ng array para sa last 7 days (para may 0 value ang mga araw na walang benta)
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $sales_dates[$date] = 0;
}

// Kunin ang benta na 'Completed' na sa nakalipas na 7 araw
$graph_sql = "SELECT DATE(order_date) as date, SUM(total_price) as daily_total 
              FROM orders 
              WHERE status = 'Completed' AND order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              GROUP BY DATE(order_date)";
$graph_res = $conn->query($graph_sql);

if ($graph_res) {
    while ($row = $graph_res->fetch_assoc()) {
        $date = $row['date'];
        if (array_key_exists($date, $sales_dates)) {
            $sales_dates[$date] = floatval($row['daily_total']);
        }
    }
}

// I-convert sa JSON para mabasa ng JavaScript Chart.js
$labels_json = json_encode(array_keys($sales_dates));
$data_json = json_encode(array_values($sales_dates));
// ------------------------------------------------------
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Gazette in Vines</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
        :root { --coral: #f18973; --peach: #fce0d8; --text: #444; }
        
        body { font-family: 'Montserrat', sans-serif; background: #fffafb; margin: 0; display: flex; color: var(--text); }
        
        /* BADGE CSS PARA SA SIDEBAR */
    .msg-badge {
    background: #ff4757;
    color: white;
    padding: 2px 6px;
    border-radius: 50px;
    font-size: 10px;
    font-weight: 900;
    margin-left: 5px;
    vertical-align: top;
}

        /* SIDEBAR STYLE */
        .sidebar { width: 260px; background: white; height: 100vh; border-right: 1px solid var(--peach); padding: 30px 20px; position: fixed; }
        .sidebar h1 { color: var(--coral); font-size: 20px; font-weight: 900; text-transform: lowercase; margin-bottom: 40px; }
        
        .nav-links { display: flex; flex-direction: column; gap: 10px; }
        .nav-links a { text-decoration: none; color: #888; font-size: 13px; font-weight: 700; padding: 12px 20px; border-radius: 10px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: var(--peach); color: var(--coral); }
        .nav-links a.logout { margin-top: 20px; color: #ff4757; }

        /* MAIN CONTENT AREA */
        .main-content { margin-left: 300px; padding: 40px; width: calc(100% - 340px); }
        .page-header h2 { font-size: 32px; font-weight: 900; color: var(--coral); margin: 0 0 30px; }
        

        /* STATS CARDS */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 20px; border: 1px solid var(--peach); box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
        .stat-card h4 { margin: 0; font-size: 10px; text-transform: uppercase; color: #aaa; letter-spacing: 1px; }
        .stat-card .val { font-size: 28px; font-weight: 900; color: var(--coral); margin-top: 10px; }

        /* TABLES */
        .content-box { background: white; padding: 30px; border-radius: 20px; border: 1px solid var(--peach); margin-bottom: 30px; }
        .content-box h3 { font-size: 14px; text-transform: uppercase; color: var(--coral); margin-top: 0; margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; color: #bbb; padding: 10px; border-bottom: 1px solid var(--peach); }
        td { padding: 15px 10px; font-size: 13px; border-bottom: 1px solid #fff5f8; }
        
        .status-badge { font-size: 9px; font-weight: 900; padding: 4px 10px; border-radius: 50px; text-transform: uppercase; }
        .low-stock { font-weight: 900; }
        .type-badge { font-size: 9px; font-weight: 700; color: #888; background: #eee; padding: 3px 8px; border-radius: 8px; text-transform: uppercase; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

<?php include 'admin_sidebar.php' ?>

    <div class="main-content">
        <div class="page-header">
            <h2>Dashboard Overview</h2>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h4>Total Revenue</h4>
                <div class="val">₱<?php echo number_format($total_revenue, 2); ?></div>
                <div style="color: <?php echo $rev_color; ?>; font-size: 10px; font-weight: 700; margin-top: 5px;">
                    <?php echo $rev_text; ?>
                </div>
            </div>
            <div class="stat-card">
                <h4>Total Orders</h4>
                <div class="val"><?php echo $total_orders; ?></div>
                <div style="color: <?php echo $ord_color; ?>; font-size: 10px; font-weight: 700; margin-top: 5px;">
                    <?php echo $ord_text; ?>
                </div>
            </div>
            <div class="stat-card">
                <h4>Active Products</h4>
                <div class="val"><?php echo $total_products; ?></div>
            </div>
        </div>

        <div class="content-box">
            <h3>Sales Overview (Last 7 Days)</h3>
            <canvas id="salesChart" height="80"></canvas>
        </div>

        <div class="content-box">
            <h3 style="color: #ff4757;">Stock Alerts (10 or less)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Current Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($res_low_stock->num_rows > 0): ?>
                        <?php while($item = $res_low_stock->fetch_assoc()): ?>
                        <?php 
                            // Checker kung 0 na talaga yung stock (ginamitan ng floatval para sa materials)
                            $is_out_of_stock = floatval($item['stock']) <= 0;
                            $badge_bg = $is_out_of_stock ? '#ffebee' : '#fff2f2';
                            $badge_color = $is_out_of_stock ? '#c62828' : '#ff4757';
                            $badge_text = $is_out_of_stock ? 'Out of Stock' : 'Low Stock';
                        ?>
                        <tr>
                            <td><strong style="color: var(--coral);"><?php echo htmlspecialchars($item['name']); ?></strong></td>
                            <td><span class="type-badge"><?php echo $item['type']; ?></span></td>
                            <td class="low-stock" style="color: <?php echo $badge_color; ?>;"><?php echo $item['stock']; ?></td>
                            <td><span class="status-badge" style="background:<?php echo $badge_bg; ?>; color:<?php echo $badge_color; ?>;"><?php echo $badge_text; ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="color:#aaa; text-align:center; padding: 30px;">All products and materials have sufficient stock.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="content-box">
            <h3>Recent Transactions</h3>
            <table>
                <thead>
                    <tr><th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php
                    $recent = $conn->query("SELECT o.*, u.name FROM orders o JOIN users u ON o.user_id = u.user_id ORDER BY o.order_id DESC LIMIT 5");
                    while($r = $recent->fetch_assoc()){
                        echo "<tr>
                                <td>#{$r['order_id']}</td>
                                <td>" . htmlspecialchars($r['name']) . "</td>
                                <td style='font-weight:700;'>₱".number_format($r['total_price'], 2)."</td>
                                <td><span class='status-badge' style='background: var(--peach); color: var(--coral);'>{$r['status']}</span></td>
                                <td style='color:#aaa; font-size:11px;'>{$r['order_date']}</td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo $labels_json; ?>, // Mga petsa galing PHP
                datasets: [{
                    label: 'Daily Revenue (₱)',
                    data: <?php echo $data_json; ?>, // Benta galing PHP
                    backgroundColor: 'rgba(241, 137, 115, 0.2)', // Kulay peach/coral na malabo
                    borderColor: '#f18973', // Solid coral line
                    borderWidth: 2,
                    pointBackgroundColor: '#f18973',
                    pointRadius: 4,
                    tension: 0.3, // Medyo curve yung lines
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false // Tinago yung label sa taas para mas malinis
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₱ ' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value;
                            }
                        },
                        grid: {
                            color: '#fff5f8'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>