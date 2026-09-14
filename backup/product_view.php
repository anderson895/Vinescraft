<?php
session_start();
require_once 'db_connect.php';

if (!isset($_GET['id'])) {
    header("Location: shop.php");
    exit();
}

$p_id = intval($_GET['id']);

// SUBMIT REVIEW LOGIC
if (isset($_POST['submit_review'])) {
    if (!isset($_SESSION['user_logged_in'])) {
        echo "<script>alert('Please login first to leave a review.'); window.location.href='login.php';</script>";
        exit();
    }
    $user_id = $_SESSION['user_id'];
    $rating = intval($_POST['rating']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $p_id, $user_id, $rating, $comment);
    
    if ($stmt->execute()) {
        echo "<script>alert('Review submitted! Thank you.'); window.location.href='product_view.php?id=$p_id';</script>";
        exit();
    }
}

// BUY NOW LOGIC (Modified to go to cart first)
if (isset($_POST['buy_now'])) {
    if (!isset($_SESSION['user_logged_in'])) {
        echo "<script>alert('Please login first to purchase.'); window.location.href='login.php';</script>";
        exit();
    }
    
    $p_id = intval($_POST['p_id']);
    $product_name = $_POST['p_name'];
    $product_price = floatval($_POST['p_price']);
    $qty = intval($_POST['qty']);
    $p_image = $_POST['p_image'];

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Idagdag sa cart at i-redirect sa cart.php
    if (isset($_SESSION['cart'][$p_id])) {
        $current_qty = is_array($_SESSION['cart'][$p_id]) ? $_SESSION['cart'][$p_id]['qty'] : $_SESSION['cart'][$p_id];
        $_SESSION['cart'][$p_id] = [
            'name' => $product_name, 'price' => $product_price, 'qty' => $current_qty + $qty, 'image' => $p_image
        ];
    } else {
        $_SESSION['cart'][$p_id] = [
            'name' => $product_name, 'price' => $product_price, 'qty' => $qty, 'image' => $p_image
        ];
    }
    
    header("Location: cart.php");
    exit();
}

// FETCH PRODUCT DATA
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->bind_param("i", $p_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) { die("Product not found."); }

// CALCULATE RATINGS & SOLD
$sold_query = $conn->query("SELECT SUM(quantity) as total_sold FROM order_details WHERE product_id = $p_id");
$amount_sold = $sold_query->fetch_assoc()['total_sold'] ?? 0;

$avg_rating_query = $conn->query("SELECT AVG(rating) as average, COUNT(*) as count FROM reviews WHERE product_id = $p_id");
$rating_data = $avg_rating_query->fetch_assoc();
$avg_rating = round($rating_data['average'] ?? 0, 1);
$total_reviews = $rating_data['count'];

