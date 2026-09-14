<?php
session_start();
require_once 'db_connect.php';

// Kukunin natin ang hanggang 4 products para sa clean grid
$sql_featured = "SELECT * FROM products WHERE is_featured = 1 LIMIT 4";
$result_featured = $conn->query($sql_featured);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gazette in Vines | Home</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');

        :root {
            --coral: #f18973;
            --peach: #fce0d8;
            --text-dark: #444;
            --bg-soft: #fffafb;
        }

        body { font-family: 'Montserrat', sans-serif; margin: 0; color: var(--text-dark); background-color: #fff; }
        
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

        /* HERO SECTION */
        .hero { 
            height: 80vh; 
            background: linear-gradient(rgba(0,0,0,0.1), rgba(0,0,0,0.1)), url('img/banner.jpg'); 
            background-size: cover; 
            background-position: center;
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: flex-start; 
            padding-left: 10%;
        }
        .hero h1 { font-size: 60px; font-weight: 900; margin: 5px 0; color: white; line-height: 1; }
        .hero h1 span { background: rgba(255, 255, 255, 0.7); color: var(--coral); padding: 5px 15px; border-radius: 4px; }
        
        .order-circle {
            width: 120px; height: 120px; border-radius: 50%; background: rgba(241, 137, 115, 0.8);
            color: white; border: 2px solid white; display: flex; align-items: center; justify-content: center;
            font-weight: bold; text-transform: uppercase; margin-top: 20px; cursor: pointer; transition: 0.3s; text-decoration: none;
        }
        .order-circle:hover { transform: scale(1.1); background: var(--coral); }

        /* SECTION HEADINGS */
        .section-title { text-align: center; margin: 80px 0 30px; color: var(--coral); }
        .section-title h2 { font-size: 32px; font-weight: 900; margin-bottom: 5px; text-transform: lowercase; }
        .section-title h2::after { content: '.'; }
        .section-title p { font-size: 13px; color: #777; max-width: 600px; margin: 0 auto; line-height: 1.6; }

        /* PRODUCT GRID */
        .product-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; max-width: 1100px; margin: 0 auto; padding: 20px; }
        .p-card { text-decoration: none; color: inherit; transition: 0.3s; }
        .p-card img { width: 100%; height: 350px; object-fit: cover; border-radius: 20px; border: 1px solid var(--peach); }
        .p-card h3 { font-size: 16px; margin: 15px 0 5px; color: var(--coral); font-weight: 900; }
        .p-card p { font-size: 14px; font-weight: bold; margin: 0; }

        /* STORY SECTION */
        .story-preview {
            background: var(--bg-soft);
            padding: 100px 10%;
            display: flex;
            align-items: center;
            gap: 60px;
            margin-top: 60px;
        }
        .story-img-container { flex: 1; }
        .story-img-container img { 
            width: 100%; 
            border-radius: 30px; 
            box-shadow: 20px 20px 0px var(--peach);
            border: 1px solid var(--peach);
        }
        .story-content { flex: 1; }
        .story-content h2 { font-size: 36px; font-weight: 900; color: var(--coral); text-transform: lowercase; margin-bottom: 20px; }
        .story-content h2::after { content: '.'; }
        .story-content p { font-size: 15px; line-height: 1.8; color: #666; margin-bottom: 30px; text-align: justify; }
        .btn-story { 
            display: inline-block;
            background: var(--coral); 
            color: white; 
            text-decoration: none; 
            padding: 15px 35px; 
            border-radius: 50px; 
            font-weight: 900; 
            font-size: 12px; 
            text-transform: uppercase;
            box-shadow: 0 10px 20px rgba(241, 137, 115, 0.2);
            transition: 0.3s;
        }
        .btn-story:hover { background: #e07661; transform: translateY(-3px); }

        /* LOCATION & HOURS SECTION */
        .location-section {
            max-width: 1100px;
            margin: 80px auto;
            padding: 0 20px;
            display: flex;
            gap: 60px;
            align-items: center;
            flex-wrap: wrap;
        }
        .location-details { flex: 1; min-width: 300px; }
        .location-details h2 { font-size: 32px; font-weight: 900; color: var(--coral); text-transform: lowercase; margin-bottom: 20px; }
        .location-details h2::after { content: '.'; }
        .location-details p { font-size: 15px; color: #666; margin-bottom: 10px; line-height: 1.6; }
        
        .hours-box {
            background: var(--bg-soft);
            padding: 25px;
            border-radius: 20px;
            border: 1px dashed var(--coral);
            margin-top: 25px;
        }
        .hours-box h3 { margin: 0 0 15px 0; font-size: 16px; color: var(--coral); text-transform: uppercase; font-weight: 900; }
        .hours-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; font-weight: 700; color: var(--text-dark); }
        .closed-text { color: #ff4757; }

        .location-map { 
            flex: 1.2; 
            min-width: 300px;
            border-radius: 30px; 
            overflow: hidden; 
            border: 1px solid var(--peach); 
            box-shadow: 0 20px 40px rgba(241, 137, 115, 0.15); 
        }

        /* CUSTOMIZATION SECTION */
        .custom-section { display: flex; gap: 20px; max-width: 1100px; margin: 0 auto; padding: 40px 20px; flex-wrap: wrap; }
        .custom-box { flex: 1; min-width: 300px; position: relative; overflow: hidden; height: 500px; border-radius: 20px; border: 1px solid var(--peach); }
        .custom-box img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
        .custom-box .overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.7)); padding: 30px; color: white; }

        footer { padding: 60px; text-align: center; background: #fff; border-top: 1px solid var(--peach); color: #888; font-size: 12px; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
    <style>
        body {
            background:
                radial-gradient(circle at 8% 18%, rgba(240, 100, 154, 0.1), transparent 260px),
                radial-gradient(circle at 92% 46%, rgba(104, 143, 99, 0.09), transparent 280px),
                radial-gradient(circle at 18% 88%, rgba(248, 162, 109, 0.09), transparent 260px),
                linear-gradient(135deg, rgba(255, 244, 248, 0.72) 0 18%, transparent 18% 52%, rgba(238, 247, 235, 0.52) 52% 68%, transparent 68%) 0 0 / 64px 64px,
                linear-gradient(180deg, #fffdfb 0%, #fff4f8 42%, #fffaf6 100%) !important;
        }

        body::before {
            opacity: 0.5 !important;
        }

        .top-nav .nav-brand {
            position: relative;
            display: inline-flex !important;
            align-items: center;
            gap: 10px;
            padding: 10px 18px 10px 16px !important;
            border-radius: 999px !important;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.24), rgba(255, 255, 255, 0.09)),
                rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.55) !important;
            color: #ffffff !important;
            font-size: 21px !important;
            font-weight: 900 !important;
            letter-spacing: 0.3px !important;
            line-height: 1;
            text-transform: lowercase;
            text-shadow: 0 2px 12px rgba(92, 25, 56, 0.24);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.28),
                0 10px 24px rgba(92, 25, 56, 0.14) !important;
            overflow: hidden;
        }

        .top-nav .nav-brand::before {
            content: "";
            width: 13px;
            height: 13px;
            flex: 0 0 13px;
            border-radius: 999px 999px 999px 3px;
            background: linear-gradient(135deg, #eff8e9, #8fba80);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.16);
            transform: rotate(-28deg);
        }

        .top-nav .nav-brand::after {
            content: "";
            position: absolute;
            inset: 2px;
            border-radius: inherit;
            border: 1px solid rgba(255, 255, 255, 0.18);
            pointer-events: none;
        }

        .hero {
            position: relative;
            isolation: isolate;
            min-height: 72vh !important;
            padding: 96px 10% 82px !important;
            background:
                linear-gradient(90deg, rgba(42, 25, 34, 0.68), rgba(157, 53, 96, 0.26) 50%, rgba(255, 255, 255, 0.08)),
                url('img/banner.jpg') !important;
            background-size: cover !important;
            background-position: center !important;
            overflow: hidden;
            align-items: flex-start !important;
        }

        .hero::after {
            content: "";
            position: absolute;
            inset: auto 6% 34px auto;
            width: min(360px, 42vw);
            height: min(360px, 42vw);
            z-index: -1;
            border-radius: 48% 52% 46% 54%;
            background:
                radial-gradient(ellipse at 42% 24%, rgba(255, 255, 255, 0.44), transparent 40%),
                radial-gradient(ellipse at 55% 72%, rgba(255, 216, 229, 0.36), transparent 54%);
            filter: blur(1px);
            opacity: 0.82;
        }

        .hero-kicker {
            display: inline-flex;
            width: fit-content;
            margin-bottom: 4px;
            padding: 9px 14px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            backdrop-filter: blur(12px);
        }

        .hero-copy {
            width: min(680px, 100%);
            padding: 34px;
            border: 1px solid rgba(255, 255, 255, 0.34);
            border-radius: 30px;
            background:
                linear-gradient(135deg, rgba(67, 31, 47, 0.58), rgba(127, 47, 82, 0.22)),
                rgba(255, 255, 255, 0.08);
            box-shadow: 0 24px 64px rgba(31, 12, 22, 0.26);
            backdrop-filter: blur(8px);
        }

        .hero h1 {
            max-width: 720px;
            margin: 12px 0 0 !important;
            font-size: clamp(38px, 5.4vw, 62px) !important;
            line-height: 1.05 !important;
            letter-spacing: -0.8px !important;
            color: #fff !important;
            text-transform: none !important;
            text-shadow: 0 12px 28px rgba(32, 11, 22, 0.34);
        }

        .hero h1 span {
            display: block;
            width: fit-content;
            margin-bottom: 8px;
            background: transparent !important;
            color: #ffe1eb !important;
            border: 0 !important;
            border-radius: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            font-size: clamp(18px, 2.2vw, 27px);
            font-weight: 700;
            letter-spacing: 3px;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .hero h1 em {
            color: #ffd6e4;
            font-style: normal;
            position: relative;
            white-space: nowrap;
        }

        .hero h1 em::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 5px;
            height: 10px;
            z-index: -1;
            border-radius: 999px;
            background: rgba(240, 100, 154, 0.34);
        }

        .hero-tagline {
            max-width: 610px;
            margin: 10px 0 6px;
            color: rgba(255, 255, 255, 0.94);
            font-size: clamp(16px, 1.8vw, 21px);
            font-weight: 800;
            line-height: 1.45;
            text-shadow: 0 8px 22px rgba(42, 16, 29, 0.28);
        }

        .hero-subcopy {
            max-width: 560px;
            margin: 0;
            color: rgba(255, 255, 255, 0.84);
            font-size: 14px;
            line-height: 1.8;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 26px;
        }

        .hero .order-circle {
            width: auto !important;
            height: auto !important;
            min-width: 0 !important;
            min-height: 0 !important;
            border-radius: 999px !important;
            padding: 15px 26px !important;
            border: 1px solid rgba(255, 255, 255, 0.62) !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            margin-top: 0 !important;
            line-height: 1 !important;
            white-space: nowrap;
            box-shadow: 0 14px 30px rgba(200, 66, 120, 0.28) !important;
            transform: none !important;
        }

        .hero .order-circle:hover {
            transform: translateY(-2px) !important;
        }

        .section-title {
            position: relative;
            z-index: 1;
        }

        .section-title p {
            background: rgba(255, 255, 255, 0.58);
            border: 1px solid rgba(244, 202, 217, 0.62);
            border-radius: 999px;
            padding: 10px 18px;
            box-shadow: 0 10px 26px rgba(132, 55, 86, 0.07);
        }

        .product-grid {
            position: relative;
            max-width: 1140px !important;
            margin: 0 auto 78px !important;
            padding: 34px 24px 42px !important;
            border-radius: 34px;
            background:
                radial-gradient(circle at 12% 18%, rgba(240, 100, 154, 0.12), transparent 240px),
                radial-gradient(circle at 86% 82%, rgba(104, 143, 99, 0.1), transparent 260px),
                linear-gradient(135deg, rgba(255, 255, 255, 0.82), rgba(255, 246, 249, 0.74));
            border: 1px solid rgba(244, 202, 217, 0.76);
            box-shadow: 0 22px 60px rgba(132, 55, 86, 0.09);
        }

        .hero-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 45px;
            color: #fff;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 1px;
            text-decoration: none;
            text-transform: uppercase;
            border: 1px solid rgba(255, 255, 255, 0.36);
            border-radius: 999px;
            padding: 0 20px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
        }

        .home-feature-banner {
            max-width: 1120px;
            margin: 56px auto 52px;
            padding: 0 20px;
            position: relative;
            z-index: 3;
        }

        .banner-card {
            min-height: 300px;
            border-radius: 30px;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(255, 244, 248, 0.92)),
                radial-gradient(circle at 12% 14%, rgba(240, 100, 154, 0.13), transparent 230px);
            border: 1px solid rgba(244, 202, 217, 0.92);
            box-shadow: 0 28px 70px rgba(132, 55, 86, 0.18);
        }

        .banner-copy {
            padding: 42px;
            align-self: center;
        }

        .banner-copy span {
            display: inline-flex;
            margin-bottom: 14px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #fff3f7;
            color: #a82f62;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 1.3px;
            text-transform: uppercase;
        }

        .banner-copy h2 {
            margin: 0 0 12px;
            color: #9e335f;
            font-size: clamp(30px, 4vw, 52px);
            line-height: 1.02;
            letter-spacing: -1px;
        }

        .banner-copy p {
            max-width: 480px;
            margin: 0 0 24px;
            color: #67545d;
            font-size: 15px;
            line-height: 1.75;
        }

        .banner-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 24px;
            border-radius: 999px;
            background: linear-gradient(135deg, #f0649a, #c84278);
            color: #fff;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 1px;
            text-decoration: none;
            text-transform: uppercase;
            box-shadow: 0 14px 30px rgba(200, 66, 120, 0.24);
        }

        .banner-image {
            min-height: 300px;
            background:
                linear-gradient(90deg, rgba(255, 244, 248, 0.25), rgba(255, 244, 248, 0)),
                url('img/banner2.jpg');
            background-size: cover;
            background-position: center;
        }

        .specialty-title {
            margin-top: 92px !important;
        }

        .custom-section {
            position: relative;
            max-width: 1160px !important;
            margin: 0 auto 82px !important;
            padding: 56px 26px 64px !important;
            gap: 28px !important;
            border-radius: 34px;
            background:
                radial-gradient(circle at 10% 12%, rgba(240, 100, 154, 0.2), transparent 250px),
                radial-gradient(circle at 92% 78%, rgba(104, 143, 99, 0.17), transparent 280px),
                linear-gradient(120deg, rgba(255, 255, 255, 0.9), rgba(255, 240, 246, 0.78) 58%, rgba(238, 247, 235, 0.66));
            border: 1px solid rgba(244, 202, 217, 0.82);
            box-shadow: 0 28px 78px rgba(132, 55, 86, 0.14);
            overflow: hidden;
        }

        .custom-section::before {
            content: "Choose your keepsake";
            position: absolute;
            top: 24px;
            left: 28px;
            z-index: 2;
            color: #a82f62;
            background: rgba(255, 255, 255, 0.74);
            border: 1px solid rgba(244, 202, 217, 0.9);
            border-radius: 999px;
            padding: 8px 13px;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            box-shadow: 0 10px 22px rgba(132, 55, 86, 0.1);
        }

        .custom-section::after {
            content: "";
            position: absolute;
            right: -88px;
            top: -86px;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background:
                radial-gradient(ellipse at 50% 22%, rgba(255, 255, 255, 0.72) 0 34px, transparent 35px),
                radial-gradient(ellipse at 28% 56%, rgba(240, 100, 154, 0.18) 0 58px, transparent 59px),
                radial-gradient(ellipse at 72% 56%, rgba(248, 162, 109, 0.16) 0 56px, transparent 57px);
            opacity: 0.9;
            pointer-events: none;
        }

        .custom-box {
            min-height: 500px !important;
            border-radius: 28px !important;
            border: 1px solid rgba(255, 255, 255, 0.72) !important;
            box-shadow: 0 18px 46px rgba(74, 38, 53, 0.16) !important;
            transform: translateY(0);
        }

        .custom-box img {
            filter: saturate(1.08) contrast(1.04);
        }

        .custom-box::before {
            content: "";
            position: absolute;
            inset: 14px;
            z-index: 2;
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 22px;
            pointer-events: none;
        }

        .custom-box::after {
            content: attr(data-label);
            position: absolute;
            top: 28px;
            left: 28px;
            z-index: 3;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.84);
            color: #9e335f;
            padding: 8px 12px;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 1px;
            text-transform: uppercase;
            backdrop-filter: blur(10px);
        }

        .custom-box:hover img {
            transform: scale(1.06);
        }

        .custom-box .overlay {
            z-index: 3;
            padding: 36px !important;
            background:
                radial-gradient(circle at 18% 92%, rgba(240, 100, 154, 0.28), transparent 220px),
                linear-gradient(180deg, rgba(50, 28, 39, 0.02), rgba(50, 28, 39, 0.9) 82%) !important;
        }

        .custom-box .overlay h3 {
            margin: 0 0 10px;
            color: #fff !important;
            font-size: clamp(26px, 3.2vw, 38px) !important;
            line-height: 0.98;
            letter-spacing: -0.8px;
        }

        .custom-box .overlay p {
            max-width: 430px;
            margin: 0 0 16px;
            color: rgba(255, 255, 255, 0.9) !important;
            font-size: 15px !important;
            line-height: 1.65;
        }

        .custom-tagline {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.32);
            color: #fff;
            padding: 9px 12px;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            backdrop-filter: blur(10px);
        }

        .story-preview {
            position: relative;
            margin-top: 86px !important;
            background:
                radial-gradient(circle at 10% 20%, rgba(240, 100, 154, 0.11), transparent 280px),
                radial-gradient(circle at 86% 88%, rgba(104, 143, 99, 0.12), transparent 260px),
                linear-gradient(135deg, rgba(255, 255, 255, 0.88), rgba(255, 244, 248, 0.8)) !important;
            border-top: 1px solid rgba(244, 202, 217, 0.78);
            border-bottom: 1px solid rgba(244, 202, 217, 0.78);
            overflow: hidden;
        }

        .story-preview::after {
            content: "";
            position: absolute;
            right: -100px;
            bottom: -120px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(240, 100, 154, 0.14), transparent 68%);
            pointer-events: none;
        }

        .location-section {
            padding: 46px 34px !important;
            border-radius: 34px;
            background:
                radial-gradient(circle at 8% 20%, rgba(248, 162, 109, 0.11), transparent 230px),
                radial-gradient(circle at 92% 88%, rgba(240, 100, 154, 0.12), transparent 260px),
                linear-gradient(135deg, rgba(255, 255, 255, 0.86), rgba(255, 250, 246, 0.8));
            border: 1px solid rgba(244, 202, 217, 0.78);
            box-shadow: 0 22px 60px rgba(132, 55, 86, 0.09);
        }

        footer {
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.92), rgba(255, 244, 248, 0.92)),
                radial-gradient(circle at 50% 0%, rgba(240, 100, 154, 0.12), transparent 220px) !important;
        }

        @media (max-width: 820px) {
            .hero {
                min-height: auto !important;
                padding: 74px 24px 88px !important;
            }

            .hero-copy {
                padding: 24px;
                border-radius: 24px;
            }

            .hero h1 {
                font-size: clamp(31px, 10vw, 46px) !important;
            }

            .hero-actions {
                align-items: flex-start;
                flex-direction: column;
            }

            .home-feature-banner {
                margin-top: 34px;
            }

            .banner-card {
                grid-template-columns: 1fr;
            }

            .banner-copy {
                padding: 30px 24px;
            }

            .banner-image {
                min-height: 230px;
                order: -1;
            }

            .specialty-title {
                margin-top: 72px !important;
            }

            .custom-section {
                padding: 58px 16px 24px !important;
                border-radius: 24px;
            }

            .custom-section::before {
                left: 18px;
                top: 18px;
            }

            .custom-box {
                min-height: 420px !important;
            }

            .custom-box .overlay {
                padding: 28px !important;
            }
        }
    </style>
