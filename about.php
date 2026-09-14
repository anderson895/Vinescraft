<?php
session_start();
require_once 'db_connect.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Story | Chub's Handicrafts</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');

        :root {
            --coral: #f18973;
            --peach: #fce0d8;
            --dark-peach: #f5d0c5;
            --text: #555;
            --bg: #faebeb; /* Much dimmer pinkish background */
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--bg);
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(241, 137, 115, 0.12), transparent 400px),
                radial-gradient(circle at 85% 30%, rgba(241, 137, 115, 0.15), transparent 400px);
            margin: 0;
            color: var(--text);
            line-height: 1.8;
            overflow-x: hidden;
        }

        /* BANNER SECTION */
        .banner {
            width: 100%;
            height: 45vh;
            background: linear-gradient(rgba(42, 25, 34, 0.5), rgba(157, 53, 96, 0.3)), url('img/team.jpg?v=<?php echo file_exists("img/team.jpg") ? filemtime("img/team.jpg") : time(); ?>') no-repeat; 
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        /* MAIN CONTAINER */
        .story-wrapper {
            max-width: 1000px;
            margin: -80px auto 100px; 
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        /* SECTION STYLING - NO MORE WHITE */
        .story-section {
            background: rgba(252, 224, 216, 0.35); /* Translucent peach instead of white */
            backdrop-filter: blur(15px);
            padding: 50px 60px;
            border-radius: 35px; 
            box-shadow: 0 20px 50px rgba(132, 55, 86, 0.08);
            border: 1px solid rgba(241, 137, 115, 0.2);
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 42px;
            font-weight: 900;
            color: var(--coral);
            margin: 0 0 5px 0;
            text-transform: lowercase;
            text-align: center;
            text-shadow: 0 4px 15px rgba(241, 137, 115, 0.2);
        }

        .sub-tag {
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            color: #a38290;
            letter-spacing: 2px;
            display: block;
            margin-bottom: 30px;
            text-align: center;
        }

        .story-section h2 {
            font-size: 32px;
            color: var(--coral);
            margin-top: 0;
            margin-bottom: 20px;
            font-weight: 900;
            text-transform: lowercase;
            text-align: center;
        }

        .story-section p {
            font-size: 14px;
            color: #67545d;
            margin-bottom: 20px;
            text-align: justify;
            line-height: 1.9;
        }

        /* QUOTE */
        .quote-block {
            text-align: center;
            padding: 40px;
            background: linear-gradient(135deg, var(--peach), var(--dark-peach));
            border-radius: 30px;
            margin: 40px 0;
            box-shadow: 0 15px 35px rgba(241, 137, 115, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .quote-block h3 {
            color: #c94a6d;
            font-family: 'Brush Script MT', cursive;
            font-size: 34px;
            margin: 0;
            font-weight: 400;
        }

        /* INTERACTIVE TIMELINE */
        .timeline {
            position: relative;
            max-width: 800px;
            margin: 40px auto;
        }
        .timeline::after {
            content: '';
            position: absolute;
            width: 4px;
            background-color: rgba(241, 137, 115, 0.3);
            top: 0;
            bottom: 0;
            left: 50%;
            margin-left: -2px;
            border-radius: 10px;
        }
        .timeline-container {
            padding: 10px 40px;
            position: relative;
            background-color: inherit;
            width: 50%;
            box-sizing: border-box;
        }
        .timeline-container::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            right: -10px;
            background-color: var(--bg);
            border: 4px solid var(--coral);
            top: 15px;
            border-radius: 50%;
            z-index: 1;
            transition: 0.3s;
        }
        .left { left: 0; }
        .right { left: 50%; }
        .right::after { left: -10px; }
        .timeline-container:hover::after { background-color: var(--coral); transform: scale(1.3); box-shadow: 0 0 15px rgba(241, 137, 115, 0.4); }
        
        .timeline-content {
            padding: 25px 30px;
            background: rgba(252, 224, 216, 0.4);
            backdrop-filter: blur(8px);
            position: relative;
            border-radius: 25px;
            border: 1px solid rgba(241, 137, 115, 0.2);
            transition: 0.4s transform, 0.4s box-shadow;
            box-shadow: 0 10px 25px rgba(132, 55, 86, 0.05);
        }
        .timeline-content:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 20px 40px rgba(132, 55, 86, 0.12); 
            background: rgba(252, 224, 216, 0.6);
        }
        .timeline-content h3 { margin-top: 0; margin-bottom: 10px; color: var(--coral); font-weight: 900; font-size: 16px; }
        .timeline-content p { margin: 0; font-size: 13px; text-align: left; color: #67545d; }

        /* INTERACTIVE CARDS (The Craft) */
        .craft-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .craft-card {
            background: rgba(252, 224, 216, 0.2);
            backdrop-filter: blur(5px);
            padding: 30px;
            border-radius: 25px;
            text-align: center;
            border: 1px solid rgba(241, 137, 115, 0.15);
            transition: 0.4s;
            cursor: default;
        }
        .craft-card:hover {
            background: rgba(252, 224, 216, 0.5);
            border-color: rgba(241, 137, 115, 0.4);
            transform: translateY(-10px);
            box-shadow: 0 18px 40px rgba(132, 55, 86, 0.1);
        }
        .craft-icon {
            font-size: 40px;
            margin-bottom: 15px;
            text-shadow: 0 5px 15px rgba(241, 137, 115, 0.2);
        }
        .craft-card h4 { color: var(--coral); font-weight: 900; margin-bottom: 10px; font-size: 15px; text-transform: uppercase; letter-spacing: 1px; }
        .craft-card p { font-size: 13px; color: #67545d; margin: 0; text-align: center; }

        /* SCROLL ANIMATIONS */
        .reveal {
            opacity: 0;
            transform: translateY(50px);
            transition: all 0.8s cubic-bezier(0.5, 0, 0, 1);
        }
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }

        footer {
            padding: 40px;
            text-align: center;
            background: rgba(252, 224, 216, 0.3);
            border-top: 1px solid rgba(241, 137, 115, 0.2);
            color: #a38290;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* Mobile Timeline Adjustment */
        @media screen and (max-width: 600px) {
            .story-section { padding: 40px 30px; }
            .timeline::after { left: 31px; }
            .timeline-container { width: 100%; padding-left: 70px; padding-right: 25px; }
            .timeline-container::after { left: 21px; }
            .right { left: 0%; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

<?php include 'notifications.php'; ?>
<?php include 'navbar.php'; ?>

<section class="banner"></section>

<div class="story-wrapper">
    
    <div class="story-section reveal">
        <h1 class="page-title">our blooming story</h1>
        <span class="sub-tag">The Beginning</span>
        
        <p>
            Chub's Handicrafts began with a fascination for the delicate balance between nature and permanence. 
            In a world where fleeting beauty is often the standard, we sought a way to capture the 
            elegance of a blooming bouquet and preserve it indefinitely. This vision led to the 
            birth of <strong>Chub's Handicrafts</strong>.
        </p>
        <p>
            Operating from the heart of Dasmariñas, what started as a small local initiative has evolved into a diverse craft house. 
            We realized that flowers speak a universal language, but real flowers fade. We wanted to give people a way to hold onto 
            that language forever.
        </p>
    </div>

    <div class="quote-block reveal">
        <h3>"Every gift should be a lasting memory, not just a temporary gesture."</h3>
    </div>

    <div class="story-section reveal" style="background: transparent; border: none; box-shadow: none; padding: 0;">
        <h2>How We Grew</h2>
        <div class="timeline">
            <div class="timeline-container left reveal">
                <div class="timeline-content">
                    <h3>2022: The First Seed</h3>
                    <p>It all started as a passionate hobby. Experimenting with different crafting materials, we discovered the charm and versatility of fuzzy wires.</p>
                </div>
            </div>
            <div class="timeline-container right reveal">
                <div class="timeline-content">
                    <h3>2024: Taking Root</h3>
                    <p>Chub's Handicrafts was officially established in Dasmariñas. We began taking local orders, perfecting our French wrapping techniques.</p>
                </div>
            </div>
            <div class="timeline-container left reveal">
                <div class="timeline-content">
                    <h3>2026: Branching Out</h3>
                    <p>With overwhelming support, we expanded our catalog. Beyond our signature everlastings, we introduced interactive customized apparel and advanced online customization.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="story-section reveal">
        <h2>The Craft</h2>
        <p style="text-align: center; margin-bottom: 30px;">At Chub's Handicrafts, our mission remains unchanged: to deliver handcrafted excellence that defies the seasons. Here is how we ensure every piece feels special.</p>
        
        <div class="craft-grid">
            <div class="craft-card">
                <div class="craft-icon">🧶</div>
                <h4>Material Selection</h4>
                <p>We source high-quality, vibrant fuzzy wires and resilient stems that hold their shape while maintaining a soft, delicate texture.</p>
            </div>
            <div class="craft-card">
                <div class="craft-icon">🤲</div>
                <h4>Hand-Shaping</h4>
                <p>Every single petal and leaf is shaped entirely by hand. No molds, no mass production, just careful, intentional craftsmanship.</p>
            </div>
            <div class="craft-card">
                <div class="craft-icon">🎀</div>
                <h4>Finishing Touches</h4>
                <p>We wrap every arrangement with timeless French aesthetics, tying it all together with premium ribbons and personalized notes.</p>
            </div>
        </div>
    </div>

</div>

<footer>
    &copy; 2026 Chub's Handicrafts | Handcrafted in Dasmariñas
</footer>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const reveals = document.querySelectorAll(".reveal");

        const revealOnScroll = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("active");
                    observer.unobserve(entry.target); 
                }
            });
        }, {
            root: null,
            threshold: 0.15, 
            rootMargin: "0px 0px -50px 0px"
        });

        reveals.forEach(reveal => {
            revealOnScroll.observe(reveal);
        });
    });
</script>

</body>
</html>