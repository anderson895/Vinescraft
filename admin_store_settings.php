<?php
session_start();
require_once 'db_connect.php';
require_once 'paymongo_config.php';

// FETCH: Kunin yung total na unread para sa Sidebar Badge
$unread_total_query = $conn->query("SELECT COUNT(*) as unread FROM messages WHERE sender_type = 'user' AND is_read = 0");
$unread_count = $unread_total_query ? $unread_total_query->fetch_assoc()['unread'] : 0;

// PROTECTION: Admin session check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$message = "";
$error = "";

// --- SAVE SHIPPING & COURIER FEES ---
if (isset($_POST['save_shipping'])) {
    foreach (['ship_fee_jnt', 'ship_fee_lalamove', 'ship_fee_pickup', 'ship_fee_walkin'] as $key) {
        $val = max(0, floatval($_POST[$key] ?? 0));
        setting_set($conn, $key, (string)$val);
    }
    setting_set($conn, 'store_pickup_address', trim($_POST['store_pickup_address'] ?? ''));
    $message = "Shipping and courier fees updated!";
}

// --- SAVE PAYMENT OPTIONS ---
if (isset($_POST['save_payment'])) {
    $dp = intval($_POST['dp_percent'] ?? 50);
    if ($dp < 1 || $dp > 99) {
        $error = "Downpayment percent must be between 1 and 99.";
    } else {
        setting_set($conn, 'dp_percent', (string)$dp);
        setting_set($conn, 'gcash_enabled', isset($_POST['gcash_enabled']) ? '1' : '0');
        setting_set($conn, 'cod_enabled',   isset($_POST['cod_enabled'])   ? '1' : '0');

        if (!isset($_POST['gcash_enabled']) && !isset($_POST['cod_enabled'])) {
            setting_set($conn, 'cod_enabled', '1');
            $error = "At least one payment method must stay enabled &mdash; COD was turned back on.";
        } else {
            $message = "Payment options updated!";
        }
    }
}

// --- SAVE GATEWAY MODE ---
if (isset($_POST['save_gateway'])) {
    $mode = ($_POST['paymongo_mode'] ?? 'test') === 'live' ? 'live' : 'test';
    if ($mode === 'live' && !paymongo_live_allowed()) {
        $error = "LIVE mode is locked in the server config file. Set 'allow_live' to true first.";
    } else {
        setting_set($conn, 'paymongo_mode', $mode);
        $message = "Payment gateway mode: " . strtoupper($mode);
    }
}

