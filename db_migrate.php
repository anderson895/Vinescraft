<?php
/**
 * Idempotent na schema migrations para sa chubs_db.
 *
 * Sinusundan nito ang dating pattern ng project ("auto-create column if it doesn't exist"
 * gaya sa checkout.php at process_final_payment.php), pero naka-gate sa isang
 * schema_version row para isang query lang ang gastos kapag updated na ang schema.
 *
 * MAHALAGA: nag-tatapon ng exception ang mysqli sa install na ito. Kaya bawat statement
 * ay may sariling try/catch - kapag may pumalya, hindi dapat mamatay ang buong site.
 */

define('CHUBS_SCHEMA_VERSION', 2);

function chubs_try($conn, $sql) {
    try {
        $conn->query($sql);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function chubs_col_exists($conn, $table, $column) {
    try {
        $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
        return $res && $res->num_rows > 0;
    } catch (Throwable $e) {
        return true; // i-assume na meron para hindi paulit-ulit subukan
    }
}

/** Nagbabalik ng TRUE lamang kapag KAKAGAWA lang ng column - para dun ilagay ang seed data. */
function chubs_add_col($conn, $table, $column, $definition) {
    if (chubs_col_exists($conn, $table, $column)) return false;
    return chubs_try($conn, "ALTER TABLE `$table` ADD `$column` $definition");
}

function chubs_seed_setting($conn, $key, $value) {
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
    }
}

function chubs_migrate($conn) {

    // ---- FAST PATH: isang query lang kapag up to date na ----
    try {
        $res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key = 'schema_version'");
        if ($res && ($row = $res->fetch_assoc()) && intval($row['setting_value']) >= CHUBS_SCHEMA_VERSION) {
            return;
        }
    } catch (Throwable $e) {
        // Wala pa ang site_settings table - tuloy sa buong migration sa ibaba.
    }

    // ---- 1. SETTINGS TABLE ----
    chubs_try($conn, "CREATE TABLE IF NOT EXISTS site_settings (
        setting_key   VARCHAR(64) NOT NULL PRIMARY KEY,
        setting_value TEXT DEFAULT NULL,
        updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ---- 2. RE-ORDER LEVELS ----
    if (chubs_add_col($conn, 'materials', 'reorder_level', "DECIMAL(10,2) NOT NULL DEFAULT 100")) {
        // Per-category seed. Ang flat na 100 sa lahat ay puro ingay lang:
        // 18-20 lang talaga ang normal na stock ng mga shirt, kaya laging mag-aalarma.
        chubs_try($conn, "UPDATE materials SET reorder_level = 5  WHERE LOWER(category) = 'shirt'");
        chubs_try($conn, "UPDATE materials SET reorder_level = 10 WHERE LOWER(category) = 'accessories'");
        chubs_try($conn, "UPDATE materials SET reorder_level = 20 WHERE LOWER(category) IN ('crafts','craft','packaging','materials')");
        // Ang 'bouquet' ay nananatili sa default na 100.
    }
    // Mas mababa ang default ng products: 28 lang ang pinakamataas na stock ngayon,
    // kaya ang 100 ay magma-mark sa lahat ng 49 na produkto bilang low stock.
    chubs_add_col($conn, 'products', 'reorder_level', "INT NOT NULL DEFAULT 10");

    // ---- 3. CHECKOUT / PAYMENT FIELDS SA ORDERS ----
    chubs_add_col($conn, 'orders', 'payment_method', "VARCHAR(20) DEFAULT NULL");   // 'GCash' | 'COD'
    chubs_add_col($conn, 'orders', 'payment_plan',   "VARCHAR(20) DEFAULT NULL");   // 'dp50' | 'full' | 'cod'
    chubs_add_col($conn, 'orders', 'courier',        "VARCHAR(30) DEFAULT NULL");   // 'J&T' | 'Lalamove'
    chubs_add_col($conn, 'orders', 'grand_total',    "DECIMAL(10,2) DEFAULT NULL"); // total_price + shipping_fee

    if (chubs_add_col($conn, 'orders', 'materials_deducted', "TINYINT(1) NOT NULL DEFAULT 0")) {
        // Backfill: na-deduct na sa cart.php ang materials ng lahat ng existing orders.
        // Kailangan itong markahan bago ayusin ang order_type ENUM, kung hindi ay
        // madodoble ang deduction sa admin_orders.php kapag na-move sa "To Ship".
        chubs_try($conn, "UPDATE orders SET materials_deducted = 1");
    }

    // ---- 4. PAYMENTS LEDGER (dito nakatira ang idempotency ng PayMongo) ----
    chubs_try($conn, "CREATE TABLE IF NOT EXISTS payments (
        payment_id          INT AUTO_INCREMENT PRIMARY KEY,
        order_id            INT NOT NULL,
        user_id             INT NOT NULL,
        provider            VARCHAR(20)  NOT NULL DEFAULT 'paymongo',
        mode                VARCHAR(10)  NOT NULL DEFAULT 'test',
        purpose             VARCHAR(20)  NOT NULL DEFAULT 'dp',
        amount              DECIMAL(10,2) NOT NULL,
        amount_centavos     INT NOT NULL,
        checkout_session_id VARCHAR(120) DEFAULT NULL,
        payment_intent_id   VARCHAR(120) DEFAULT NULL,
        provider_payment_id VARCHAR(120) DEFAULT NULL,
        return_token        VARCHAR(64)  DEFAULT NULL,
        status              VARCHAR(20)  NOT NULL DEFAULT 'pending',
        applied             TINYINT(1)   NOT NULL DEFAULT 0,
        raw_response        LONGTEXT     DEFAULT NULL,
        created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        paid_at             DATETIME     DEFAULT NULL,
        UNIQUE KEY uq_pm_payment (provider_payment_id),
        UNIQUE KEY uq_pm_session (checkout_session_id),
        KEY idx_order (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ---- 5. DEFAULT SETTINGS ----
    $seeds = [
        'ship_fee_jnt'           => '100',
        'ship_fee_lalamove'      => '150',
        'ship_fee_pickup'        => '0',
        'ship_fee_walkin'        => '0',
        'dp_percent'             => '50',
        'gcash_enabled'          => '1',
        'cod_enabled'            => '1',
        'paymongo_mode'          => 'test',
        'paymongo_methods'       => 'gcash',
        'paymongo_min_centavos'  => '10000',   // PHP 100.00 minimum ng PayMongo sa e-wallet
        'payment_manual_fallback' => '0',
        'store_pickup_address'   => "Chub's Handicrafts, Las Pinas City",
    ];
    foreach ($seeds as $k => $v) {
        chubs_seed_setting($conn, $k, $v);
    }

    // ---- 6. AYUSIN ANG order_type (schema v2) ----
    // ENUM('T-Shirt','Bouquet') ang column pero 'Custom Bouquet' / 'Custom T-Shirt' /
    // 'Cart Items (N)' ang ini-insert ng code. Walang STRICT_TRANS_TABLES ang install
    // na ito, kaya tahimik na pinuputol ng MySQL ang lahat ng ito papuntang '' -
    // kaya blangko ang pangalan ng order kahit saan.
    //
    // PANSININ: ang $is_bouquet check sa admin_orders.php ay HINDI KAILANMAN naging
    // totoo dahil dito, at iyon lang pala ang pumipigil sa DOBLENG material deduction
    // (nakabawas na ang cart.php). Kaya kasabay nitong inaayos ang materials_deducted
    // guard sa admin_orders.php - hindi pwedeng isa lang sa dalawa.
    try {
        $col = $conn->query("SHOW COLUMNS FROM orders LIKE 'order_type'");
        $row = $col ? $col->fetch_assoc() : null;
        if ($row && stripos($row['Type'], 'enum') !== false) {
            if (chubs_try($conn, "ALTER TABLE orders MODIFY order_type VARCHAR(50) DEFAULT NULL")) {
                chubs_try($conn, "UPDATE orders SET order_type = 'Custom Bouquet'
                                  WHERE (order_type IS NULL OR order_type = '')
                                    AND items_json LIKE '%\"type\":\"flower\"%'");
                chubs_try($conn, "UPDATE orders SET order_type = 'Custom T-Shirt'
                                  WHERE (order_type IS NULL OR order_type = '')
                                    AND items_json LIKE '%shirt_color_info%'");
                chubs_try($conn, "UPDATE orders SET order_type = 'Cart Items'
                                  WHERE order_type IS NULL OR order_type = ''");
            }
        }
    } catch (Throwable $e) {
    }

    // ---- TAPOS: i-record ang version ----
    try {
        $stmt = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('schema_version', ?)
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $v = (string)CHUBS_SCHEMA_VERSION;
        $stmt->bind_param("s", $v);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
    }
}
