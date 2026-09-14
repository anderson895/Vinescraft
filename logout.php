<?php
session_start();

// 1. Burahin lahat ng laman ng $_SESSION array
$_SESSION = array();

// 2. Kung may session cookie, burahin din (Best practice ito)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Tuluyan nang sirain ang session sa server
session_destroy();

// 4. Redirect pabalik sa home
header("Location: index.php");
exit();
?>