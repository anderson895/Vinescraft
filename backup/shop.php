<?php
session_start();
require_once 'db_connect.php';

// Kukunin ang lahat ng products mula sa database
$products = $conn->query("SELECT * FROM products ORDER BY product_id DESC");
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

        /* Page Title (Inspired by bloom.jpg lowercase style) */
        .page-header { text-align: center; margin: 40px 0 60px; }
        .page-header h1 { font-size: 48px; font-weight: 900; color: var(--coral); margin: 0; text-transform: lowercase; }
        .page-header h1::after { content: '.'; }
        .page-header p { font-size: 13px; color: #888; letter-spacing: 1px; margin-top: 10px; }

        /* Product Grid */
        .shop-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 40px; 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 0 20px 80px; 
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

        /* Circular Action Button for Sections */
        .btn-circle {
            width: 80px; height: 80px; border-radius: 50%; background: var(--coral); color: white;
            display: flex; align-items: center; justify-content: center; text-align: center;
            font-size: 10px; font-weight: bold; text-transform: uppercase; margin: 0 auto 40px;
            text-decoration: none; transition: 0.3s; line-height: 1.2;
        }
        .btn-circle:hover { transform: scale(1.1); background: #e07661; }

        footer { padding: 40px; text-align: center; border-top: 1px solid var(--peach); color: #888; font-size: 11px; margin-top: 50px; }
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

<div class="back-container">
    <a href="index.php" class="back-link">← Return Home</a>
</div>

<!-- PAGE HEADER -->
<div class="page-header">
    <h1>shop all.</h1>
    <p>CURATED FLORALS & CRAFTS FOR YOUR SOUL</p>
</div>

<!-- SHOP GRID -->
<div class="shop-grid">
    <?php while($row = $products->fetch_assoc()): ?>
        <a href="product_view.php?id=<?php echo $row['product_id']; ?>" class="product-card">
            <div class="image-container">
                <img src="uploads/<?php echo $row['image']; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
            </div>
            <div class="product-info">
                <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                <div class="price">₱<?php echo number_format($row['price'], 2); ?></div>
                <div class="stock">In Stock: <?php echo $row['stock']; ?></div>
            </div>
        </a>
    <?php endwhile; ?>
</div>

<!-- SYMBOLISM SECTION (Optional, based on bloom.jpg style) -->
<div style="background: var(--coral); color: white; padding: 60px 10%; text-align: center; margin-top: 40px;">
    <p style="font-size: 20px; font-weight: 700; margin: 0;">"The gesture of giving flowers is a symbol of love, respect, and admiration."</p>
</div>

<footer>
    <p>&copy; 2026 Gazette in Vines Flower and Craft Shop | Las Piñas</p>
</footer>

</body>
</html>