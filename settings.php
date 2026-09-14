<?php
/**
 * Site-wide settings na naka-store sa `site_settings` table (key/value).
 * Isang query lang kada request - naka-cache sa static array.
 */

function settings_all($conn, $force_reload = false) {
    static $cache = null;
    if ($cache !== null && !$force_reload) return $cache;

    $cache = [];
    try {
        $res = $conn->query("SELECT setting_key, setting_value FROM site_settings");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        }
    } catch (Throwable $e) {
        // Kung wala pa yung table, defaults muna ang gagamitin ng mga caller.
        $cache = [];
    }
    return $cache;
}

function setting_get($conn, $key, $default = null) {
    $all = settings_all($conn);
    if (!array_key_exists($key, $all) || $all[$key] === null || $all[$key] === '') return $default;
    return $all[$key];
}

function setting_money($conn, $key, $default = 0) {
    return floatval(setting_get($conn, $key, $default));
}

function setting_int($conn, $key, $default = 0) {
    return intval(setting_get($conn, $key, $default));
}

function setting_bool($conn, $key, $default = false) {
    $v = setting_get($conn, $key, $default ? '1' : '0');
    return in_array(strtolower((string)$v), ['1', 'true', 'yes', 'on'], true);
}

function setting_set($conn, $key, $value) {
    try {
        $stmt = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->bind_param("ss", $key, $value);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) settings_all($conn, true); // i-refresh ang cache
        return $ok;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Shipping fee base sa piniling delivery method at courier.
 * Pick Up at Walk In ay karaniwang 0 - lahat editable sa admin_store_settings.php.
 */
function shipping_fee_for($conn, $shipping_method, $courier = null) {
    if ($shipping_method === 'pickup') return setting_money($conn, 'ship_fee_pickup', 0);
    if ($shipping_method === 'walkin') return setting_money($conn, 'ship_fee_walkin', 0);

    // 'ship' - depende sa courier
    if ($courier === 'Lalamove') return setting_money($conn, 'ship_fee_lalamove', 150);
    return setting_money($conn, 'ship_fee_jnt', 100);
}

/** Listahan ng courier options na ipapakita sa checkout. */
function courier_options($conn) {
    return [
        'J&T'      => setting_money($conn, 'ship_fee_jnt', 100),
        'Lalamove' => setting_money($conn, 'ship_fee_lalamove', 150),
    ];
}
