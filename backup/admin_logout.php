<?php
session_start();

// 1. Tanggalin ang admin-specific data lang[cite: 6]
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_logged_in']);

// 2. Redirect pabalik sa admin login page[cite: 6]
header("Location: admin_login.php");
exit();
?>