$keys = paymongo_keys($conn);
$configured = paymongo_is_configured();
$current_mode = setting_get($conn, 'paymongo_mode', 'test');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store Settings | Vinescraft Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
        :root { --coral: #f18973; --peach: #fce0d8; --text: #444; }

        body { font-family: 'Montserrat', sans-serif; background: #fffafb; margin: 0; display: flex; color: var(--text); }

        /* BADGE CSS PARA SA SIDEBAR */
        .msg-badge { background: #ff4757; color: white; padding: 2px 6px; border-radius: 50px; font-size: 10px; font-weight: 900; margin-left: 5px; vertical-align: top; }

        /* SIDEBAR STYLE */
        .sidebar { width: 260px; background: white; height: 100vh; border-right: 1px solid var(--peach); padding: 30px 20px; position: fixed; overflow-y: auto; }
        .sidebar h1 { color: var(--coral); font-size: 20px; font-weight: 900; text-transform: lowercase; margin-bottom: 40px; }

        .nav-links { display: flex; flex-direction: column; gap: 10px; }
        .nav-links a { text-decoration: none; color: #888; font-size: 13px; font-weight: 700; padding: 12px 20px; border-radius: 10px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: var(--peach); color: var(--coral); }
        .nav-links a.logout { margin-top: 20px; color: #ff4757; }

        /* MAIN CONTENT AREA */
        .main-content { margin-left: 300px; padding: 40px; width: calc(100% - 340px); }
        h2 { font-size: 32px; font-weight: 900; color: var(--coral); margin: 0 0 10px; }

        .back-container { margin-bottom: 25px; }
        .back-btn { text-decoration: none; color: var(--coral); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }

        .content-box { background: white; padding: 35px; border-radius: 20px; border: 1px solid var(--peach); max-width: 620px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); margin-bottom: 25px; }
        .content-box h3 { font-size: 14px; text-transform: uppercase; color: var(--coral); margin: 0 0 6px; letter-spacing: 1px; }
        .content-box .hint { font-size: 12px; color: #aaa; margin: 0 0 25px; line-height: 1.6; }

        .alert { padding: 15px; border-radius: 10px; font-size: 13px; font-weight: 700; margin-bottom: 20px; max-width: 620px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        .form-group { margin-bottom: 20px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        label { display: block; font-size: 11px; font-weight: 900; text-transform: uppercase; color: #aaa; margin-bottom: 8px; letter-spacing: 1px; }

        input[type="number"], input[type="text"], textarea {
            width: 100%; padding: 12px 15px; border: 1px solid var(--peach); border-radius: 12px;
            font-family: 'Montserrat', sans-serif; font-size: 14px; box-sizing: border-box; outline: none; transition: 0.3s;
        }
        input:focus, textarea:focus { border-color: var(--coral); box-shadow: 0 0 0 3px rgba(241, 137, 115, 0.1); }

        .toggle-row { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border: 1px solid var(--peach); border-radius: 12px; margin-bottom: 12px; }
        .toggle-row input { width: 18px; height: 18px; accent-color: var(--coral); cursor: pointer; }
        .toggle-row label { margin: 0; font-size: 13px; color: var(--text); text-transform: none; letter-spacing: 0; font-weight: 700; cursor: pointer; }
        .toggle-row .sub { font-size: 11px; color: #aaa; font-weight: 400; }

        .mode-card { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border: 2px solid var(--peach); border-radius: 14px; margin-bottom: 12px; cursor: pointer; transition: 0.3s; }
        .mode-card.selected { border-color: var(--coral); background: #fff8f6; }
        .mode-card.disabled { opacity: 0.55; cursor: not-allowed; background: #fafafa; }
        .mode-card input { margin-top: 3px; accent-color: var(--coral); }
        .mode-card strong { display: block; font-size: 13px; margin-bottom: 3px; }
        .mode-card span { font-size: 11px; color: #999; line-height: 1.5; }

        .key-pill { display: inline-block; font-family: monospace; font-size: 11px; background: #f5f5f5; color: #777; padding: 3px 9px; border-radius: 6px; }

        .btn-save {
            background: var(--coral); color: white; border: none; padding: 15px 30px; border-radius: 50px;
            font-weight: 900; font-size: 12px; text-transform: uppercase; cursor: pointer; transition: 0.3s; width: 100%; margin-top: 10px;
        }
        .btn-save:hover { background: #e07661; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(241, 137, 115, 0.3); }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
</head>
<body>

<?php include 'admin_sidebar.php' ?>

    <div class="main-content">
        <div class="back-container">
            <a href="admin_dashboard.php" class="back-btn">&larr; Back to Dashboard</a>
        </div>

        <h2>Store Settings</h2>
        <p style="font-size: 13px; color: #888; margin-bottom: 30px;">Shipping fees, payment options, and the shop's payment gateway.</p>

        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- 1. SHIPPING & COURIER FEES -->
        <div class="content-box">
            <h3>Shipping &amp; Courier Fees</h3>
            <p class="hint">These are what customers see at checkout. The selected courier's fee is added to the order total automatically.</p>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>J&amp;T Express (&#8369;)</label>
                        <input type="number" step="0.01" min="0" name="ship_fee_jnt" value="<?php echo setting_money($conn, 'ship_fee_jnt', 100); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Lalamove (&#8369;)</label>
                        <input type="number" step="0.01" min="0" name="ship_fee_lalamove" value="<?php echo setting_money($conn, 'ship_fee_lalamove', 150); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Pick Up (&#8369;)</label>
                        <input type="number" step="0.01" min="0" name="ship_fee_pickup" value="<?php echo setting_money($conn, 'ship_fee_pickup', 0); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Walk In (&#8369;)</label>
                        <input type="number" step="0.01" min="0" name="ship_fee_walkin" value="<?php echo setting_money($conn, 'ship_fee_walkin', 0); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Pick Up / Walk In Address</label>

                    <textarea name="store_pickup_address" rows="2"><?php echo htmlspecialchars(setting_get($conn, 'store_pickup_address', '')); ?></textarea>
                </div>
                <button type="submit" name="save_shipping" class="btn-save">Save Shipping Fees</button>
            </form>
        </div>

        <!-- 2. PAYMENT OPTIONS -->
        <div class="content-box">
            <h3>Payment Options</h3>
            <p class="hint">The downpayment percent applies when a customer chooses partial payment. The remainder is paid on delivery.</p>
            <form method="POST">
                <div class="form-group">
                    <label>Downpayment Percent (%)</label>
                    <input type="number" min="1" max="99" name="dp_percent" value="<?php echo setting_int($conn, 'dp_percent', 50); ?>" required>
                </div>

                <div class="toggle-row">
                    <input type="checkbox" id="gcash_enabled" name="gcash_enabled" value="1" <?php echo setting_bool($conn, 'gcash_enabled', true) ? 'checked' : ''; ?>>
                    <label for="gcash_enabled">Allow GCash (online)<br><span class="sub">Customers can choose downpayment or full payment.</span></label>
                </div>

                <div class="toggle-row">
                    <input type="checkbox" id="cod_enabled" name="cod_enabled" value="1" <?php echo setting_bool($conn, 'cod_enabled', true) ? 'checked' : ''; ?>>
                    <label for="cod_enabled">Allow Cash on Delivery<br><span class="sub">The full amount is paid to the rider on arrival.</span></label>
                </div>

                <button type="submit" name="save_payment" class="btn-save">Save Payment Options</button>
            </form>
        </div>

        <!-- 3. PAYMENT GATEWAY -->
        <div class="content-box">
            <h3>Payment Gateway (PayMongo)</h3>

            <?php if (!$configured): ?>
                <div class="alert alert-error" style="max-width:none;">
                    The config file is not set up yet.<br>
                    Copy <span class="key-pill">paymongo_config.sample.php</span> to
                    <span class="key-pill"><?php echo CHUBS_PAYMONGO_DEFAULT_PATH; ?></span> and put your keys there.
                </div>
            <?php else: ?>
                <p class="hint">
                    Active key: <span class="key-pill"><?php echo htmlspecialchars(paymongo_key_hint($keys['public'] ?? '')); ?></span>
                    &nbsp;&middot;&nbsp; Mode: <strong style="color: <?php echo $keys['mode'] === 'live' ? '#c62828' : '#2e7d32'; ?>;"><?php echo strtoupper($keys['mode']); ?></strong>
                    <?php if ($current_mode === 'live' && $keys['mode'] === 'test'): ?>
                        <br><strong style="color:#c62828;">LIVE is selected but still locked in the server config, so TEST keys are being used.</strong>
                    <?php endif; ?>
                </p>

                <form method="POST">
                    <label class="mode-card <?php echo $current_mode !== 'live' ? 'selected' : ''; ?>">
                        <input type="radio" name="paymongo_mode" value="test" <?php echo $current_mode !== 'live' ? 'checked' : ''; ?>>
                        <div>
                            <strong>TEST mode</strong>
                            <span>No real money moves. Stay here while you are testing checkout.</span>
                        </div>
                    </label>

                    <label class="mode-card <?php echo $current_mode === 'live' ? 'selected' : ''; ?> <?php echo !paymongo_live_allowed() ? 'disabled' : ''; ?>">
                        <input type="radio" name="paymongo_mode" value="live" <?php echo $current_mode === 'live' ? 'checked' : ''; ?> <?php echo !paymongo_live_allowed() ? 'disabled' : ''; ?>>
                        <div>
                            <strong>LIVE mode</strong>
                            <?php if (paymongo_live_allowed()): ?>
                                <span>Customers are charged for real through GCash. Make sure you have tested the whole flow.</span>
                            <?php else: ?>
                                <span>Locked in the server config. To enable, set <code>'allow_live'</code> to <code>true</code> in
                                <?php echo CHUBS_PAYMONGO_DEFAULT_PATH; ?>.</span>
                            <?php endif; ?>
                        </div>
                    </label>

                    <button type="submit" name="save_gateway" class="btn-save">Save Gateway Mode</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // I-highlight ang napiling mode card
        document.querySelectorAll('.mode-card input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', () => {
                document.querySelectorAll('.mode-card').forEach(c => c.classList.remove('selected'));
                if (radio.checked) radio.closest('.mode-card').classList.add('selected');
            });
        });
    </script>

</body>
</html>
