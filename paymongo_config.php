<?php
/**
 * Hinahanap at binabasa ang PayMongo credentials mula sa LABAS ng webroot.
 *
 * Sinadyang wala dito ang mismong keys. Ang C:/xampp/htdocs ang DocumentRoot,
 * kaya kahit anong file sa loob nito ay pwedeng buksan sa browser.
 *
 * Setup (isang beses lang):
 *   Kopyahin ang paymongo_config.sample.php papuntang
 *   C:\xampp\secure_config\chubs_paymongo.php at ilagay dun ang totoong keys.
 */

define('CHUBS_PAYMONGO_DEFAULT_PATH', 'C:/xampp/secure_config/chubs_paymongo.php');

function paymongo_config() {
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $candidates = [];
    if (getenv('CHUBS_PAYMONGO_CONFIG')) {
        $candidates[] = getenv('CHUBS_PAYMONGO_CONFIG');
    }
    $candidates[] = CHUBS_PAYMONGO_DEFAULT_PATH;
    // Kung nailipat ang buong XAMPP sa ibang drive, subukan pa rin ang ../../secure_config
    $candidates[] = dirname(__DIR__, 3) . '/secure_config/chubs_paymongo.php';

    foreach ($candidates as $path) {
        if ($path && is_file($path) && is_readable($path)) {
            $loaded = require $path;
            if (is_array($loaded) && isset($loaded['test']['secret'])) {
                $cfg = $loaded;
                return $cfg;
            }
        }
    }

    $cfg = false;
    return $cfg;
}

function paymongo_is_configured() {
    return paymongo_config() !== false;
}

/** Puwedeng gamitin ang LIVE keys? Kailangang naka-true ang latch sa config file. */
function paymongo_live_allowed() {
    $cfg = paymongo_config();
    return $cfg !== false && !empty($cfg['allow_live']);
}

/**
 * Ang aktwal na gagamiting keys. Kahit ano pang naka-set sa admin, TEST pa rin
 * ang ibabalik habang hindi naka-allow_live ang config file - safety latch ito.
 */
function paymongo_keys($conn) {
    $cfg = paymongo_config();
    if ($cfg === false) return false;

    $mode = setting_get($conn, 'paymongo_mode', 'test') === 'live' ? 'live' : 'test';
    if ($mode === 'live' && !paymongo_live_allowed()) {
        $mode = 'test';
    }

    if (empty($cfg[$mode]['secret'])) return false;

    return [
        'mode'     => $mode,
        'secret'   => $cfg[$mode]['secret'],
        'public'   => $cfg[$mode]['public'] ?? '',
        'base_url' => $cfg['base_url'] ?? 'https://api.paymongo.com/v1',
        'salt'     => $cfg['return_salt'] ?? 'chubs-default-salt',
    ];
}

/** Huling 4 na character lang - huwag ilabas ang buong secret sa UI. */
function paymongo_key_hint($key) {
    if (!$key) return 'wala';
    $prefix = substr($key, 0, strrpos($key, '_') + 1);
    return $prefix . '****' . substr($key, -4);
}