</head>
<body>

<nav class="top-nav">
    <div class="nav-brand">vinescraft.</div>
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
            <a href="logout.php" style="font-size: 9px; opacity: 0.6; margin-left: 5px;">(Logout)</a>
        <?php else: ?>
            <a href="login.php">Account</a>
        <?php endif; ?>
    </div>
</nav>

<!-- HERO SECTION -->
<section class="hero">
    <div class="hero-copy">
        <div class="hero-kicker">Handmade Floral Studio</div>
        <h1><span>Fluffy blooms</span>made to last <em>beautifully.</em></h1>
        <p class="hero-tagline">Flowers that stay soft, sweet, and unforgettable long after the moment passes.</p>
        <p class="hero-subcopy">Discover fuzzy wire bouquets, custom gifts, and keepsake pieces crafted with a personal touch from Las Pinas.</p>
        <div class="hero-actions">
            <a href="shop.php" class="order-circle">Order Now</a>
            <a href="customizer.php" class="hero-link">Create a custom bouquet</a>
        </div>
    </div>
</section>

<!-- HOME BANNER -->
<section class="home-feature-banner" aria-label="Featured custom gift banner">
    <div class="banner-card">
        <div class="banner-copy">
            <span>Made for gifting</span>
            <h2>Custom blooms with a little more heart.</h2>
            <p>Choose colors, styles, and details that match your story. We turn simple ideas into bouquets that feel personal, pretty, and ready to give.</p>
            <a href="customizer.php" class="banner-btn">Start Designing</a>
        </div>
        <div class="banner-image" role="img" aria-label="Aesthetic handmade floral banner"></div>
    </div>