// FETCH REVIEWS
$reviews_query = $conn->query("SELECT r.*, u.name FROM reviews r JOIN users u ON r.user_id = u.user_id WHERE r.product_id = $p_id ORDER BY r.created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> | Vinescraft</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');

        :root {
            --coral: #f18973;
            --peach: #fce0d8;
            --text-dark: #444;
            --bg-soft: #fdf2f2;
        }

        body { font-family: 'Montserrat', sans-serif; background-color: #fff; margin: 0; color: var(--text-dark); line-height: 1.6; }
        .top-nav { background: var(--coral); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; color: white; position: sticky; top: 0; z-index: 1000; }
        .top-nav a { color: white; text-decoration: none; font-size: 11px; font-weight: 900; text-transform: uppercase; margin-left: 20px; letter-spacing: 1px; }

        .product-container { max-width: 1000px; margin: 50px auto; padding: 0 20px; display: flex; gap: 60px; flex-wrap: wrap; }
        .image-wrapper { flex: 1; min-width: 350px; }
        .image-wrapper img { width: 100%; border-radius: 30px; border: 1px solid var(--peach); box-shadow: 0 15px 35px rgba(241, 137, 115, 0.1); }

        .content-wrapper { flex: 1.2; min-width: 350px; }
        .content-wrapper h1 { font-size: 42px; font-weight: 900; color: var(--coral); margin: 0 0 5px 0; text-transform: lowercase; }
        .content-wrapper h1::after { content: '.'; }
        
        .rating-stars { color: #ffca28; font-size: 18px; margin-bottom: 10px; display: flex; align-items: center; gap: 5px; }
        .rating-text { color: #bbb; font-size: 12px; font-weight: 700; text-transform: uppercase; }

        .price { font-size: 28px; color: var(--text-dark); font-weight: 900; margin: 20px 0; }
        .desc { color: #666; font-size: 14px; margin-bottom: 30px; text-align: justify; }

        .action-area { display: flex; align-items: center; gap: 20px; margin-top: 30px; }
        .qty-circle { width: 55px; height: 55px; border-radius: 50%; border: 2px solid var(--peach); text-align: center; font-weight: 900; color: var(--coral); outline: none; font-family: inherit; }
        
        .btn-round {
            width: 110px; height: 110px; border-radius: 50%; background: var(--coral); color: white; border: none; cursor: pointer;
            font-weight: 900; font-size: 11px; text-transform: uppercase; display: flex; align-items: center; justify-content: center;
            transition: 0.3s; box-shadow: 0 8px 20px rgba(241, 137, 115, 0.3); line-height: 1.2;
        }
        .btn-round:hover { transform: scale(1.1); background: #e07661; }
        .btn-cart-text { color: var(--coral); text-decoration: none; font-weight: 900; cursor: pointer; border: none; background: none; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }

        .reviews-section { max-width: 1000px; margin: 60px auto; padding: 0 20px; }
        .reviews-section h2 { font-size: 28px; font-weight: 900; color: var(--coral); text-transform: lowercase; margin-bottom: 30px; }
        .reviews-section h2::after { content: '.'; }

        .review-card { background: var(--bg-soft); padding: 25px; border-radius: 20px; margin-bottom: 15px; border: 1px solid var(--peach); }
        .review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .reviewer-name { font-weight: 900; font-size: 14px; color: var(--coral); }
        .review-date { font-size: 10px; color: #bbb; text-transform: uppercase; }

        .review-form { background: white; border: 2px dashed var(--peach); padding: 30px; border-radius: 30px; margin-top: 40px; }
        .review-form select, .review-form textarea {
            width: 100%; padding: 15px; border: 1px solid var(--peach); border-radius: 15px; font-family: inherit; margin-bottom: 15px; outline: none; box-sizing: border-box;
        }
        .btn-submit-review { background: var(--coral); color: white; border: none; padding: 12px 30px; border-radius: 50px; font-weight: 900; text-transform: uppercase; font-size: 11px; cursor: pointer; }

        .quote-box { background: var(--coral); color: white; padding: 30px; text-align: center; font-size: 14px; border-radius: 20px; margin: 50px 0; font-weight: 700; font-style: italic; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

<nav class="top-nav">
    <div style="font-weight: 900; font-size: 18px; text-transform: lowercase;">vinescraft.</div>
    <div>
        <a href="index.php">Home</a>
        <a href="shop.php">Shop</a>
        <a href="cart.php">Cart</a>
        <?php if(isset($_SESSION['user_logged_in'])): ?>
            <a href="profile.php">Account</a>
        <?php else: ?>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </div>
</nav>

<div class="product-container">
    <div class="image-wrapper">
        <img src="uploads/<?= htmlspecialchars($product['image']) ?>" alt="Product">
    </div>

    <div class="content-wrapper">
        <p style="color: var(--coral); font-weight: 900; letter-spacing: 2px; font-size: 10px; margin-bottom: 5px; text-transform: uppercase;">Exclusive Collection</p>
        <h1><?= htmlspecialchars($product['name']) ?></h1>
        
        <div class="rating-stars">
            <?php for($i=1; $i<=5; $i++): ?>
                <span style="color: <?= $i <= $avg_rating ? '#ffca28' : '#eee' ?>;">★</span>
            <?php endfor; ?>
            <span class="rating-text">&nbsp; <?= $avg_rating ?> (<?= $total_reviews ?> reviews)</span>
        </div>

        <div class="price">₱<?= number_format($product['price'], 2) ?></div>
        
        <p class="desc"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
        
        <div style="font-size: 11px; font-weight: 700; color: #bbb; text-transform: uppercase; letter-spacing: 1px;">
            <span style="color: var(--coral);"><?= $amount_sold ?></span> items delivered to happy customers.
        </div>

        <form action="" method="POST" class="action-area">
            <input type="hidden" name="p_id" value="<?= $p_id ?>">
            <input type="hidden" name="p_name" value="<?= htmlspecialchars($product['name']) ?>">
            <input type="hidden" name="p_price" value="<?= $product['price'] ?>">
            <input type="hidden" name="p_image" value="uploads/<?= htmlspecialchars($product['image']) ?>">
            
            <input type="number" name="qty" value="1" min="1" max="<?= $product['stock'] ?>" class="qty-circle">
            
            <button type="submit" name="buy_now" class="btn-round">Order<br>Now</button>
            <button type="submit" formaction="cart_handler.php" name="add_to_cart" class="btn-cart-text">Add to Cart</button>
        </form>
    </div>
</div>

<div class="reviews-section">
    <div class="quote-box">
        "Flowers are the music of the ground. From earth's lips spoken without sound."
    </div>

    <h2>customer reviews.</h2>

    <?php if($reviews_query->num_rows > 0): ?>
        <?php while($rev = $reviews_query->fetch_assoc()): ?>
            <div class="review-card">
                <div class="review-header">
                    <span class="reviewer-name"><?= htmlspecialchars($rev['name']) ?></span>
                    <span class="review-date"><?= date('M d, Y', strtotime($rev['created_at'])) ?></span>
                </div>
                <div style="color: #ffca28; margin-bottom: 10px;">
                    <?php for($i=1; $i<=5; $i++) echo ($i <= $rev['rating'] ? '★' : '☆'); ?>
                </div>
                <p class="review-comment"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                
                <!-- ADMIN REPLY DISPLAY SECTION (Bago ito) -->
                <?php if (!empty($rev['admin_reply'])): ?>
                    <div style="background: #fffafb; padding: 15px; border-radius: 12px; margin-top: 15px; font-size: 13px; border-left: 4px solid var(--coral); border-top: 1px solid var(--peach); border-right: 1px solid var(--peach); border-bottom: 1px solid var(--peach);">
                        <strong style="color: var(--coral); font-size: 10px; text-transform: uppercase; display: block; margin-bottom: 5px;">Response from Vinescraft:</strong>
                        <p style="margin: 0; color: #555; font-style: italic;"><?= nl2br(htmlspecialchars($rev['admin_reply'])) ?></p>
                    </div>
                <?php endif; ?>
                <!-- END ADMIN REPLY -->
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="color: #bbb; font-style: italic;">No reviews yet. Be the first to share your thoughts!</p>
    <?php endif; ?>

    <?php if(isset($_SESSION['user_logged_in'])): ?>
        <div class="review-form">
            <h3 style="color: var(--coral); font-weight: 900; text-transform: lowercase; margin-top: 0;">leave a review.</h3>
            <form method="POST">
                <label style="font-size: 11px; font-weight: 900; color: #bbb; text-transform: uppercase;">Your Rating</label>
                <select name="rating" required>
                    <option value="5">5 - Excellent</option>
                    <option value="4">4 - Very Good</option>
                    <option value="3">3 - Good</option>
                    <option value="2">2 - Fair</option>
                    <option value="1">1 - Poor</option>
                </select>
                <label style="font-size: 11px; font-weight: 900; color: #bbb; text-transform: uppercase;">Your Feedback</label>
                <textarea name="comment" rows="4" placeholder="How was the quality of our handcrafted blooms?" required></textarea>
                <button type="submit" name="submit_review" class="btn-submit-review">Submit Feedback</button>
            </form>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; border: 1px dashed var(--peach); border-radius: 20px; margin-top: 30px;">
            <p style="font-size: 13px; color: #999;">Want to leave a review? <a href="login.php" style="color: var(--coral); font-weight: 900; text-decoration: none;">Login to your account.</a></p>
        </div>
    <?php endif; ?>
</div>

<footer style="padding: 60px; text-align: center; color: #bbb; font-size: 11px; text-transform: uppercase; letter-spacing: 2px;">
    &copy; 2026 Gazette in Vines Flower and Craft Shop
</footer>

</body>
</html>