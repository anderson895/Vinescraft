<?php
session_start();
require_once 'db_connect.php';

// Check kung naka-login at kung galing sa POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $p_id = intval($_POST['product_id']);
    $u_id = $_SESSION['user_id'];
    $rating = intval($_POST['rating']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    // INSERT INTO sa existing reviews table mo
    $sql = "INSERT INTO reviews (product_id, user_id, rating, comment) VALUES ($p_id, $u_id, $rating, '$comment')";

    if ($conn->query($sql) === TRUE) {
        header("Location: product_view.php?id=$p_id&msg=Review submitted!");
        exit();
    } else {
        echo "May error sa database: " . $conn->error;
    }
} else {
    header("Location: index.php");
}
?>