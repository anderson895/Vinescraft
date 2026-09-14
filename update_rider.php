<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $order_id = $_POST['order_id'];
    $rider_details = $conn->real_escape_string($_POST['rider_details']);
    $user_id = $_SESSION['user_id'];

    // I-update ang order record. Siguraduhin na sa kanya talagang order yung inaupdate niya.
    $sql = "UPDATE orders SET rider_info = '$rider_details' WHERE order_id = $order_id AND user_id = $user_id";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Rider info updated!'); window.location.href='profile.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>