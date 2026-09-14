<?php
session_start();

// I-force natin ang system na ibalik lang ay JSON para hindi matulala ang button
header('Content-Type: application/json'); 
error_reporting(0); 

require_once 'db_connect.php';

// HELPER FUNCTION PARA SA BOUQUET (Wag buburahin)
function getFlowerColor($filename, $flower_name) {
    $base = strtolower(trim($flower_name));
    preg_match('/(\d+)\.png$/i', $filename, $matches);
    $num = isset($matches[1]) ? intval($matches[1]) - 1 : 0;

    $f_colors = [
        'tulip' => ["Red","Pink","Orange","Yellow","Purple","Blue","Green","White"],
        'rose' => ["Red","Pink","Orange","Yellow","Purple","Blue","Green","White","Black"],
        'small sunflower' => ["Yellow","Pink","Purple","Red"],
        'big sunflower' => ["Yellow","Pink","Purple","Red"],
        'lily' => ["Red","Pink","Yellow","Orange","White"],
        'calla lily' => ["Magenta","Maroon","Orange","Yellow","White"],
        'spider lily' => ["Red","Blue","Purple","Pink","Black"],
        'poppy' => ["Red","Orange","Yellow","Pink","White"],
        'iris' => ["Purple","Pink","Orange","Yellow"],
        'cornflower' => ["Blue","Purple","Pink","White Pink","White Purple"], 
        'carnation' => ["Red","Orange","Yellow","Pink","Purple","Green","White"],
        'hyacinth' => ["Red","Yellow","Pink","Purple","White"],
        'hydrangea' => ["Blue","Pink","Purple","White","Orange"],
        'fuchsia' => ["Red","Pink","Purple"],
        'thistle' => ["Pink","Purple","Blue"]
    ];

    if (isset($f_colors[$base]) && isset($f_colors[$base][$num])) {
        $color = $f_colors[$base][$num];
        if (strtolower($color) == 'white pink') return 'Pink';
        if (strtolower($color) == 'white purple') return 'Purple';
        return $color;
    }
    return 'Red'; 
}

$data = json_decode(file_get_contents('php://input'), true);

if ($data && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $order_type = $data['order_type'];
    $size = $data['size'];
    $custom_image = $data['custom_image'];
    $items_json = $data['items_json'];
    $status = 'Pending';

    // 1. I-SAVE ANG ORDER SA DATABASE
    $query = "INSERT INTO orders (user_id, order_type, size, total_price, custom_image, items_json, status) VALUES (?, ?, ?, 0, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isssss", $user_id, $order_type, $size, $custom_image, $items_json, $status);

    if ($stmt->execute()) {
        $order_id = $conn->insert_id;

        // ==========================================
        // DEDUCTION LOGIC PARA SA CUSTOM BOUQUET
        // ==========================================
        if ($order_type === 'Custom Bouquet') {
            $materials_to_deduct = [];

            $base_size = '';
            if (stripos($size, 'Small') !== false) $base_size = 'Small';
            elseif (stripos($size, 'Medium') !== false) $base_size = 'Medium';
            elseif (stripos($size, 'Large') !== false) $base_size = 'Large';

            if ($base_size != '') {
                $req = $conn->query("SELECT material_name, quantity_needed FROM flower_materials WHERE element_name = '$base_size'");
                if ($req) {
                    while($r = $req->fetch_assoc()) {
                        $materials_to_deduct[$r['material_name']] = ($materials_to_deduct[$r['material_name']] ?? 0) + $r['quantity_needed'];
                    }
                }
            }

            $items_array = json_decode($items_json, true);
            if (is_array($items_array)) {
                foreach ($items_array as $item) {
                    if (!isset($item['name']) || (isset($item['type']) && $item['type'] === 'deduction_data')) continue;
                    
                    $fname = strtolower(trim($item['name']));
                    if ($fname == 'lily of valley') $fname = 'lily of the valley';
                    if ($fname == 'eucalyptus') $fname = 'eucalyptus leaf';
                    if ($fname == 'gardenia') $fname = 'gardenia leaf';
                    if ($fname == "babys breath") $fname = "baby's breath";

                    $orig_name = $conn->real_escape_string($item['name']);
                    $conn->query("INSERT INTO order_items (order_id, flower_name, quantity) VALUES ($order_id, '$orig_name', 1)");

                    $esc_name = $conn->real_escape_string($fname);
                    $res_map = $conn->query("SELECT material_name, quantity_needed FROM flower_materials WHERE LOWER(element_name) = '$esc_name'");
                    
                    if ($res_map) {
                        while ($map_row = $res_map->fetch_assoc()) {
                            $mat = $map_row['material_name'];
                            if ($mat === 'Fuzzy Wire [COLOR]') {
                                $color = getFlowerColor($item['file'] ?? '', $item['name']);
                                $mat = "Fuzzy Wire " . ucfirst(strtolower($color));
                            }
                            $materials_to_deduct[$mat] = ($materials_to_deduct[$mat] ?? 0) + $map_row['quantity_needed'];
                        }
                    }
                }
            }

            foreach ($materials_to_deduct as $mat_name => $total_qty) {
                if ($total_qty > 0) {
                    $sql_deduct = "UPDATE materials SET stock = stock - ? WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))";
                    $stmt_deduct = $conn->prepare($sql_deduct);
                    $stmt_deduct->bind_param("ds", $total_qty, $mat_name);
                    $stmt_deduct->execute();
                }
            }
        }

        // ==========================================
        // DEDUCTION LOGIC PARA SA CUSTOM T-SHIRT
        // ==========================================
        elseif ($order_type === 'Custom T-Shirt') {
            
            // DITO NA: Kukunin na niya direkta yung salitang ipinasa mula sa customizer (e.g., "Purple")
            $shirt_color_name = $data['shirt_color'] ?? 'White';
            
            // Bubuo ng material name (e.g. "Shirt Purple")
            $shirt_mat = "Shirt " . ucfirst(strtolower(trim($shirt_color_name)));
            
            $ink_ml = rand(8, 15);

            // Bawasan ang Shirt sa database (1 piece)
            $sql_s = "UPDATE materials SET stock = stock - 1 WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))";
            $stmt_s = $conn->prepare($sql_s);
            $stmt_s->bind_param("s", $shirt_mat);
            $stmt_s->execute();

            // Bawasan ang CMYK Ink sa database gamit ang LIKE para kahit may '(ml)' ay mabasa pa rin
            $sql_i = "UPDATE materials SET stock = stock - ? WHERE LOWER(name) LIKE '%cmyk ink%'";
            $stmt_i = $conn->prepare($sql_i);
            $stmt_i->bind_param("d", $ink_ml);
            $stmt_i->execute();
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Query Failed']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>