</section>

<!-- FEATURED PRODUCTS -->
<div class="section-title">
    <h2>Daily Flower Collections</h2>
    <p>Hand-crafted arrangements delivered in friendly Las Piñas. Pick the perfect gesture for your loved ones now.</p>
</div>

<div class="product-grid">
    <?php while($row = $result_featured->fetch_assoc()): ?>
        <a href="product_view.php?id=<?= $row['product_id'] ?>" class="p-card">
            <span class="bestseller-badge">Bestseller</span>
            <img src="uploads/<?= $row['image'] ?>" alt="<?= $row['name'] ?>">
            <h3><?= htmlspecialchars($row['name']) ?></h3>
            <p>₱<?= number_format($row['price'], 2) ?></p>
        </a>
    <?php endwhile; ?>
</div>

<!-- CUSTOMIZATION SECTION -->
<div class="section-title specialty-title">
    <h2>Vinescraft Specialty</h2>
    <p>Pick your kind of keepsake: soft handmade blooms for sweet moments, or wearable art for everyday memories.</p>
</div>

<div class="custom-section">
    <a href="customizer.php" class="custom-box" data-label="Bouquet Studio">
        <img src="img/custom_bouquet.jpg" alt="Custom Bouquet">
        <div class="overlay">
            <h3>Custom Flowers</h3>
            <p>Build a bouquet that feels like your message: soft colors, lasting blooms, and details made especially for the person receiving it.</p>
            <span class="custom-tagline">Tagline: Say it with blooms that last</span>
        </div>
    </a>
    <a href="customize_tshirt.php" class="custom-box" data-label="Print Studio">
        <img src="img/custom_shirt.jpg" alt="Custom Shirt">
        <div class="overlay">
            <h3>Custom Prints</h3>
            <p>Turn names, ideas, or favorite designs into comfy statement shirts made for gifting, matching, or showing your own style.</p>
            <span class="custom-tagline">Tagline: Wear your story in bloom</span>
        </div>
    </a>
