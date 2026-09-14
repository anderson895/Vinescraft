<?php
require_once 'db_connect.php';

$name = "Admin";
$email = "gazetteinvines@gmail.com";
$password = "admin123";
$role = "admin";

// Eto yung tamang paraan para gumawa ng hash sa PHP
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// I-check muna natin kung may admin na para hindi doble-doble
$check = $conn->query("SELECT * FROM users WHERE email = '$email'");

if ($check->num_rows > 0) {
    // Kung meron na, i-update na lang natin yung password para sure
    $sql = "UPDATE users SET password = '$hashed_password', role = 'admin' WHERE email = '$email'";
    $action = "updated";
} else {
    // Kung wala pa, i-insert natin
    $sql = "INSERT INTO users (name, email, mobile_no, password, address, role) 
            VALUES ('$name', '$email', '09000000000', '$hashed_password', 'Shop Address', '$role')";
    $action = "created";
}

if ($conn->query($sql) === TRUE) {
    echo "<h3>Success! Admin account $action successfully.</h3>";
    echo "Email: $email <br> Password: $password <br><br>";
    echo "<a href='admin_login.php'>Subukan mo na mag-login dito</a>";
} else {
    echo "Error: " . $conn->error;
}
?>