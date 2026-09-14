<?php
session_start();
require_once 'db_connect.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Hahanapin ang user na may role na 'admin'
    $sql = "SELECT * FROM users WHERE email = '$email' AND role = 'admin'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        if (password_verify($password, $row['password'])) {
            // Independent session keys para sa admin
            $_SESSION['admin_id'] = $row['user_id'];
            $_SESSION['admin_name'] = $row['name'];
            $_SESSION['admin_logged_in'] = true;

            // IP at Role tracking para sa login_history[cite: 6]
            $uid = $row['user_id'];
            $ip = $_SERVER['REMOTE_ADDR']; 
            
            $log_sql = "INSERT INTO login_history (user_id, role, ip_address) VALUES ($uid, 'admin', '$ip')";
            $conn->query($log_sql);

            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error = "Mali ang admin password.";
        }
    } else {
        $error = "Hindi admin account ang email na yan.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Vinescraft</title>
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
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: var(--text);
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
            text-transform: lowercase;
        }
        .login-card h2::after { content: '.'; }

        .login-card p {
            font-size: 12px;
            color: #888;
            margin-bottom: 30px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* FORM STYLES */
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

        input[type="email"],
        input[type="password"],
        input[type="text"].password-input {
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
            color: var(--text);
            caret-color: var(--coral);
        }

        input:focus {
            border-color: var(--coral);
            background: white;
            box-shadow: 0 5px 15px rgba(241, 137, 115, 0.1);
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

        .back-link {
            display: inline-block;
            margin-top: 25px;
            text-decoration: none;
            color: #bbb;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            transition: 0.3s;
        }

        .back-link:hover {
            color: var(--coral);
        }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
    <style>
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
            border-radius: 50px !important;
            background: var(--bg) !important;
            color: var(--text) !important;
        }

        .toggle-password {
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
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

        .toggle-password:focus-visible {
            color: #b84c76 !important;
            outline: 2px solid rgba(241, 137, 115, 0.35) !important;
            outline-offset: 3px;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <h2>admin portal.</h2>
        <p>Gazette in Vines</p>

        <?php if ($error != ""): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Admin Email</label>
                <input type="email" name="email" required placeholder="Enter your email">
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="password-wrap">
                    <input type="password" id="admin-password" class="password-input" name="password" required placeholder="Password" autocomplete="current-password">
                    <button type="button" class="toggle-password" data-target="admin-password" aria-label="Show password" aria-pressed="false">Show</button>
                </div>
            </div>

            <button type="submit" class="btn-login">Login as Admin</button>
        </form>

        <a href="index.php" class="back-link">&larr; Return to Shop</a>
    </div>

    <script>
        document.querySelectorAll('.toggle-password').forEach(function(button) {
            button.addEventListener('click', function() {
                var input = document.getElementById(button.dataset.target);
                var isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                button.textContent = isHidden ? 'Hide' : 'Show';
                button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                button.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            });
        });
    </script>

</body>
</html>
