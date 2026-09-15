<?php
session_start();
require_once 'db_connect.php';

// Initialize variables
$search_query = "";
$category_filter = "";
$price_filter = "";
$sql = "SELECT * FROM products WHERE 1=1"; // WHERE 1=1 para madaling magdugtong ng AND

// 1. Process Search Bar Input (Accurate Search)
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = trim($_GET['search']);
    $safe_search = $conn->real_escape_string($search_query);
    
    // Hatiin ang query by space para accurate kahit multiple words
    $keywords = explode(' ', $safe_search);
    foreach ($keywords as $word) {
        $word = trim($word);
        if (!empty($word)) {
            $sql .= " AND name LIKE '%$word%'";
        }
    }
}

// 2. Process Category Filter Input
if (isset($_GET['category']) && !empty(trim($_GET['category'])) && trim($_GET['category']) != 'All') {
    $category_filter = trim($_GET['category']);
    $safe_category = $conn->real_escape_string($category_filter);
    $sql .= " AND category = '$safe_category'";
}

// 3. Process Price Filter Input
if (isset($_GET['price']) && !empty(trim($_GET['price'])) && trim($_GET['price']) != 'All') {
    $price_filter = trim($_GET['price']);
    if ($price_filter == 'under_300') {
        $sql .= " AND price < 300";
    } elseif ($price_filter == '300_to_500') {
        $sql .= " AND price BETWEEN 300 AND 500";
    } elseif ($price_filter == 'over_500') {
        $sql .= " AND price > 500";
    }
}

$sql .= " ORDER BY product_id DESC";
$products = $conn->query($sql);

// Pagsama-samahin ang magkakaparehong uri ng item para magkakatabi sila sa page.
// Ang hindi kilalang / walang laman na category ay mapupunta sa "Others".
$CATEGORY_ORDER = ['Bouquet' => 'Bouquets', 'Shirt' => 'Shirts', 'Crafts' => 'Crafts', 'Others' => 'Other Finds'];
$buckets = array_fill_keys(array_keys($CATEGORY_ORDER), []);

if ($products) {
    while ($row = $products->fetch_assoc()) {
        $cat = trim($row['category'] ?? '');
        if (!isset($buckets[$cat])) $cat = 'Others';
        $buckets[$cat][] = $row;
    }
}

