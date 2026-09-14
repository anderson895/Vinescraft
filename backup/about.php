<?php
session_start();
require_once 'db_connect.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Story | Vinescraft</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');

        :root {
            --coral: #f18973;
            --peach: #fce0d8;
            --text: #444;
            /* Updated to a soft light pink */
            --bg: #fdf2f2; 
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--bg);
            margin: 0;
            color: var(--text);
            line-height: 1.8;
        }

        /* NAVIGATION BAR */
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
            font-size: 11px; 
            font-weight: 900; 
            text-transform: uppercase; 
            margin-left: 20px; 
            letter-spacing: 1px;
        }

        /* BANNER SECTION */
        .banner {
            width: 100%;
            height: 450px;
            background: url('img/team.jpg') no-repeat; 
            background-size: cover;
            background-position: center;
            position: relative;
        }

        /* STORY CARD CONTAINER */
        .story-container {
            max-width: 800px;
            margin: -100px auto 100px; 
            padding: 0 20px;
            position: relative;
        }

        .story-card {
            background: white; /* Card stays white for clear readability */
            padding: 60px 50px;
            border-radius: 30px;
            box-shadow: 0 20px 50px rgba(241, 137, 115, 0.15);
            border: 1px solid var(--peach);
            text-align: center;
        }

        /* HEADINGS */
        .story-card h2 {
            font-size: 50px;
            color: var(--coral);
            margin-bottom: 40px;
            font-weight: 400;
            font-family: 'Brush Script MT', cursive; 
        }

        .story-card p {
            font-size: 15px;
            margin-bottom: 25px;
            color: #666;
            text-align: justify;
        }

        /* QUOTE STYLE */
        .quote {
            font-weight: 700;
            color: var(--coral);
            font-size: 18px;
            margin: 40px 0;
            display: block;
            font-style: italic;
        }

        /* MISSION SECTION */
        .mission-highlight {
            border-top: 1px solid var(--peach);
            margin-top: 40px;
            padding-top: 40px;
        }

        footer {
            padding: 40px;
            text-align: center;
            background: var(--bg); /* Footer matches the new pink background */
            border-top: 1px solid var(--peach);
            color: #bbb;
            font-size: 12px;
        }
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

<!-- TOP BANNER -->
<section class="banner"></section>

<div class="story-container">
    <div class="story-card">
        <h2>Our Story</h2>

        <p>
            Vinescraft began with a fascination for the delicate balance between nature and permanence. 
            In a world where fleeting beauty is often the standard, we sought a way to capture the 
            elegance of a blooming bouquet and preserve it indefinitely. This vision led to the 
            birth of <strong>Gazette in Vines Flower and Craft Shop</strong>.
        </p>

        <span class="quote">"Every gift should be a lasting memory, not just a temporary gesture."</span>

        <p>
            Our specialty lies in the meticulous creation of fuzzy wire bouquets. Operating from the 
            heart of Las Piñas, we transform simple materials into intricate works of art. Every petal 
            is shaped with intention, and every arrangement is wrapped with the timeless grace of 
            French aesthetics.
        </p>

        <div class="mission-highlight">
            <p>
                What started as a small, local initiative has evolved into a diverse craft house. 
                Beyond our signature everlastings, we bring the same level of detail to customized 
                apparel and personalized treasures. At Vinescraft, our mission remains unchanged: 
                to deliver handcrafted excellence that defies the seasons.
            </p>
        </div>
    </div>
</div>

<footer>
    <p>&copy; 2026 Gazette in Vines Flower and Craft Shop | Handcrafted in Las Piñas</p>
</footer>

</body>
</html>