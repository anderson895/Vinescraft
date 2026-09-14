<?php
/**
 * Manipis na PayMongo client (Checkout Sessions API).
 *
 * BAKIT CHECKOUT SESSIONS AT HINDI SOURCES API:
 * Nasa localhost ang site na ito, kaya HINDI kayang abutin ng PayMongo webhooks.
 * Sa Sources API, dalawang hakbang ang pagsingil (source -> chargeable -> create
 * payment). Kapag hindi nakabalik ang customer, hindi mo magagawa ang pangalawang
 * hakbang at mare-reverse ang bayad - nawawala ang pera.
 * Sa Checkout Sessions, awtomatiko nang nasisingil ang bayad. Kapag hindi nakabalik
 * ang customer, confirmation lang ang na-delay - nare-recover ito sa pag-poll.
 */

require_once __DIR__ . '/paymongo_config.php';

/**
 * Isang request sa PayMongo API.
 * Nagbabalik ng [http_status, decoded_array].
 */
function pm_request($conn, $method, $path, $payload = null) {
    $keys = paymongo_keys($conn);
    if ($keys === false) {
        return [0, ['errors' => [['detail' => 'Hindi pa naka-setup ang PayMongo config file.']]]];
    }

    $url = rtrim($keys['base_url'], '/') . '/' . ltrim($path, '/');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
        // Huwag i-off ito. Ang CA bundle ay naka-set na sa php.ini ng XAMPP.
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . base64_encode($keys['secret'] . ':'),
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['data' => ['attributes' => $payload]]));
    }

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return [0, ['errors' => [['detail' => 'Hindi maabot ang PayMongo: ' . $err]]]];
    }

    $decoded = json_decode($raw, true);
    return [$code, is_array($decoded) ? $decoded : ['raw' => $raw]];
}

/** Piso -> centavos. Integer ang tinatanggap ng PayMongo. */
function pm_pesos_to_centavos($pesos) {
    return (int) round(floatval($pesos) * 100);
}

/** Base URL ng site para sa success/cancel redirects (hal. http://localhost/Chubs/Chubs). */
function pm_site_base_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '/')), '/');
    return $scheme . '://' . $host . $dir;
}

/**
 * Gumawa ng Checkout Session. Ang halaga ay galing lang sa server -
 * walang bahagi nito ang dumadaan sa browser.
 */
function pm_create_checkout_session($conn, $opts) {
    $methods = array_values(array_filter(array_map('trim',
        explode(',', setting_get($conn, 'paymongo_methods', 'gcash')))));
    if (empty($methods)) $methods = ['gcash'];

    $payload = [
        'line_items' => [[
            'name'     => $opts['name'],
            'amount'   => (int)$opts['amount_centavos'],
            'currency' => 'PHP',
            'quantity' => 1,
        ]],
        'payment_method_types' => $methods,
        'description'          => $opts['description'],
        'reference_number'     => $opts['reference'],
        'success_url'          => $opts['success_url'],
        'cancel_url'           => $opts['cancel_url'],
        'send_email_receipt'   => false,
        'show_description'     => true,
        'show_line_items'      => true,
    ];

    return pm_request($conn, 'POST', '/checkout_sessions', $payload);
}

function pm_get_checkout_session($conn, $session_id) {
    return pm_request($conn, 'GET', '/checkout_sessions/' . urlencode($session_id));
}

/**
 * Hanapin ang unang BAYAD na payment sa loob ng checkout session.
 * Nagbabalik ng null kapag wala pa.
 */
function pm_find_paid_payment($session_response) {
    $payments = $session_response['data']['attributes']['payments'] ?? [];
    foreach ($payments as $p) {
        if (($p['attributes']['status'] ?? '') === 'paid') {
            return $p;
        }
    }
    return null;
}

/** Nababasang error message mula sa PayMongo response. */
function pm_error_message($response) {
    if (!empty($response['errors'][0]['detail'])) {
        return $response['errors'][0]['detail'];
    }
    return 'Hindi inaasahang sagot mula sa payment gateway.';
}
