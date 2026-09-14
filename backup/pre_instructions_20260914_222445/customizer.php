<?php
session_start();
require_once 'db_connect.php';

//LOGIN CHECK
if (!isset($_SESSION['user_logged_in'])) {
    header("Location: login.php");
    exit();
}

//DYNAMIC FLOWERS LOGIC
$df_array = [];
$check_df = $conn->query("SHOW TABLES LIKE 'dynamic_flowers'");
if ($check_df && $check_df->num_rows > 0) {
    $dyn_flowers = $conn->query("SELECT * FROM dynamic_flowers");
    if ($dyn_flowers && $dyn_flowers->num_rows > 0) {
        while($row = $dyn_flowers->fetch_assoc()) {
            $df_array[] = $row;
        }
    }



    
}

//DYNAMIC WRAPPERS LOGIC
$dw_array = [];
$check_dw = $conn->query("SHOW TABLES LIKE 'dynamic_wrappers'");
if ($check_dw && $check_dw->num_rows > 0) {
    $dyn_wrappers = $conn->query("SELECT * FROM dynamic_wrappers");
    if ($dyn_wrappers && $dyn_wrappers->num_rows > 0) {
        while($row = $dyn_wrappers->fetch_assoc()) {
            $dw_array[] = $row;
        }
    }
}

