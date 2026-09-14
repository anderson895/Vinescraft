<?php
session_start(); 
require_once 'db_connect.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        if (password_verify($password, $row['password'])) {
            // Independent keys para sa Customer Session
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['user_name'] = $row['name'];
            $_SESSION['user_logged_in'] = true; 

            // RECORD LOGIN HISTORY
            $uid = $row['user_id'];
            $ip = $_SERVER['REMOTE_ADDR']; 
            $log_sql = "INSERT INTO login_history (user_id, role, ip_address) VALUES ($uid, 'user', '$ip')";
            $conn->query($log_sql);

            header("Location: index.php");
            exit();
        } else {
            $error = "Mali ang password.";
        }
    } else {
        $error = "Walang account na nakarehistro sa email na yan.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Chub's Handicrafts</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
        
        :root {
            --coral: #f18973;
            --peach: #fce0d8;
            --text: #444;
            --bg: #fffafb;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--bg);
            margin: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: var(--text);
        }

        /* BACK BUTTON STYLE */
        .top-nav {
            position: absolute;
            top: 30px;
            left: 30px;
        }

        .back-home-btn {
            text-decoration: none;
            color: var(--coral);
            font-weight: 900;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px 20px;
            border: 2px solid var(--peach);
            border-radius: 50px;
            transition: 0.3s;
            background: white;
        }

        .back-home-btn:hover {
            background: var(--peach);
            transform: translateX(-5px);
        }

        .login-card {
            background: white;
            padding: 50px 40px;
            border-radius: 30px;
            border: 1px solid var(--peach);
            box-shadow: 0 15px 35px rgba(241, 137, 115, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .login-card h2 {
            color: var(--coral);
            font-weight: 900;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .login-card p {
            font-size: 11px;
            color: #aaa;
            margin-bottom: 30px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            color: #bbb;
            margin-bottom: 8px;
            margin-left: 15px;
        }

        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 15px 20px;
            border: 1px solid var(--peach);
            border-radius: 50px;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
            transition: 0.3s;
            background: var(--bg);
        }

        input:focus {
            border-color: var(--coral);
            background: white;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: var(--coral);
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 900;
            font-size: 12px;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
            box-shadow: 0 8px 20px rgba(241, 137, 115, 0.3);
        }

        .btn-login:hover {
            background: #e07661;
            transform: translateY(-2px);
        }

        .error-msg {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 20px;
            border: 1px solid #ffcdd2;
        }

        .footer-links {
            margin-top: 25px;
            font-size: 11px;
            color: #bbb;
        }

        .footer-links a {
            text-decoration: none;
            color: var(--coral);
            font-weight: 700;
        }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
    <style>
        .login-card {
            position: relative;
            overflow: hidden;
            border-radius: 24px !important;
            box-shadow: 0 22px 55px rgba(184, 76, 118, 0.16) !important;
        }

        .login-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #f8dbe5, #f47da8, #7f9b73);
        }

        .login-card h2 {
            font-size: 34px !important;
            margin-top: 6px;
        }

        .login-card p,
        label,
        .footer-links {
            font-size: 12px !important;
        }

        input[type="email"],
        .password-wrap input {
            min-height: 52px;
            font-size: 15px !important;
        }

        .password-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrap,
        .password-wrap * {
            outline: none !important;
        }

        .password-wrap input {
            width: 100%;
            padding-right: 84px !important;
        }

        .toggle-password {
            all: unset;
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            color: var(--coral) !important;
            cursor: pointer;
            display: inline-block;
            font-family: 'Montserrat', sans-serif;
            font-size: 11px !important;
            font-weight: 900;
            letter-spacing: 0.8px;
            line-height: 1;
            min-height: 0 !important;
            min-width: 0 !important;
            outline: 0 !important;
            padding: 0 !important;
            text-transform: uppercase;
            width: auto !important;
            -webkit-appearance: none;
            appearance: none;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }

        .toggle-password:hover,
        .toggle-password:focus,
        .toggle-password:active {
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
            color: #b84c76 !important;
            outline: 0 !important;
            transform: translateY(-50%);
        }

        .toggle-password::-moz-focus-inner {
            border: 0 !important;
            padding: 0 !important;
        }

        .btn-login {
            font-size: 13px !important;
            min-height: 52px;
        }
    </style>
</head>
<body>

    <!-- NAVIGATION PARA SA BACK TO HOME -->
    <div class="top-nav">
        <a href="index.php" class="back-home-btn">← Back to Shop</a>
    </div>

    <div class="login-card">
        <h2>Welcome back</h2>
        <p>Login to your account</p>

        <?php if ($error != ""): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="Enter your email">
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="password-wrap">
                    <input type="password" id="login-password" name="password" required placeholder="••••••••">
                    <span class="toggle-password" data-target="login-password" role="button" tabindex="0">Show</span>
                </div>
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>

        <div class="footer-links">
            Wala pang account? <a href="register.php">Mag-register dito</a>
        </div>
    </div>

    <script>
        document.querySelectorAll('.toggle-password').forEach(function(button) {
            button.addEventListener('click', function() {
                var input = document.getElementById(button.dataset.target);
                var isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                button.textContent = isHidden ? 'Hide' : 'Show';
            });
            button.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    button.click();
                }
            });
        });
    </script>

</body>
</html>
