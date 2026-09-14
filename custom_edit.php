<?php
/**
 * Pagbuo ng EDIT_DATA para sa re-edit ng custom design mula sa cart.
 *
 * Ang customizer ay ibabalik ang design mula sa items_json na naka-save sa
 * $_SESSION['cart']. Dito sa server ginagawa ang paghihiwalay ng meta entries
 * at ang pag-parse ng size string, para simple na lang ang JavaScript.
 */

require_once __DIR__ . '/inventory_helpers.php';

/**
 * Isulat ang design preview sa disk imbes na dalhin ito bilang base64.
 *
 * BAKIT: 1 MB lang ang max_allowed_packet ng MySQL dito, pero ang custom t-shirt ay
 * may APAT na html2canvas snapshot (~1.5-2 MB lahat), kaya hindi talaga kayang
 * i-INSERT ang custom_image - kaya hindi pa kailanman gumana ang t-shirt checkout.
 * Ang bouquet naman ay ~460 KB kada order, na nagpapalaki rin sa session.
 *
 * Pareho pa rin ang hugis ng ibinabalik (single string, o JSON na may f/b/ls/rs),
 * path na lang imbes na data URL - kaya gumagana pa rin ang lahat ng <img src>.
 */
function persist_design_image($image_data) {
    $dir = __DIR__ . '/uploads/custom_designs';
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
        return $image_data; // Bumalik sa dating paraan kung hindi magawa ang folder
    }

    $write_one = function ($data_url) use ($dir) {
        if (!is_string($data_url) || strpos($data_url, 'data:image') !== 0) {
            return $data_url; // path na - hayaan
        }
        if (!preg_match('/^data:image\/(png|jpe?g|webp);base64,(.+)$/s', $data_url, $m)) {
            return $data_url;
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false) return $data_url;

        $ext  = $m[1] === 'jpg' ? 'jpeg' : $m[1];
        $name = 'design_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (@file_put_contents($dir . '/' . $name, $bytes) === false) {
            return $data_url;
        }
        return 'uploads/custom_designs/' . $name;
    };

    // T-shirt: JSON na may apat na view
    if (is_string($image_data) && isset($image_data[0]) && $image_data[0] === '{') {
        $views = json_decode($image_data, true);
        if (is_array($views)) {
            foreach ($views as $k => $v) {
                $views[$k] = $write_one($v);
            }
            return json_encode($views);
        }
    }

    // Bouquet: isang larawan lang
    return $write_one($image_data);
}

/** Burahin ang mga lumang design file kapag pinalitan ang isang cart item. */
function delete_design_images($image_data) {
    $paths = [];
    if (is_string($image_data) && isset($image_data[0]) && $image_data[0] === '{') {
        $decoded = json_decode($image_data, true);
        if (is_array($decoded)) $paths = array_values($decoded);
    } else {
        $paths = [$image_data];
    }

    foreach ($paths as $p) {
        if (is_string($p) && strpos($p, 'uploads/custom_designs/') === 0 && strpos($p, '..') === false) {
            @unlink(__DIR__ . '/' . $p);
        }
    }
}

/** Mga kulay ng shirt - fallback kapag walang hex ang naka-save (lumang cart item). */
function shirt_color_map() {
    return [
        'Red' => '#FF0000', 'Orange' => '#FFA500', 'Yellow' => '#FFFF00',
        'Green' => '#008000', 'Blue' => '#0000FF', 'Purple' => '#800080',
        'Pink' => '#FFC0CB', 'Brown' => '#A52A2A', 'White' => '#FFFFFF', 'Black' => '#000000',
    ];
}

/**
 * Size string -> [sizeKey, customInches].
 *
 * Tinatanggal ang trailing na "(...)" imbes na maghanap ng substring. Mahalaga ito:
 * ang stripos($s, 'Large') ay tumatama sa "Extra Large" kaya magiging "Large" ang XL.
 *   'Small (9")'                          -> ['Small', null]
 *   'Extra Large (21"x29")'               -> ['Extra Large', null]
 *   'Large - Custom Request (25 inches)'  -> ['Custom', 25]
 */
function parse_size_key($size_str) {
    if (preg_match('/Custom Request\s*\(\s*([\d.]+)\s*inch/i', $size_str, $m)) {
        return ['Custom', floatval($m[1])];
    }
    $base = trim(preg_replace('/\s*\([^)]*\)\s*$/', '', (string)$size_str));
    $base = trim(preg_replace('/\s*-\s*$/', '', $base));
    return [$base, null];
}

/**
 * Buuin ang EDIT_DATA mula sa isang cart item.
 * Nagbabalik ng null kapag hindi valid ang key o hindi tugma ang uri.
 */
function build_edit_data($cart_key, $expected_type) {
    if (!is_string($cart_key) || strpos($cart_key, 'custom_') !== 0) return null;
    if (empty($_SESSION['cart'][$cart_key])) return null;

    $item = $_SESSION['cart'][$cart_key];
    if (empty($item['is_custom']) || ($item['order_type'] ?? '') !== $expected_type) return null;

    $raw = json_decode($item['items_json'] ?? '[]', true);
    if (!is_array($raw)) $raw = [];

    $design_items = [];
    $wrapper      = null;
    $shirt_hex    = null;
    $shirt_name   = $item['shirt_color'] ?? 'White';

    foreach ($raw as $entry) {
        if (!is_array($entry)) continue;

        if (($entry['id'] ?? '') === 'wrapper_info') {
            $wrapper = ['back' => $entry['back'] ?? null, 'front' => $entry['front'] ?? null];
            continue;
        }
        if (($entry['id'] ?? '') === 'shirt_color_info') {
            if (!empty($entry['hex'])) $shirt_hex = $entry['hex'];
            if (!empty($entry['name'])) $shirt_name = trim(str_replace('Shirt Color:', '', $entry['name']));
            continue;
        }
        // Ang lumang deduction_data ay dapat matanggal - kino-compute ulit ito
        // ng calculateMaterials() sa susunod na save.
        if (is_meta_item($entry)) continue;

        $design_items[] = $entry;
    }

    if ($shirt_hex === null) {
        $map = shirt_color_map();
        $shirt_hex = $map[$shirt_name] ?? '#FFFFFF';
    }

    [$size_key, $custom_inches] = parse_size_key($item['size'] ?? '');

    return [
        'cartId'       => $cart_key,
        'sizeKey'      => $size_key,
        'customInches' => $custom_inches,
        'wrapper'      => $wrapper,
        'items'        => $design_items,
        'shirtColor'   => $shirt_hex,
        'shirtName'    => $shirt_name,
    ];
}