//DYNAMIC RECIPES LOGIC
$dyn_recipes = [];
$check_br = $conn->query("SHOW TABLES LIKE 'bouquet_recipes'");
if ($check_br && $check_br->num_rows > 0) {
    $recipe_res = $conn->query("SELECT element_name, material_name, qty FROM bouquet_recipes");
    if ($recipe_res && $recipe_res->num_rows > 0) {
        while($row = $recipe_res->fetch_assoc()) {
            $dyn_recipes[strtolower(trim($row['element_name']))][] = [
                'mat' => $row['material_name'],
                'qty' => $row['qty']
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <title>Bouquet Designer | Vinescraft</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');

        :root {
            --coral: #f18973;
            --peach: #fce0d8;
            --text-dark: #444;
            --grey-light: #f9f9f9;
        }

        body { font-family: 'Montserrat', sans-serif; background: #fffafb; margin: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        
        /* NAV NOTIFICATION BADGE & TOAST ALERTS */
        .nav-badge { background: #ff4757; color: white; padding: 2px 6px; border-radius: 50px; font-size: 8px; font-weight: 900; margin-left: 3px; vertical-align: super; box-shadow: 0 0 5px rgba(255, 71, 87, 0.5); }
        .status-toast-wrapper { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
        .status-toast { background: white; border-left: 5px solid #2ed573; padding: 15px 25px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 15px; animation: slideDown 0.5s ease forwards; transition: opacity 0.5s ease; min-width: 300px; pointer-events: auto; }
        .toast-icon { font-size: 22px; }
        .toast-content { font-size: 13px; font-weight: 700; color: #444; flex-grow: 1; line-height: 1.4; }
        .toast-close { cursor: pointer; font-size: 18px; color: #aaa; font-weight: bold; }
        .toast-close:hover { color: #f18973; }
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* NAVIGATION */
        .top-nav { background: var(--coral); padding: 12px 5%; display: flex; justify-content: space-between; align-items: center; color: white; z-index: 1001; }
        .top-nav a { color: white; text-decoration: none; font-size: 10px; font-weight: 900; text-transform: uppercase; margin-left: 15px; letter-spacing: 1px; }

        .main-layout { display: flex; flex-grow: 1; overflow: hidden; }

        /* SIDEBAR */
        .sidebar { width: 340px; background: white; border-right: 1px solid var(--peach); display: flex; flex-direction: column; z-index: 100; box-shadow: 5px 0 15px rgba(0,0,0,0.02); }
        .sidebar-header { padding: 20px; background: var(--grey-light); border-bottom: 1px solid var(--peach); color: var(--coral); font-weight: 900; font-size: 12px; letter-spacing: 1px; }
        
        .category-tab { padding: 15px 20px; border-bottom: 1px solid #f9f9f9; cursor: pointer; font-weight: 700; color: var(--text-dark); font-size: 11px; transition: 0.3s; text-transform: lowercase; }
        
        .panel { display: none; padding: 20px; flex-grow: 1; overflow-y: auto; background: #fffafb; }
        .panel.active { display: block; }

        .size-btn { width: 100%; padding: 15px; margin-bottom: 10px; border: 2px solid var(--peach); background: white; border-radius: 50px; cursor: pointer; font-weight: 700; font-size: 11px; }

        .asset-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .asset-item { text-align: center; font-size: 10px; cursor: pointer; padding: 10px; background: white; border: 1px solid var(--peach); border-radius: 12px; transition: 0.3s; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .asset-item img { width: 100%; height: 60px; object-fit: contain; }

        /* CANVAS & LAYERING */
        .editor-container { flex-grow: 1; position: relative; display: flex; align-items: center; justify-content: center; background: var(--grey-light); }
        #canvas { width: 450px; height: 550px; background: white; border: 2px solid var(--peach); position: relative; overflow: hidden; border-radius: 20px; box-shadow: 0 15px 40px rgba(241, 137, 115, 0.1); }
        
        #bouquetScaleWrapper { width: 100%; height: 100%; position: absolute; bottom: 0; left: 0; transform-origin: center bottom; }
        
        /* Z-INDEX STACK */
        .bouq-layer { position: absolute; width: 100%; height: 100%; bottom: 0; left: 0; pointer-events: none; z-index: 1; background-size: contain; background-position: center bottom; background-repeat: no-repeat; }
        .items-layer { position: absolute; width: 100%; height: 100%; top: 0; left: 0; pointer-events: auto; z-index: 10; }
        .wrapper-top-layer { position: absolute; width: 100%; height: 100%; bottom: 0; left: 0; pointer-events: none; z-index: 100; background-size: contain; background-position: center bottom; background-repeat: no-repeat; }
        
        .canvas-item { position: absolute; cursor: grab; transform-origin: center center; user-select: none; caret-color: transparent !important; }
        .canvas-item.active { outline: 2px dashed var(--coral); filter: brightness(1.05); }

        /* FLOATING UI */
        #summary-box { position: absolute; bottom: 25px; left: 25px; background: white; padding: 20px; border-radius: 20px; border: 2px solid var(--peach); min-width: 250px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); z-index: 200; }
        .right-tools { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); display: flex; flex-direction: column; gap: 12px; background: white; padding: 20px 10px; border-radius: 50px; border: 2px solid var(--peach); z-index: 500; align-items: center; box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        
        .tool-btn { background: none; border: none; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; font-size: 11px !important; color: var(--coral); font-weight: 700; text-transform: uppercase; min-width: 78px; line-height: 1; }
        .tool-btn .emoji { font-size: 22px; display: block; margin-bottom: 2px; }

        .dpad-btn { width: 24px; height: 24px; background: var(--peach); border: none; border-radius: 50%; color: var(--coral); cursor: pointer; font-size: 10px; font-weight: bold; }
        
        #request-btn { background: var(--coral); color: white; border: none; padding: 15px; width: 100%; border-radius: 50px; font-weight: 900; cursor: pointer; text-transform: uppercase; margin-top: 15px; transition: 0.3s; }
        #request-btn:disabled { background: #ccc; cursor: not-allowed; opacity: 0.7; }

        .modal-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; display: none; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 90%; max-width: 450px; position: relative; }
        .floating-slider-popup { position: absolute; display: none; flex-direction: column; background: white; padding: 15px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); z-index: 1001; border: 2px solid var(--peach); align-items: center; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
    <style>
        body { background: radial-gradient(circle at 8% 12%, rgba(244, 125, 168, 0.14), transparent 260px), radial-gradient(circle at 88% 82%, rgba(127, 155, 115, 0.11), transparent 260px), linear-gradient(180deg, #fffdfd 0%, #fff5f8 100%) !important; }
        .top-nav { padding: 14px 4% !important; }
        .top-nav a { font-size: 14px !important; padding: 10px 12px !important; }
        .sidebar { width: 370px !important; }
        .sidebar-header { background: linear-gradient(135deg, #fff, #fff3f7) !important; color: #b84c76 !important; font-size: 21px !important; letter-spacing: 1.1px; padding: 24px !important; }
        .category-tab { color: #5a4b51 !important; font-size: 18px !important; line-height: 1.25; letter-spacing: 0.4px; padding: 18px 24px !important; text-transform: none !important; }
        .category-tab:hover { background: #fff3f7; color: var(--coral) !important; }
        .panel { padding: 24px !important; }
        .size-btn, #request-btn { min-height: 58px; font-size: 18px !important; letter-spacing: 0.3px; }
        .asset-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 14px !important; }
        .asset-item { border-radius: 14px !important; font-size: 17px !important; font-weight: 800; line-height: 1.3; padding: 12px !important; box-shadow: 0 8px 18px rgba(137, 91, 108, 0.08); }
        .asset-item:hover { border-color: var(--coral) !important; transform: translateY(-2px); }
        .asset-item img { height: 78px !important; margin-bottom: 8px; }
        .editor-container { background: linear-gradient(135deg, rgba(255,255,255,0.62), rgba(255,243,247,0.82)), radial-gradient(circle at 80% 14%, rgba(244,125,168,0.1), transparent 230px) !important; }
        #canvas { border-radius: 18px !important; box-shadow: 0 24px 60px rgba(137, 91, 108, 0.18) !important; }
        #summary-box { border-radius: 16px !important; min-width: 280px !important; box-shadow: 0 18px 42px rgba(137, 91, 108, 0.14) !important; }
        #sum-size { font-size: 20px !important; }
        #sum-items, #sum-status, #summary-box label { font-size: 15px !important; }
        #sum-price { font-size: 34px !important; }
        .right-tools { border-radius: 22px !important; padding: 16px 12px !important; gap: 10px !important; }
        .dpad-btn { width: 36px !important; height: 36px !important; font-size: 16px !important; }
        .modal-content { border-radius: 18px !important; }
        .modal-content h3 { font-size: 22px !important; }
        .floating-slider-popup label, .floating-slider-popup button { font-size: 14px !important; }
    </style>
</head>
<body>

<?php include 'notifications.php'; ?>
<?php include 'navbar.php'; ?>

<div class="main-layout">
    <div class="sidebar">
        <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center; gap: 15px;">
    <span style="flex: 1; line-height: 1.3;">Customized Bouquet Maker</span>
    <button onclick="document.getElementById('guideModal').style.display='flex'" style="flex-shrink: 0; background: var(--coral); color: white; border: none; padding: 8px 12px; border-radius: 8px; font-size: 10px; font-weight: 900; cursor: pointer; text-transform: uppercase; transition: 0.3s; box-shadow: 0 4px 10px rgba(241,137,115,0.3);">ⓘ Guide</button>
</div>
        
        <div class="category-tab" onclick="showPanel('size-panel')">1. Bouquet size</div>
        <div id="size-panel" class="panel active">
            <button class="size-btn" onclick="setSize('Small', 149)">Small (9") — ₱149</button>
            <button class="size-btn" onclick="setSize('Medium', 299)">Medium (18") — ₱299</button>
            <button class="size-btn" onclick="setSize('Large', 499)">Large (22") — ₱499</button>
            <button class="size-btn" onclick="setSize('Custom', '699+')">Custom Size Request — ₱699+</button>
            
            <div id="custom-size-input-div" style="display:none; margin-top: 15px;">
                <label style="font-size: 10px; font-weight: 900; color: var(--coral); text-transform: uppercase;">Custom Bouquet Size Measurement Request (in inches):</label>
                <input type="number" id="customSizeInput" placeholder="e.g., 25" min="1" oninput="updateSummary()" style="width: 100%; padding: 15px; border: 2px solid var(--peach); border-radius: 50px; outline: none; margin-top: 5px; box-sizing: border-box; font-family: inherit;">
            </div>
        </div>

        <div class="category-tab" onclick="showPanel('wrapper-panel')">2. Wrapper style</div>
        <div id="wrapper-panel" class="panel"><div id="wrapper-list" class="asset-grid"></div></div>

        <div class="category-tab" onclick="showPanel('flower-panel')">3. Wire flowers</div>
        <div id="flower-panel" class="panel">
            <div class="asset-grid" id="flower-list"></div>
        </div>

        <div class="category-tab" onclick="showPanel('filler-panel')">4. Fillers & leaves</div>
        <div id="filler-panel" class="panel"><div id="filler-list" class="asset-grid"></div></div>
    </div>

    <div class="editor-container">
        <div id="summary-box">
            <div id="sum-size" style="font-size: 13px; font-weight: 700;">Size: None</div>
            <div id="sum-items" style="font-size: 10px; color: #888; margin: 5px 0;">Items: 0/0 Flower | 0/0 Filler | 0/0 Leaf</div>
            <div id="sum-status" style="font-size: 9px; font-weight: bold; color: red;">Status: Select Size</div>
            <div id="sum-price" style="color: var(--coral); font-size: 20px; font-weight: 900; margin: 5px 0;">₱0.00</div>
            
            <label style="display: flex; align-items: center; gap: 8px; font-size: 10px; color: #777; cursor: pointer; margin-bottom: 10px;">
                <input type="checkbox" id="termsCheckbox" onchange="updateSummary()">
                Agree to <span onclick="toggleTermsModal(true)" style="text-decoration: underline; color: var(--coral); font-weight: bold;">Terms</span>
            </label>
            <button id="request-btn" disabled onclick="submitRequest()">Save to Cart</button>
        </div>

        <div id="canvas">
            <div id="bouquetScaleWrapper">
                <div class="bouq-layer" id="layerBack"></div>
                <div class="items-layer" id="layerItems"></div>
                <div class="wrapper-top-layer" id="layerFront"></div>
            </div>
            <div id="toolPopup" class="floating-slider-popup">
                <label id="popupLabel" style="font-size: 10px; font-weight: 900; color: var(--coral); margin-bottom: 5px;">ADJUST</label>
                <input type="range" id="popupSlider" oninput="updateItemProp(this.value)" style="accent-color: var(--coral);">
                <button onclick="closePopup()" style="margin-top:10px; font-size:9px; cursor:pointer; color:#888; border:none; background:none; text-decoration:underline;">DONE</button>
            </div>
        </div>

        <div class="right-tools">
            <div class="dpad-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 3px;">
                <div></div><button class="dpad-btn" onclick="toolMove(0,-5)">▲</button><div></div>
                <button class="dpad-btn" onclick="toolMove(-5,0)">◀</button><div style="font-size:6px; text-align:center; color:var(--coral); line-height:24px;">MOVE</div><button class="dpad-btn" onclick="toolMove(5,0)">▶</button>
                <div></div><button class="dpad-btn" onclick="toolMove(0,5)">▼</button><div></div>
            </div>
            <button class="tool-btn" onclick="openLayersModal()"><svg class="emoji" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 12 12 17 22 12"></polyline><polyline points="2 17 12 22 22 17"></polyline></svg><span>LAYERS</span></button>
            <button class="tool-btn" onclick="toolZIndex(1)"><svg class="emoji" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg><span>FRONT</span></button>
            <button class="tool-btn" onclick="toolZIndex(-1)"><svg class="emoji" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg><span>BACK</span></button>
            <button class="tool-btn" onclick="togglePopup('rotate')"><svg class="emoji" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg><span>ROTATE</span></button>
            <button class="tool-btn" onclick="togglePopup('bend')"><svg class="emoji" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12c4-8 8-8 16 0"></path></svg><span>BEND</span></button>
            <button class="tool-btn" onclick="manipulate('flip')"><svg class="emoji" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="22"></line><polyline points="8 6 2 12 8 18"></polyline><polyline points="16 6 22 12 16 18"></polyline></svg><span>FLIP</span></button>
            <button class="tool-btn" onclick="removeItem()" style="color:#ff4444; margin-top:10px;"><svg class="emoji" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg><span>DELETE</span></button>
        </div>
    </div>
</div>

<div id="color-modal-box" class="modal-bg" onclick="closeColorModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
        <span onclick="closeColorModal()" style="float:right; cursor:pointer; font-weight:bold; color:var(--coral);">✕</span>
        <h3 id="modal-flower-name" style="color: var(--coral); margin-top:0; font-size:14px; text-transform:uppercase;">Select Color</h3>
        <div id="color-options" class="asset-grid"></div>
    </div>
</div>

<div id="termsModal" class="modal-bg" onclick="toggleTermsModal(false)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <h3 style="color: var(--coral); margin-top:0;">Terms & Conditions</h3>
        <div style="font-size:11px; color:#666; line-height:1.6;">
            <p>1. Ordering and Customization
• Order Accuracy: Customers are responsible for providing accurate details for customized products and craft products, including flower types, colors, and specific arrangements.

• Size Specifications: Product dimensions (in inches/cm) provided on the site are estimates; slight variations may occur due to the handcrafted nature of our floral designs.

• Material Availability: Since we work with specific craft materials we reserve the right to make minor substitutions that maintain the overall aesthetic and quality of your order.
</p>
            <p>2. Payment and Down Payments
• Down Payment Requirement: To confirm your order and reduce the risk of cancellation, a partial down payment is required before we begin processing.

• Final Payment: The remaining balance must be settled according to the agreed-upon workflow before delivery or pickup.

• Dynamic Pricing: Costs for large-scale or non-standard customizations are calculated dynamically based on materials, dimensions, and labor complexity.
</p>
            <p>3. Cancellation and Refunds
• Cancellation Policy: Because our products are perishable or custom-made, cancellations made after the down payment has been processed may not be eligible for a full refund.

• Damage Claims: Any issues regarding the quality or condition of the flowers must be reported immediately upon receipt.
</p>
            <p>4. Fulfillment and Fees
• In-Store Pickup: Customers may choose to pick up their orders directly at the store at no additional fulfillment cost.

• Delivery Services: An additional delivery fee will be applied only if the store is responsible for transporting the items to your location.

• Third-Party Courier Booking: If the customer chooses to book an external courier service (e.g., Lalamove, Grab) for pickup, Gazzete in Vines is not liable for any damage, loss, or degradation of the product once it has been handed over to the third-party courier.
</p>
            <p>5. Terms and Agreement Integration
•Transparency: This section ensures full transparency between the business and the customer regarding handcrafted expectations.

•Mandatory Acceptance: You must review and accept these Terms and Conditions before the system allows you to finalize your order.
</p>
        </div>
        <button class="size-btn" style="margin-top:20px; background: var(--coral); color: white;" onclick="toggleTermsModal(false)">I Understand</button>
    </div>
</div>

<div id="layersModal" class="modal-bg" onclick="this.style.display='none'">
    <div class="modal-content" style="max-width:320px;" onclick="event.stopPropagation()">
        <span onclick="document.getElementById('layersModal').style.display='none'" style="float:right; cursor:pointer; font-weight:bold; color:var(--coral);">✕</span>
        <h3 style="color: var(--coral); margin-top:0; font-size:14px; text-transform:uppercase;">Layers</h3>
        <div id="layerRows" style="max-height:350px; overflow-y:auto; padding-right:10px;"></div>
    </div>
</div>

<!-- GUIDE / INSTRUCTIONS MODAL -->
<div id="guideModal" class="modal-bg" onclick="this.style.display='none'">
    <div class="modal-content" onclick="event.stopPropagation()">
        <span onclick="document.getElementById('guideModal').style.display='none'" style="float:right; cursor:pointer; font-weight:bold; color:var(--coral); font-size: 18px;">✕</span>
        <h3 style="color: var(--coral); margin-top:0; font-size:18px; text-transform:uppercase;">How to Customize</h3>
        <div style="font-size:12px; color:#666; line-height:1.6; max-height: 400px; overflow-y: auto; padding-right: 10px;">
            <p><strong>Step 1: Choose a Size</strong><br>Select the base size of your bouquet. This will set your budget and determine how many flowers, fillers, and leaves you can add.</p>
            <p><strong>Step 2: Pick a Wrapper</strong><br>Select a wrapper style from the panel. This provides the aesthetic background and foreground of your bouquet.</p>
            <p><strong>Step 3: Add Flowers</strong><br>Click on the flowers you like! Some flowers will prompt you to choose a specific color. You can move them around the canvas by dragging them.</p>
            <p><strong>Step 4: Add Fillers & Leaves</strong><br>Enhance your arrangement with fillers. Keep an eye on your required limits at the bottom left panel.</p>
            <p><strong>Step 5: Adjust Elements</strong><br>Click any item on the canvas to select it. Use the floating tools on the right to rotate, bend, flip, or adjust its layer (bring to front/back).</p>
            <p><strong>Step 6: Submit Request</strong><br>Once your status says "Ready ✓", agree to the terms and click "Request Bouquet"!</p>
        </div>
        <button class="size-btn" style="margin-top:20px; background: var(--coral); color: white;" onclick="document.getElementById('guideModal').style.display='none'">Got it!</button>
    </div>
</div>

<!-- LIMIT REACHED MODAL -->
<div id="limitModal" class="modal-bg" onclick="this.style.display='none'">
    <div class="modal-content" style="text-align: center; max-width: 320px;" onclick="event.stopPropagation()">
        <div style="font-size: 45px; margin-bottom: 10px;">🌸</div>
        <h3 style="color: var(--coral); margin-top:0; font-size:16px; text-transform:uppercase;">Limit Reached!</h3>
        <p id="limitModalMessage" style="font-size:12px; color:#666; line-height:1.6; margin-bottom: 20px;">You cannot add more items of this type.</p>
        <button class="size-btn" style="background: var(--coral); color: white; width: 100%;" onclick="document.getElementById('limitModal').style.display='none'">Okay</button>
    </div>
</div>

<script>
// --- PHP DATA PASSED TO JS ---
const dynamicFlowers = <?php echo json_encode($df_array); ?>;
const dynamicWrappers = <?php echo json_encode($dw_array); ?>; 
const dynamicRecipes = <?php echo json_encode($dyn_recipes); ?>;

const RULES = {
    'Small': { f: [1,3], fil: 3, l: 2, price: 149, prefix: 's' },
    'Medium': { f: [4,6], fil: 4, l: 3, price: 299, prefix: 'm' },
    'Large': { f: [7,9], fil: 7, l: 4, price: 499, prefix: 'l' },
    'Custom': { f: [7,999], fil: 7, l: 4, price: '699+', prefix: 'l' } 
};
const MEASUREMENTS = { 'Small': '9"', 'Medium': '18"', 'Large': '22"', 'Custom': 'Custom' };

const flowersData = [
    { name: "Tulip", colors: 8, file: "tulip", list: ["Red","Pink","Orange","Yellow","Purple","Blue","Green","White"] },
    { name: "Rose", colors: 9, file: "rose", list: ["Red","Pink","Orange","Yellow","Purple","Blue","Green","White","Black"] },
    { name: "Small Sunflower", colors: 4, file: "s_sunflower", list: ["Yellow","Pink","Purple","Red"] },
    { name: "Big Sunflower", colors: 4, file: "b_sunflower", list: ["Yellow","Pink","Purple","Red"] },
    { name: "Lily", colors: 5, file: "lily", list: ["Red","Pink","Yellow","Orange","White"] },
    { name: "Calla lily", colors: 5, file: "calla", list: ["Magenta","Maroon","Orange","Yellow","White"] },
    { name: "Poppy", colors: 5, file: "poppy", list: ["Red","Orange","Yellow","Pink","White"] },
    { name: "Iris", colors: 4, file: "iris", list: ["Purple","Pink","Orange","Yellow"] },
    { name: "Cornflower", colors: 5, file: "corn", list: ["Blue","Purple","Pink","White Pink","White Purple"] },
    { name: "Carnation", colors: 7, file: "carnation", list: ["Red","Orange","Yellow","Pink","Purple","Green","White"] },
    { name: "Hyacinth", colors: 5, file: "hyacinth", list: ["Red","Yellow","Pink","Purple","White"] },
    { name: "Hydrangea", colors: 5, file: "hydra", list: ["Blue","Pink","Purple","White","Orange"] },
    { name: "Fuchsia", colors: 3, file: "fuchsia", list: ["Red","Pink","Purple"] },
    { name: "Spider Lily", colors: 5, file: "spider", list: ["Red","Blue","Purple","Pink","Black"] },
    { name: "Thistle", colors: 3, file: "thistle", list: ["Pink","Purple","Blue"] }
];

const fillersData = [
    { name: "Daisy", file: "daisy.png", type: "filler" },
    { name: "Lavender", file: "lavender.png", type: "filler" },
    { name: "Baby's Breath", file: "baby.png", type: "filler" },
    { name: "Lily of Valley", file: "valley.png", type: "filler" },
    { name: "Statice", file: "statice.png", type: "filler" },
    { name: "Bell flower", file: "bell.png", type: "filler" },
    { name: "Eucalyptus", file: "eucalyptus.png", type: "leaf" },
    { name: "Gardenia", file: "gardenia.png", type: "leaf" },
    { name: "Small Leaf", file: "leaf.png", type: "leaf" }
];

let state = { size: null, wrapper: null, items: [], activeId: null };
let counts = { flower: 0, filler: 0, leaf: 0 };
let currentScale = 1;
let currentToolMode = '';
let dragInfo = { dragging: false, id: null, startX: 0, startY: 0, origX: 0, origY: 0 };

function generateBendedFlower(it) {
    let numSlices = 15; let totalH = it.h; let stemH = totalH * 0.65; let petalH = totalH * 0.35; let h = stemH / numSlices; 
    let bendAngle = (it.bend || 0) / numSlices; let bg = `url('${it.file}')`; let bgSize = `100% ${totalH}px`;
    let html = `<div style="position:absolute; bottom:${h}px; left:0; width:100%; height:${petalH + 1}px; background-image:${bg}; background-size:${bgSize}; background-position:center top; transform-origin:bottom center; transform:rotate(${bendAngle}deg); pointer-events:none;"></div>`;
    for(let i = numSlices - 1; i >= 1; i--) {
        let bgY = -(petalH + (numSlices - i - 1) * h);
        html = `<div style="position:absolute; bottom:${h}px; left:0; width:100%; height:${h + 0.5}px; background-image:${bg}; background-size:${bgSize}; background-position:center ${bgY}px; transform-origin:bottom center; transform:rotate(${bendAngle}deg); pointer-events:none;">${html}</div>`;
    }
    let bgY0 = -(totalH - h);
    html = `<div style="position:absolute; bottom:0; left:0; width:100%; height:${h + 0.5}px; background-image:${bg}; background-size:${bgSize}; background-position:center ${bgY0}px; transform-origin:bottom center; transform:rotate(0deg); pointer-events:none;">${html}</div>`;
    return html;
}

function startDrag(e, id) {
    e.stopPropagation(); 
    setActive(id);
    let evt = e.type.includes('touch') ? e.touches[0] : e;
    dragInfo = { dragging: true, id: id, startX: evt.clientX, startY: evt.clientY };
    let it = state.items.find(i => i.id === id);
    dragInfo.origX = it.x; dragInfo.origY = it.y;
    closePopup();
}

document.addEventListener('mousemove', (e) => {
    if(!dragInfo.dragging) return;
    let dx = (e.clientX - dragInfo.startX) / currentScale;
    let dy = (e.clientY - dragInfo.startY) / currentScale;
    let it = state.items.find(i => i.id === dragInfo.id);
    it.x = dragInfo.origX + dx; it.y = dragInfo.origY + dy;
    updateCanvas();
});
document.addEventListener('mouseup', () => dragInfo.dragging = false);

function togglePopup(mode) {
    const it = state.items.find(i => i.id === state.activeId);
    if(!it) return alert("Select an item first!");
    currentToolMode = mode;
    const popup = document.getElementById('toolPopup');
    const slider = document.getElementById('popupSlider');
    const el = document.getElementById('item-' + it.id);
    document.getElementById('popupLabel').innerText = mode === 'bend' ? "BEND" : "ROTATE";
    slider.min = mode === 'bend' ? -45 : -180; slider.max = mode === 'bend' ? 45 : 180;
    slider.value = mode === 'bend' ? (it.bend || 0) : (it.rot || 0);
    const rect = el.getBoundingClientRect();
    const canvasRect = document.getElementById('canvas').getBoundingClientRect();
    popup.style.left = (rect.left - canvasRect.left + rect.width / 2) + "px";
    popup.style.top = (rect.top - canvasRect.top - 80) + "px";
    popup.style.display = "flex";
}

function updateItemProp(val) {
    let it = state.items.find(i => i.id === state.activeId);
    if(!it) return;
    let v = parseInt(val);
    if(currentToolMode === 'bend') it.bend = v; else it.rot = v;
    updateCanvas();
}

function setSize(s, price) {
    state.size = s; 
    state.items = []; 
    counts = { flower: 0, filler: 0, leaf: 0 };
    currentScale = (s === 'Small') ? 0.85 : ((s === 'Large' || s === 'Custom') ? 1.15 : 1);
    document.getElementById('bouquetScaleWrapper').style.transform = `scale(${currentScale})`;
    
    document.getElementById('sum-size').innerText = "Size: " + s + (s !== 'Custom' ? " (" + MEASUREMENTS[s] + ")" : "");
    
    let priceText = (s === 'Custom') ? "₱699+" : "₱" + price + ".00";
    document.getElementById('sum-price').innerText = priceText;

    if (s === 'Custom') {
        document.getElementById('custom-size-input-div').style.display = 'block';
    } else {
        document.getElementById('custom-size-input-div').style.display = 'none';
        document.getElementById('customSizeInput').value = ''; 
    }

    renderWrappers(s); 
    updateCanvas(); 
    updateSummary(); 
    showPanel('wrapper-panel');
}

function addItem(src, type, name, isDynamic = false) {
    const r = RULES[state.size];
    let maxF = state.size === 'Custom' ? Infinity : r.f[1];
    let maxFil = state.size === 'Custom' ? Infinity : r.fil;
    let maxL = state.size === 'Custom' ? Infinity : r.l;
    
    // Custom Modal Triggers imbes na alert()
    if(type === 'flower' && counts.flower >= maxF) {
        document.getElementById('limitModalMessage').innerText = "Oops! You've reached the maximum limit for FLOWERS based on your chosen bouquet size. You can upgrade to a larger size or delete existing flowers to add new ones.";
        document.getElementById('limitModal').style.display = 'flex';
        return;
    }
    if(type === 'filler' && counts.filler >= maxFil) {
        document.getElementById('limitModalMessage').innerText = "Oops! You've reached the maximum limit for FILLERS based on your chosen bouquet size.";
        document.getElementById('limitModal').style.display = 'flex';
        return;
    }
    if(type === 'leaf' && counts.leaf >= maxL) {
        document.getElementById('limitModalMessage').innerText = "Oops! You've reached the maximum limit for LEAVES based on your chosen bouquet size.";
        document.getElementById('limitModal').style.display = 'flex';
        return;
    }
    
    let sizeVal = (type === 'flower') ? 200 : 140; 
    let newItem = { id: Date.now(), type: type, name: name, file: src, x: 125, y: 150, w: sizeVal, h: sizeVal, rot: 0, bend: 0, flip: 1, isDynamic: isDynamic };
    
    state.items.push(newItem);
    counts[type]++;
    setActive(newItem.id); 
    updateSummary(); 
    closeColorModal();
}

function updateSummary() {
    if (!state.size) {
        document.getElementById('request-btn').disabled = true;
        return;
    }
    
    const r = RULES[state.size];
    let maxFLabel = state.size === 'Custom' ? '∞' : r.f[1];
    let maxFilLabel = state.size === 'Custom' ? '∞' : r.fil;
    let maxLLabel = state.size === 'Custom' ? '∞' : r.l;

    document.getElementById('sum-items').innerText = `Items: ${counts.flower}/${maxFLabel} Flower | ${counts.filler}/${maxFilLabel} Filler | ${counts.leaf}/${maxLLabel} Leaf`;
    
    let hasEnoughFlowers = state.size === 'Custom' ? (counts.flower >= r.f[0]) : (counts.flower >= r.f[0] && counts.flower <= r.f[1]);
    let hasEnoughFillers = state.size === 'Custom' ? (counts.filler >= r.fil) : (counts.filler === r.fil);
    let hasEnoughLeaves = state.size === 'Custom' ? (counts.leaf >= r.l) : (counts.leaf === r.l);
    let isTermsChecked = document.getElementById('termsCheckbox').checked;

    let customVal = document.getElementById('customSizeInput') ? document.getElementById('customSizeInput').value.trim() : '';
    let isCustomValid = (state.size !== 'Custom') || (state.size === 'Custom' && customVal !== '' && parseInt(customVal) > 0);
    
    if (hasEnoughFlowers && hasEnoughFillers && hasEnoughLeaves && isTermsChecked && isCustomValid) {
        document.getElementById('request-btn').disabled = false;
        document.getElementById('sum-status').innerText = "Status: Ready ✓";
        document.getElementById('sum-status').style.color = "green";
    } else {
        document.getElementById('request-btn').disabled = true;
        let missingFlowers = r.f[0] - counts.flower;
        let missingFillers = r.fil - counts.filler;
        let missingLeaves = r.l - counts.leaf;
        let statusText = [];
        
        if (missingFlowers > 0) statusText.push(`${missingFlowers} Flower`);
        if (missingFillers > 0) statusText.push(`${missingFillers} Filler`);
        if (missingLeaves > 0) statusText.push(`${missingLeaves} Leaf`);
        
        if (statusText.length > 0) {
            document.getElementById('sum-status').innerText = `Status: Add ` + statusText.join(', ');
        } else if (!isCustomValid) {
            document.getElementById('sum-status').innerText = `Status: Specify Custom Inches`;
        } else if (!isTermsChecked) {
            document.getElementById('sum-status').innerText = `Status: Please agree to Terms`;
        }
        document.getElementById('sum-status').style.color = "red";
    }
}

function renderAssets() {
    let hardcodedHtml = flowersData.map((f, i) => `<div class="asset-item" onclick="openColorModal(${i})"><img src="img/custom/bouquet/flower/${f.file}1.png"><span>${f.name}</span></div>`).join('');
    
    let dynHtml = '';
    if (typeof dynamicFlowers !== 'undefined' && dynamicFlowers.length > 0) {
        dynHtml = dynamicFlowers.map(f => {
            let safeName = f.name.replace(/'/g, "\\'");
            return `<div class="asset-item" onclick="addItem('uploads/custom_flowers/${f.image}', 'flower', '${safeName}', true)"><img src="uploads/custom_flowers/${f.image}"><span>${f.name}</span></div>`;
        }).join('');
    }

    document.getElementById('flower-list').innerHTML = hardcodedHtml + dynHtml;
    document.getElementById('filler-list').innerHTML = fillersData.map(f => `<div class="asset-item" onclick="addItem('img/custom/bouquet/filler/${f.file}', '${f.type}', '${f.name.replace(/'/g, "\\'")}')"><img src="img/custom/bouquet/filler/${f.file}"><span>${f.name}</span></div>`).join('');
}

// BAGO: Inayos ang logic para mas sure na case-insensitive ang pag-detect sa Size ng wrapper mula sa database
function renderWrappers(size) {
    const list = document.getElementById('wrapper-list'); 
    list.innerHTML = "";
    
    // 1. Hardcoded Wrappers
    for(let i=1; i<=3; i++) { 
        let backUrl = `img/custom/bouquet/wrapper/${RULES[size].prefix}${i}.png`;
        let frontUrl = `img/custom/bouquet/wrapper/t_${RULES[size].prefix}${i}.png`;
        list.innerHTML += `<div class="asset-item" onclick="setWrapper('${backUrl}', '${frontUrl}')"><img src="${backUrl}"><span>Style ${i}</span></div>`; 
    }

    // 2. Dynamic Wrappers
    let effectiveSize = size === 'Custom' ? 'Large' : size; 
    let targetSize = effectiveSize.trim().toLowerCase();

    if (typeof dynamicWrappers !== 'undefined' && dynamicWrappers.length > 0) {
        dynamicWrappers.forEach(w => {
            let dbSize = (w.size || '').trim().toLowerCase();
            
            if(dbSize === targetSize || (dbSize === 'large' && targetSize === 'custom')) {
                let backUrl = `uploads/custom_wrappers/${w.image_back}`;
                let frontUrl = `uploads/custom_wrappers/${w.image_front}`;
                list.innerHTML += `<div class="asset-item" onclick="setWrapper('${backUrl}', '${frontUrl}')"><img src="${backUrl}"><span>${w.name}</span></div>`;
            }
        });
    }
}

function setWrapper(backUrl, frontUrl) {
    state.wrapper = backUrl; 
    document.getElementById('layerBack').style.backgroundImage = `url('${backUrl}')`;
    document.getElementById('layerFront').style.backgroundImage = `url('${frontUrl}')`;
    showPanel('flower-panel');
}

function toolMove(dx, dy) { let it = state.items.find(i => i.id === state.activeId); if(it) { it.x += dx; it.y += dy; updateCanvas(); } }

function toolZIndex(dir) {
    let idx = state.items.findIndex(i => i.id === state.activeId);
    if(idx === -1) { return; }
    if (dir === 1 && idx < state.items.length - 1) {
        let temp = state.items[idx]; state.items[idx] = state.items[idx + 1]; state.items[idx + 1] = temp;
    } else if (dir === -1 && idx > 0) {
        let temp = state.items[idx]; state.items[idx] = state.items[idx - 1]; state.items[idx - 1] = temp;
    }
    updateCanvas();
}

function manipulate(action) { let it = state.items.find(i => i.id === state.activeId); if(it && action === 'flip') { it.flip *= -1; updateCanvas(); } }

function removeItem() {
    let it = state.items.find(i => i.id === state.activeId); 
    if(it) { counts[it.type]--; }
    state.items = state.items.filter(i => i.id !== state.activeId);
    state.activeId = null; updateCanvas(); updateSummary(); closePopup();
}

function openLayersModal() { document.getElementById('layersModal').style.display = 'flex'; renderLayersList(); }

function renderLayersList() {
    let rows = document.getElementById('layerRows'); let html = '';
    for (let i = state.items.length - 1; i >= 0; i--) {
        let it = state.items[i]; let isAct = (it.id === state.activeId);
        html += `
        <div style="display:flex; align-items:center; gap:10px; padding:10px; border-bottom:1px solid #eee; border-radius: 8px; margin-bottom: 5px; background:${isAct ? '#fff5f8' : 'white'}; border: ${isAct ? '1px solid var(--peach)' : '1px solid transparent'};">
            <img src="${it.file}" style="width:35px; height:35px; object-fit:contain; cursor:pointer;" onclick="selectFromLayer(${it.id})">
            <span style="font-size:12px; font-weight:bold; flex-grow:1; cursor:pointer; color:${isAct ? 'var(--coral)' : '#333'};" onclick="selectFromLayer(${it.id})">
                ${it.name} ${isAct ? '<span style="font-size:9px;"><br>(Selected)</span>' : ''}
            </span>
            <div style="display:flex; gap:5px;">
                <button onclick="moveLayer(${i}, 1); event.stopPropagation();" style="border:none; background:var(--peach); color:var(--coral); font-weight:bold; border-radius:5px; cursor:pointer; padding:8px 12px;" ${i === state.items.length - 1 ? 'disabled opacity="0.5"' : ''}>▲</button>
                <button onclick="moveLayer(${i}, -1); event.stopPropagation();" style="border:none; background:var(--peach); color:var(--coral); font-weight:bold; border-radius:5px; cursor:pointer; padding:8px 12px;" ${i === 0 ? 'disabled opacity="0.5"' : ''}>▼</button>
            </div>
        </div>`;
    }
    if(state.items.length === 0) { html = '<div style="text-align:center; padding:20px; color:#aaa; font-size:11px;">No items added yet.</div>'; }
    rows.innerHTML = html;
}

function selectFromLayer(id) { setActive(id); renderLayersList(); }
function moveLayer(index, dir) {
    if (dir === 1 && index < state.items.length - 1) { let temp = state.items[index]; state.items[index] = state.items[index + 1]; state.items[index + 1] = temp; }
    else if (dir === -1 && index > 0) { let temp = state.items[index]; state.items[index] = state.items[index - 1]; state.items[index - 1] = temp; }
    updateCanvas(); renderLayersList(); 
}

function openColorModal(idx) {
    const f = flowersData[idx]; document.getElementById('modal-flower-name').innerText = f.name;
    document.getElementById('color-options').innerHTML = Array.from({length:f.colors}, (_, i) => `<div class="asset-item" onclick="addItem('img/custom/bouquet/flower/${f.file}${i+1}.png', 'flower', '${f.name}')"><img src="img/custom/bouquet/flower/${f.file}${i+1}.png"><span>${f.list[i]}</span></div>`).join('');
    document.getElementById('color-modal-box').style.display = "flex";
}

function updateCanvas() {
    document.getElementById('layerItems').innerHTML = state.items.map(it => `
        <div id="item-${it.id}" class="canvas-item ${it.id === state.activeId ? 'active' : ''}" 
             style="width:${it.w}px; height:${it.h}px; left:${it.x}px; top:${it.y}px; transform:rotate(${it.rot}deg) scaleX(${it.flip}); pointer-events:auto;" 
             onmousedown="startDrag(event, ${it.id})">
            ${generateBendedFlower(it)}
        </div>`).join('');
}

function closeColorModal() { document.getElementById('color-modal-box').style.display = "none"; }
function showPanel(id) { document.querySelectorAll('.panel').forEach(p => p.classList.remove('active')); document.getElementById(id).classList.add('active'); }
function setActive(id) { state.activeId = id; updateCanvas(); }
function closePopup() { document.getElementById('toolPopup').style.display = 'none'; }
function toggleTermsModal(show) { document.getElementById('termsModal').style.display = show ? 'flex' : 'none'; }

function calculateMaterials() {
    let deductions = {};
    function addMat(name, qty) {
        if(!deductions[name]) deductions[name] = 0;
        deductions[name] += qty;
    }

    if (state.size === 'Small') { addMat('Wrapper', 2); addMat('Floral Foam', 1); addMat('Ribbon', 0.01); }
    else if (state.size === 'Medium') { addMat('Wrapper', 3); addMat('Floral Foam', 1); addMat('Ribbon', 0.03); }
    else if (state.size === 'Large' || state.size === 'Custom') { addMat('Wrapper', 8); addMat('Floral Foam', 1); addMat('Ribbon', 0.06); }

    state.items.forEach(item => {
        let name = item.name.toLowerCase().trim();

        if (item.isDynamic) {
            if (dynamicRecipes[name]) {
                dynamicRecipes[name].forEach(r => {
                    addMat(r.mat, parseFloat(r.qty));
                });
            }
            return;
        }

        let colorStr = 'Red';
        if (item.type === 'flower') {
            let match = item.file.match(/([a-z_]+)(\d+)\.png$/i);
            if (match) {
                let base = match[1].toLowerCase();
                let num = parseInt(match[2]) - 1;
                const f_colors = {
                    'tulip': ["Red","Pink","Orange","Yellow","Purple","Blue","Green","White"],
                    'rose': ["Red","Pink","Orange","Yellow","Purple","Blue","Green","White","Black"],
                    's_sunflower': ["Yellow","Pink","Purple","Red"],
                    'b_sunflower': ["Yellow","Pink","Purple","Red"],
                    'lily': ["Red","Pink","Yellow","Orange","White"],
                    'calla': ["Magenta","Maroon","Orange","Yellow","White"], 
                    'poppy': ["Red","Orange","Yellow","Pink","White"],
                    'iris': ["Purple","Pink","Orange","Yellow"],
                    'corn': ["Blue","Purple","Pink","White Pink","White Purple"], 
                    'carnation': ["Red","Orange","Yellow","Pink","Purple","Green","White"],
                    'hyacinth': ["Red","Yellow","Pink","Purple","White"],
                    'hydra': ["Blue","Pink","Purple","White","Orange"],
                    'fuchsia': ["Red","Pink","Purple"],
                    'spider': ["Red","Blue","Purple","Pink","Black"],
                    'thistle': ["Pink","Purple","Blue"]
                };
                if (f_colors[base] && f_colors[base][num]) {
                    colorStr = f_colors[base][num];
                }
            }
        }

        let fw_color = "Fuzzy Wire " + colorStr.charAt(0).toUpperCase() + colorStr.slice(1).toLowerCase();
        if(colorStr.toLowerCase() === 'white pink') fw_color = "Fuzzy Wire Pink";
        if(colorStr.toLowerCase() === 'white purple') fw_color = "Fuzzy Wire Purple";

        let t = 0.01; let g = 0.12;

        if(name === 'tulip') { addMat(fw_color, 8); addMat('Fuzzy Wire Green', 4); addMat('Stem Wire', 1); addMat('Floral Tape', t); addMat('Glue Stick', g); }
        else if(name === 'rose') { addMat(fw_color, 30); addMat('Fuzzy Wire Green', 9); addMat('Stem Wire', 1); }
        else if(name === 'small sunflower') { addMat(fw_color, 8); addMat('Fuzzy Wire Brown', 1); addMat('Fuzzy Wire Green', 3); }
        else if(name === 'big sunflower') { addMat(fw_color, 44); addMat('Fuzzy Wire Brown', 8); addMat('Fuzzy Wire Green', 31); }
        else if(name === 'lily') { addMat(fw_color, 19); addMat('Fuzzy Wire Green', 9); addMat('Stem Wire', 1); addMat('Floral Tape', t); }
        else if(name === 'calla lily') { addMat(fw_color, 8); addMat('Fuzzy Wire Yellow', 1); addMat('Fuzzy Wire Green', 4); addMat('Stem Wire', 1); addMat('Floral Tape', t); }
        else if(name === 'spider lily') { addMat(fw_color, 28); addMat('Fuzzy Wire Green', 6); addMat('Stem Wire', 1); addMat('Floral Tape', t); addMat('Glue Stick', g); }
        else if(name === 'poppy') { addMat(fw_color, 19); addMat('Fuzzy Wire Green', 2); addMat('Floral Tape', t); }
        else if(name === 'iris') { addMat(fw_color, 6); addMat('Fuzzy Wire Green', 3); addMat('Stem Wire', 1); addMat('Floral Tape', t); }
        else if(name === 'cornflower') { addMat(fw_color, 6); addMat('Fuzzy Wire Black', 1); addMat('Fuzzy Wire Green', 4); addMat('Stem Wire', 1); addMat('Floral Tape', t); addMat('Glue Stick', g); }
        else if(name === 'carnation') { addMat(fw_color, 24); addMat('Fuzzy Wire Green', 11); addMat('Stem Wire', 1); addMat('Floral Tape', t); addMat('Glue Stick', g); }
        else if(name === 'hyacinth') { addMat(fw_color, 40); addMat('Fuzzy Wire Green', 6); addMat('Stem Wire', 1); addMat('Floral Tape', t); }
        else if(name === 'hydrangea') { addMat(fw_color, 42); addMat('Fuzzy Wire Green', 10); addMat('Floral Tape', 1.5); addMat('Glue Stick', g); }
        else if(name === 'fuchsia') { addMat(fw_color, 21); addMat('Fuzzy Wire Green', 6); addMat('Stem Wire', 1); addMat('Floral Tape', t); addMat('Glue Stick', g); }
        else if(name === 'thistle') { addMat(fw_color, 10); addMat('Fuzzy Wire Green', 5); addMat('Stem Wire', 1); addMat('Floral Tape', t); }
        
        else if(name === 'daisy') { addMat('Fuzzy Wire White', 2); addMat('Fuzzy Wire Green', 1); addMat('Fuzzy Wire Yellow', 1); }
        else if(name === 'lavender') { addMat('Fuzzy Wire Purple', 2); addMat('Fuzzy Wire Green', 1); addMat('Stem Wire', 1); }
        else if(name === "baby's breath" || name === "babys breath") { addMat('Fuzzy Wire White', 5); addMat('Stem Wire', 1); addMat('Floral Tape', t); }
        else if(name === 'lily of valley' || name === 'lily of the valley') { addMat('Fuzzy Wire White', 12); addMat('Fuzzy Wire Green', 4); }
        else if(name === 'statice') { addMat('Fuzzy Wire Purple', 15); addMat('Fuzzy Wire Green', 1); addMat('Floral Tape', t); }
        else if(name === 'bell flower') { addMat('Fuzzy Wire Purple', 7); addMat('Fuzzy Wire Green', 10); }
        else if(name === 'eucalyptus' || name === 'eucalyptus leaf') { addMat('Fuzzy Wire Green', 5); addMat('Stem Wire', 1); addMat('Floral Tape', t); }
        else if(name === 'gardenia' || name === 'gardenia leaf') { addMat('Fuzzy Wire Green', 14); addMat('Stem Wire', 1); addMat('Floral Tape', t); }
        else if(name === 'small leaf') { addMat('Fuzzy Wire Green', 1); addMat('Stem Wire', 1); addMat('Floral Tape', t); }
    });

    for (let mat in deductions) { deductions[mat] = parseFloat(deductions[mat].toFixed(2)); }
    return deductions;
}

async function submitRequest() {
    const canvas = document.getElementById('canvas');
    const btn = document.getElementById('request-btn');
    btn.disabled = true; btn.innerText = "Capturing...";
    try {
        const capture = await html2canvas(canvas, { useCORS: true, scale: 2, backgroundColor: "#ffffff" });
        const imageData = capture.toDataURL("image/png");
        
        let payloadItems = [...state.items];
        let finalSizeStr = state.size + " (" + MEASUREMENTS[state.size] + ")";
        
        if (state.size === 'Custom') {
            let customInches = document.getElementById('customSizeInput').value;
            finalSizeStr = "Large - Custom Request (" + customInches + " inches)";
            
            payloadItems.unshift({
                id: 'custom_size_info',
                type: 'info',
                name: 'Custom Request: ' + customInches + ' inches',
                qty: 1
            });
        }

        payloadItems.push({
            type: 'deduction_data',
            materials: calculateMaterials()
        });

        const orderData = { 
            order_type: 'Custom Bouquet', 
            size: finalSizeStr, 
            total_price: 0, 
            custom_image: imageData, 
            items_json: JSON.stringify(payloadItems) 
        };
        
        // Pinalitan natin ito papuntang add_custom_cart.php
        const response = await fetch('add_custom_cart.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(orderData) });
        const result = await response.json();
        
        if(result.success) { 
            alert("Custom Bouquet saved to cart!"); 
            window.location.href = "cart.php"; 
        } else { 
            alert("Error: Something went wrong."); 
            btn.disabled = false; btn.innerText = "Save to Cart"; 
        }
    } catch (err) { console.error(err); btn.disabled = false; btn.innerText = "Save to Cart"; }
}
renderAssets();
</script>
</body>
</html>