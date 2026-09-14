<?php
require_once 'db_connect.php';

$message = "";
$msg_type = ""; // Para sa styling ng alert

// I-check kung pinindot yung submit button
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $mobile_no = mysqli_real_escape_string($conn, $_POST['mobile_no']);
    $password = $_POST['password'];

    // --- BAGO: I-check muna sa database kung ginagamit na ang email ---
    $check_email = $conn->query("SELECT * FROM users WHERE email = '$email'");

    if ($check_email->num_rows > 0) {
        // Kung may nahanap na kaparehong email, maglalabas ng error
        $message = "This email is already in use. Please use a different email.";
        $msg_type = "error";
    } else {
        // Kung walang kapareho, itutuloy ang pag-save
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // SQL query para mag insert ng data sa users table (blangko na ang address dito)
        $sql = "INSERT INTO users (name, email, mobile_no, password, address, role) 
                VALUES ('$name', '$email', '$mobile_no', '$hashed_password', '', 'customer')";

        if ($conn->query($sql) === TRUE) {
            $message = "Registration successful! Pwede ka na mag-login.";
            $msg_type = "success";
            // Optional: Redirect sa login after 2 seconds
            echo "<script>setTimeout(function(){ window.location.href = 'login.php'; }, 2000);</script>";
        } else {
            $message = "Error: " . $conn->error;
            $msg_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Vinescraft</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');

        :root {
            --coral: #f18973;
            --peach: #fce0d8;
            --text: #444;
            --bg: #fdf2f2;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--bg);
            margin: 0;
            color: var(--text);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* NAVIGATION BAR */
        .top-nav { 
            background: var(--coral); 
            padding: 15px 5%; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            color: white; 
            box-shadow: 0 4px 15px rgba(241, 137, 115, 0.2);
        }
        .top-nav .logo {
            font-weight: 900; 
            font-size: 18px; 
            letter-spacing: 1px;
        }
        .top-nav a { 
            color: white; 
            text-decoration: none; 
            font-size: 10px; 
            font-weight: 900; 
            text-transform: uppercase; 
            letter-spacing: 1px;
        }

        .container {
            max-width: 500px;
            margin: 50px auto;
            padding: 0 20px;
            flex-grow: 1;
        }

        .register-card {
            background: white;
            padding: 45px;
            border-radius: 35px;
            border: 1px solid var(--peach);
            box-shadow: 0 20px 40px rgba(241, 137, 115, 0.1);
            text-align: center;
        }

        h2 {
            font-size: 32px;
            font-weight: 900;
            color: var(--coral);
            margin: 0;
        }
        
        .sub-tag {
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            color: #bbb;
            letter-spacing: 2px;
            display: block;
            margin-bottom: 30px;
        }

        /* ALERTS */
        .alert {
            padding: 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 25px;
            border: 1px solid;
        }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-color: #a5d6a7; }
        .alert-error { background: #ffebee; color: #c62828; border-color: #ef9a9a; }

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
            color: var(--coral);
            margin-bottom: 8px;
            margin-left: 10px;
            letter-spacing: 1px;
        }

        input[type="text"], input[type="email"], input[type="password"], textarea {
            width: 100%;
            padding: 15px 25px;
            border: 2px solid var(--bg);
            border-radius: 50px;
            font-family: 'Montserrat', sans-serif;
            font-size: 13px;
            box-sizing: border-box;
            outline: none;
            background: #fffcfc;
            color: var(--text);
            transition: 0.3s;
        }

        input:focus, textarea:focus {
            border-color: var(--peach);
            background: #fff;
        }

        textarea {
            resize: none;
            height: 80px;
            border-radius: 25px;
        }

        .btn-register {
            width: 100%;
            padding: 18px;
            background: var(--coral);
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 900;
            font-size: 12px;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 10px 20px rgba(241, 137, 115, 0.2);
            margin-top: 10px;
        }

        .btn-register:hover {
            background: #e07661;
            transform: translateY(-2px);
        }

        .footer-links {
            margin-top: 25px;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .footer-links a {
            text-decoration: none;
            color: #ccc;
            transition: 0.3s;
        }

        .footer-links a:hover { color: var(--coral); }

        footer {
            text-align: center;
            font-size: 10px;
            color: #bbb;
            padding: 40px 20px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

<nav class="top-nav">
    <div class="logo">vinescraft</div>
    <a href="login.php">Login</a>
</nav>

<div class="container">
    <div class="register-card">
        <h2>Register</h2>
        <span class="sub-tag">join our blooming community</span>

        <?php if ($message != ""): ?>
            <div class="alert alert-<?= $msg_type == 'success' ? 'success' : 'error' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="Juan Dela Cruz" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="juan@example.com" required>
            </div>

            <div class="form-group">
                <label>Mobile Number</label>
                <input type="text" name="mobile_no" placeholder="09123456789" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="password-wrap">
                    <input type="password" id="register-password" name="password" placeholder="••••••••" required>
                    <button type="button" class="toggle-password" data-target="register-password">Show</button>
                </div>
            </div>

            <button type="submit" class="btn-register">Create Account</button>
        </form>

        <div class="footer-links">
            <a href="index.php">Back to Home</a>
        </div>
    </div>
</div>

<footer>
    &copy; 2026 Gazette in Vines Flower and Craft Shop
</footer>

<script>
    document.querySelectorAll('.toggle-password').forEach(function(button) {
        button.addEventListener('click', function() {
            var input = document.getElementById(button.dataset.target);
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            button.textContent = isHidden ? 'Hide' : 'Show';
        });
    });
</script>

</body>
</html>