$total_found = array_sum(array_map('count', $buckets));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop All | Vinescraft</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');

        :root {
            --coral: #f18973;
            --peach: #fce0d8;
            --text-dark: #444;
            --grey-light: #f9f9f9;
        }

        body { font-family: 'Montserrat', sans-serif; background-color: #fff; margin: 0; color: var(--text-dark); }
        
        /* NAV NOTIFICATION BADGE */
        .nav-badge {
            background: #ff4757;
            color: white;
            padding: 2px 6px;
            border-radius: 50px;
            font-size: 8px;
            font-weight: 900;
            margin-left: 3px;
            vertical-align: super;
            box-shadow: 0 0 5px rgba(255, 71, 87, 0.5);
        }

        /* NAVIGATION STYLES */
        .top-nav { 
            background: var(--coral); 
            padding: 15px 5%; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            color: white; 
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .top-nav a { 
            color: white; 
            text-decoration: none; 
            font-size: 10px; 
            font-weight: 900; 
            text-transform: uppercase; 
            margin-left: 15px; 
            letter-spacing: 1px;
            transition: 0.3s;
        }
        .top-nav a:hover { opacity: 0.8; }

        /* Back Link Row */
        .back-container { padding: 20px 5% 0; max-width: 1200px; margin: 0 auto; }
        .back-link { text-decoration: none; color: var(--coral); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }

        /* Page Title */
        .page-header { text-align: center; margin: 40px 0 30px; }
        .page-header h1 { font-size: 48px; font-weight: 900; color: var(--coral); margin: 0; }
        .page-header p { font-size: 13px; color: #888; letter-spacing: 1px; margin-top: 10px; }

        /* Search Bar Styles */
        .search-container {
            max-width: 600px;
            margin: 0 auto 50px;
            padding: 0 20px;
        }
        .search-form {
            display: flex;
            gap: 10px;
            position: relative;
        }
        .search-form input {
            flex-grow: 1;
            padding: 18px 25px;
            border: 2px solid var(--peach);
            border-radius: 50px;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            outline: none;
            background: #fffcfc;
            color: var(--text-dark);
            transition: 0.3s;
        }
        .search-form input:focus {
            border-color: var(--coral);
            background: white;
            box-shadow: 0 10px 25px rgba(241, 137, 115, 0.1);
        }
        .search-form button {
            background: var(--coral);
            color: white;
            border: none;
            padding: 0 35px;
            border-radius: 50px;
            font-weight: 900;
            font-size: 12px;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.3s;
            font-family: inherit;
            box-shadow: 0 8px 20px rgba(241, 137, 115, 0.2);
        }
        .search-form button:hover {
            background: #e07661;
            transform: translateY(-2px);
        }
        .clear-search {
            display: block;
            text-align: center;
            margin-top: 15px;
            font-size: 11px;
            font-weight: 900;
            color: #bbb;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s;
        }
        .clear-search:hover {
            color: #ff4757;
        }

        /* Product Grid */
        .shop-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 40px; 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 0 20px 80px; 
        }

        /* Category Sections - magkakatabi ang magkakaparehong uri ng item */
        .cat-section { max-width: 1200px; margin: 0 auto 20px; }
        .cat-head {
            display: flex;
            align-items: baseline;
            gap: 15px;
            padding: 0 20px 12px;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--peach);
        }
        .cat-head h2 { font-size: 28px; font-weight: 900; color: var(--coral); margin: 0; text-transform: lowercase; }
        .cat-count { font-size: 11px; font-weight: 700; color: #bbb; text-transform: uppercase; letter-spacing: 1px; }
        .cat-all {
            margin-left: auto;
            font-size: 11px;
            font-weight: 900;
            color: var(--coral);
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s;
        }
        .cat-all:hover { opacity: 0.65; }

        @media (max-width: 600px) {
            .cat-head { flex-wrap: wrap; gap: 8px; }
            .cat-head h2 { font-size: 22px; }
            .cat-all { margin-left: 0; width: 100%; }
        }

        .product-card { text-decoration: none; color: inherit; transition: 0.4s; }
        
        .image-container { 
            position: relative; 
            overflow: hidden; 
            background: var(--grey-light); 
            border-radius: 4px; 
            height: 380px; 
        }
        
        .product-card img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
            transition: 0.5s ease; 
        }

        .product-card:hover img { transform: scale(1.05); }
        
        .product-info { padding: 20px 0; text-align: left; }
        .product-info h3 { font-size: 16px; font-weight: 700; color: var(--text-dark); margin: 0 0 5px 0; text-transform: uppercase; letter-spacing: 1px; }
        .product-info .price { font-size: 15px; color: var(--coral); font-weight: 700; }
        .product-info .stock { font-size: 11px; color: #aaa; margin-top: 10px; text-transform: uppercase; }
        .product-info .stock.out { color: #c62828; font-weight: 700; }

        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: var(--grey-light);
            border-radius: 20px;
            border: 2px dashed var(--peach);
        }
        .no-results h2 { color: var(--coral); font-size: 24px; font-weight: 900; margin-bottom: 10px; }
        .no-results p { color: #888; font-size: 14px; }

        footer { padding: 40px; text-align: center; border-top: 1px solid var(--peach); color: #888; font-size: 11px; margin-top: 50px; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

<?php include 'notifications.php'; ?>
<?php include 'navbar.php'; ?>

<div class="back-container">
    <a href="index.php" class="back-link">← Return Home</a>
</div>

<div class="page-header">
    <h1>Shop All</h1>
    <p>CURATED FLORALS & CRAFTS FOR YOUR SOUL</p>
</div>

<!-- Search Bar Section -->
<div class="search-container">
    <form method="GET" action="shop.php" class="search-form">
        <!-- Ipapasa ang current category at price kapag nag-search -->
        <?php if(!empty($category_filter)): ?>
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
        <?php endif; ?>
        <?php if(!empty($price_filter)): ?>
            <input type="hidden" name="price" value="<?php echo htmlspecialchars($price_filter); ?>">
        <?php endif; ?>
        
        <input type="text" name="search" placeholder="Search for bouquets, shirts, colors..." value="<?php echo htmlspecialchars($search_query); ?>">
        <button type="submit">Search</button>
    </form>

    <!-- Clear All Filters kung may naka-apply -->
    <?php if (!empty($search_query) || !empty($category_filter) || !empty($price_filter)): ?>
        <a href="shop.php" class="clear-search">Clear All Filters ✖</a>
    <?php endif; ?>

    <!-- FILTER CONTROLS BOX -->
    <div style="background: white; padding: 25px; border-radius: 20px; border: 1px solid var(--peach); margin-top: 25px; box-shadow: 0 10px 25px rgba(241,137,115,0.05);">
        
        <!-- Category Filters -->
        <div style="margin-bottom: 20px;">
            <span style="font-size: 10px; font-weight: 900; color: #bbb; text-transform: uppercase; display: block; margin-bottom: 12px; letter-spacing: 1px;">Categories</span>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php 
                    $search_param = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
                    $p_param = !empty($price_filter) ? '&price=' . urlencode($price_filter) : '';
                    $cats = ['All', 'Bouquet', 'Shirt', 'Crafts', 'Others'];
                    
                    foreach ($cats as $cat) {
                        $is_active = ($category_filter == $cat) || (empty($category_filter) && $cat == 'All');
                        $bg_color = $is_active ? 'var(--coral)' : 'transparent';
                        $text_color = $is_active ? 'white' : 'var(--coral)';
                        
                        echo "<a href='shop.php?category=$cat$search_param$p_param' style='padding: 6px 16px; border-radius: 50px; text-decoration: none; font-size: 11px; font-weight: 900; border: 2px solid var(--peach); color: $text_color; background: $bg_color; text-transform: uppercase; transition: 0.3s;'>$cat</a>";
                    }
                ?>
            </div>
        </div>

        <!-- Price Range Filters -->
        <div>
            <span style="font-size: 10px; font-weight: 900; color: #bbb; text-transform: uppercase; display: block; margin-bottom: 12px; letter-spacing: 1px;">Price Range</span>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php 
                    $c_param = !empty($category_filter) ? '&category=' . urlencode($category_filter) : '';
                    $prices = [
                        'All' => 'Any Price', 
                        'under_300' => 'Under ₱300', 
                        '300_to_500' => '₱300 - ₱500', 
                        'over_500' => 'Over ₱500'
                    ];
                    
                    foreach ($prices as $val => $label) {
                        $is_active = ($price_filter == $val) || (empty($price_filter) && $val == 'All');
                        $bg_color = $is_active ? 'var(--coral)' : 'transparent';
                        $text_color = $is_active ? 'white' : 'var(--coral)';
                        
                        echo "<a href='shop.php?price=$val$search_param$c_param' style='padding: 6px 16px; border-radius: 50px; text-decoration: none; font-size: 11px; font-weight: 900; border: 2px solid var(--peach); color: $text_color; background: $bg_color; text-transform: uppercase; transition: 0.3s;'>$label</a>";
                    }
                ?>
            </div>
        </div>

    </div>
</div>

<?php if ($total_found > 0): ?>
    <?php foreach ($CATEGORY_ORDER as $cat_key => $cat_label): ?>
        <?php if (empty($buckets[$cat_key])) continue; ?>
        <section class="cat-section">
            <div class="cat-head">
                <h2><?php echo $cat_label; ?></h2>
                <span class="cat-count"><?php echo count($buckets[$cat_key]); ?> item<?php echo count($buckets[$cat_key]) > 1 ? 's' : ''; ?></span>
                <?php if (empty($category_filter)): ?>
                    <a class="cat-all" href="shop.php?category=<?php echo urlencode($cat_key); ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($price_filter) ? '&price=' . urlencode($price_filter) : ''; ?>">View all &rarr;</a>
                <?php endif; ?>
            </div>

            <div class="shop-grid">
                <?php foreach ($buckets[$cat_key] as $row): ?>
                    <a href="product_view.php?id=<?php echo $row['product_id']; ?>" class="product-card">
                        <div class="image-container">
                            <img src="uploads/<?php echo $row['image']; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                        </div>
                        <div class="product-info">
                            <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                            <div class="price">₱<?php echo number_format($row['price'], 2); ?></div>
                            <?php if ($row['stock'] > 0): ?>
                                <div class="stock">In Stock: <?php echo $row['stock']; ?></div>
                            <?php else: ?>
                                <div class="stock out">Out of Stock</div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php else: ?>
    <div class="shop-grid">
        <div class="no-results">
            <h2>No blooms found!</h2>
            <?php if (!empty($search_query)): ?>
                <p>We couldn't find anything matching "<strong><?php echo htmlspecialchars($search_query); ?></strong>". Try searching for a different flower, color, or style.</p>
            <?php else: ?>
                <p>No items match this filter yet. Try a different category or price range.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div style="background: var(--coral); color: white; padding: 60px 10%; text-align: center; margin-top: 40px;">
    <p style="font-size: 20px; font-weight: 700; margin: 0;">"The gesture of giving flowers is a symbol of love, respect, and admiration."</p>
</div>

<footer>
    <p>&copy; 2026 Gazette in Vines Flower and Craft Shop | Las Piñas</p>
</footer>

</body>
</html>