</div>

<!-- OUR STORY SECTION -->
<section class="story-preview">
    <div class="story-img-container">
        <img src="img/team.jpg" alt="Vinescraft Team">
    </div>
    <div class="story-content">
        <h2>the story behind the blooms</h2>
        <p>
            Vinescraft began with a fascination for nature and permanence. From the heart of Las Piñas, we transform fuzzy wire into timeless art, ensuring that every gift is a lasting memory, not just a temporary gesture.
        </p>
        <a href="about.php" class="btn-story">Read Our Full Story</a>
    </div>
</section>

<!-- LOCATION & HOURS SECTION -->
<section class="location-section">
    <div class="location-details">
        <h2>visit us</h2>
        <p>Drop by our shop to see our fuzzy wire creations, craft materials, and custom prints in person.</p>
        <p>📍 <strong>Location:</strong> 64A Gloria Diaz St. BF Resort Village, Las Piñas, Philippines, 1747</p>
        
        <div class="hours-box">
            <h3>Store Hours</h3>
            <div class="hours-row">
                <span>Monday - Friday</span>
                <span>9:00 AM - 6:00 PM</span>
            </div>
            <div class="hours-row">
                <span>Saturday - Sunday</span>
                <span class="closed-text">CLOSED</span>
            </div>
        </div>
    </div>
    <div class="location-map">
        <iframe 
            width="100%" 
            height="400" 
            frameborder="0" 
            style="border:0; display: block;"
            src="https://maps.google.com/maps?q=64A+Gloria+Diaz+St.+BF+Resort+Village,+Las+Piñas,+Philippines,+1747&t=&z=15&ie=UTF8&iwloc=&output=embed" 
            allowfullscreen>
        </iframe>
    </div>
</section>

<footer>
    <p>&copy; 2026 Gazette in Vines Flower and Craft Shop | Handcrafted in Las Piñas</p>
</footer>

</body>
</html>
