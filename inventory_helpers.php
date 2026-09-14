<?php
/**
 * Shared na inventory logic: re-order level at stock status.
 *
 * Rule:
 *   stock <= 0                          -> Out of Stock
 *   0 < stock <= reorder_level          -> Low Stock
 *   reorder_level = 0                   -> naka-mute ang alert para sa item na yun
 */

function stock_state($stock, $reorder_level) {
    $s = floatval($stock);
    $r = floatval($reorder_level);
    if ($s <= 0) return 'out';
    if ($r > 0 && $s <= $r) return 'low';
    return 'ok';
}

/**
 * Malinis na pagpapakita ng stock number.
 *
 * DECIMAL(10,2) ang stock at kailangan talaga itong decimal: ang ilang materyales
 * ay kinukonsumo nang paunti-unti (Ribbon 0.01-0.06 kada bouquet, Floral Tape 0.01
 * at Glue Stick 0.12 kada bulaklak). Kung integer ito, mababawasan sila ng 0 o ng 1
 * imbes na 0.12 - mali ang inventory.
 *
 * Pero walang saysay ang ".00" sa mga nabibilang na item, kaya tinatanggal natin ito
 * kapag buo ang numero: 2000.00 -> "2000", pero 56.66 -> "56.66".
 * Walang thousand separator para magamit din ito sa <input type="number">.
 */
function fmt_stock($value) {
    $s = number_format(floatval($value), 2, '.', '');
    return rtrim(rtrim($s, '0'), '.') ?: '0';
}

function stock_badge($state) {
    switch ($state) {
        case 'out': return ['bg' => '#ffebee', 'color' => '#c62828', 'text' => 'Out of Stock'];
        case 'low': return ['bg' => '#fff2f2', 'color' => '#ff4757', 'text' => 'Low Stock'];
        default:    return ['bg' => '#e9f9f0', 'color' => '#1e9c5a', 'text' => 'In Stock'];
    }
}

/**
 * Lahat ng products at materials na nasa o mababa pa sa re-order level nila.
 * Naka-una ang out of stock, tapos yung pinakamababa ang stock.
 */
function get_low_stock_items($conn, $limit = 0) {
    $items = [];
    $sql = "SELECT name, stock, reorder_level, category, 'Product' AS type FROM products
             WHERE stock <= 0 OR (reorder_level > 0 AND stock <= reorder_level)
            UNION ALL
            SELECT name, stock, reorder_level, category, 'Material' AS type FROM materials
             WHERE stock <= 0 OR (reorder_level > 0 AND stock <= reorder_level)
            ORDER BY (stock <= 0) DESC, stock ASC";
    if ($limit > 0) $sql .= " LIMIT " . intval($limit);

    try {
        $res = $conn->query($sql);
        if ($res) while ($r = $res->fetch_assoc()) $items[] = $r;
    } catch (Throwable $e) {
        // Kung wala pa ang reorder_level column, walang alerts muna.
    }
    return $items;
}

/** Bilang ng low/out items - para sa sidebar badges at dashboard banner. */
function get_low_stock_counts($conn) {
    $counts = ['products_low' => 0, 'products_out' => 0, 'materials_low' => 0, 'materials_out' => 0];

    $sql = "SELECT 'products' AS src,
                   SUM(stock <= 0) AS n_out,
                   SUM(stock > 0 AND reorder_level > 0 AND stock <= reorder_level) AS n_low
              FROM products
            UNION ALL
            SELECT 'materials',
                   SUM(stock <= 0),
                   SUM(stock > 0 AND reorder_level > 0 AND stock <= reorder_level)
              FROM materials";

    try {
        $res = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $counts[$r['src'] . '_out'] = intval($r['n_out']);
                $counts[$r['src'] . '_low'] = intval($r['n_low']);
            }
        }
    } catch (Throwable $e) {
        // Balik ng zeroes - walang badge na ipapakita.
    }
    return $counts;
}

/**
 * Ang items_json ng custom orders ay may mga "meta" entries na hindi tunay na
 * flower/material: wrapper_info, custom_size_info, shirt_color_info, deduction_data.
 *
 * LAHAT ng material deduction loop ay dapat mag-skip nito, kung hindi ay mai-insert
 * ang mga ito sa order_items bilang bulaklak at hahanapin sa flower_materials.
 */
function is_meta_item($item) {
    if (!is_array($item)) return true;
    if (isset($item['type']) && in_array($item['type'], ['info', 'deduction_data'], true)) return true;
    if (isset($item['id']) && in_array($item['id'], ['wrapper_info', 'custom_size_info', 'shirt_color_info'], true)) return true;
    return false;
}

/**
 * Para sa mga material deduction loop, na naghahanap ng recipe base sa PANGALAN.
 *
 * Hiwalay ito sa is_meta_item() sa isang mahalagang dahilan: ang mga item ng
 * t-shirt ay walang 'name' - ang text ay may 'content' at ang graphic ay may 'src'.
 * Kung "walang pangalan = metadata" ang panuntunan, mabubura ang buong disenyo
 * ng t-shirt kapag ni-restore ito mula sa cart.
 */
function is_deductible_item($item) {
    if (is_meta_item($item)) return false;
    return isset($item['name']) && trim((string)$item['name']) !== '';
}
