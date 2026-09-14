<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_logged_in'])) {
    header("Location: login.php");
    exit();
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
        
        /* NAVIGATION */
        .top-nav { background: var(--coral); padding: 12px 5%; display: flex; justify-content: space-between; align-items: center; color: white; z-index: 1001; }
        .top-nav a { color: white; text-decoration: none; font-size: 10px; font-weight: 900; text-transform: uppercase; margin-left: 15px; letter-spacing: 1px; }

        .main-layout { display: flex; flex-grow: 1; overflow: hidden; }

        /* SIDEBAR */
        .sidebar { width: 340px; background: white; border-right: 1px solid var(--peach); display: flex; flex-direction: column; z-index: 100; box-shadow: 5px 0 15px rgba(0,0,0,0.02); }
        .sidebar-header { padding: 20px; background: var(--grey-light); border-bottom: 1px solid var(--peach); color: var(--coral); font-weight: 900; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        
        .category-tab { padding: 15px 20px; border-bottom: 1px solid #f9f9f9; cursor: pointer; font-weight: 700; color: var(--text-dark); font-size: 11px; transition: 0.3s; text-transform: lowercase; }
        .category-tab::after { content: '.'; }
        
        .panel { display: none; padding: 20px; flex-grow: 1; overflow-y: auto; background: #fffafb; }
        .panel.active { display: block; }

        .size-btn { width: 100%; padding: 15px; margin-bottom: 10px; border: 2px solid var(--peach); background: white; border-radius: 50px; cursor: pointer; font-weight: 700; font-size: 11px; }

        .asset-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .asset-item { text-align: center; font-size: 10px; cursor: pointer; padding: 10px; background: white; border: 1px solid var(--peach); border-radius: 12px; transition: 0.3s; }
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
        .tool-btn { background: none; border: none; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px; font-size: 8px; color: var(--coral); font-weight: 700; text-transform: uppercase; }
        .dpad-btn { width: 24px; height: 24px; background: var(--peach); border: none; border-radius: 50%; color: var(--coral); cursor: pointer; font-size: 10px; font-weight: bold; }
        
        #request-btn { background: var(--coral); color: white; border: none; padding: 15px; width: 100%; border-radius: 50px; font-weight: 900; cursor: pointer; text-transform: uppercase; margin-top: 15px; transition: 0.3s; }
        #request-btn:disabled { background: #ccc; cursor: not-allowed; opacity: 0.7; }

        .modal-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; display: none; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 90%; max-width: 450px; position: relative; }
        .floating-slider-popup { position: absolute; display: none; flex-direction: column; background: white; padding: 15px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); z-index: 1001; border: 2px solid var(--peach); align-items: center; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
    <style>
        body {
            background:
                radial-gradient(circle at 8% 12%, rgba(244, 125, 168, 0.14), transparent 260px),
                radial-gradient(circle at 88% 82%, rgba(127, 155, 115, 0.11), transparent 260px),
                linear-gradient(180deg, #fffdfd 0%, #fff5f8 100%) !important;
        }

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
        .tool-btn { min-width: 78px; font-size: 13px !important; line-height: 1.25; }
        .dpad-btn { width: 36px !important; height: 36px !important; font-size: 16px !important; }
        .modal-content { border-radius: 18px !important; }
        .modal-content h3 { font-size: 22px !important; }
        .floating-slider-popup label, .floating-slider-popup button { font-size: 14px !important; }
    </style>
</head>
<body>

<nav class="top-nav">
    <div style="font-weight: 900; font-size: 18px; text-transform: lowercase;">vinescraft.</div>
    <div>
        <a href="index.php">Home</a>
        <a href="shop.php">Shop</a>
        <a href="customizer.php">Custom Bouquet</a>
        <a href="customize_tshirt.php">Custom Shirt</a>
        <a href="about.php">Our Story</a>
        <a href="cart.php">Cart</a>
        <a href="chat.php">Chat</a>
        <a href="my_orders.php">Orders</a>
        
        <?php if(!empty($_SESSION['user_logged_in'])): ?>
            <a href="profile.php">Account</a>
            <a href="logout.php" style="font-size: 9px; opacity: 0.6; margin-left: 5px;">(Logout)</a>
        <?php else: ?>
            <a href="login.php">Account</a>
        <?php endif; ?>
    </div>
</nav>

<div class="main-layout">
    <div class="sidebar">
        <div class="sidebar-header">Vinescraft Engine v2.0</div>
        
        <div class="category-tab" onclick="showPanel('size-panel')">1. bouquet size</div>
        <div id="size-panel" class="panel active">
            <button class="size-btn" onclick="setSize('Small', 149)">Small (9") — ₱149</button>
            <button class="size-btn" onclick="setSize('Medium', 299)">Medium (18") — ₱299</button>
            <button class="size-btn" onclick="setSize('Large', 499)">Large (22") — ₱499</button>
        </div>

        <div class="category-tab" onclick="showPanel('wrapper-panel')">2. wrapper style</div>
        <div id="wrapper-panel" class="panel"><div id="wrapper-list" class="asset-grid"></div></div>

        <div class="category-tab" onclick="showPanel('flower-panel')">3. wire flowers</div>
        <div id="flower-panel" class="panel"><div class="asset-grid" id="flower-list"></div></div>

        <div class="category-tab" onclick="showPanel('filler-panel')">4. fillers & leaves</div>
        <div id="filler-panel" class="panel"><div id="filler-list" class="asset-grid"></div></div>
    </div>

    <div class="editor-container">
        <!-- SUMMARY -->
        <div id="summary-box">
            <div id="sum-size" style="font-size: 13px; font-weight: 700;">Size: None</div>
            <div id="sum-items" style="font-size: 10px; color: #888; margin: 5px 0;">Items: 0/0 Fl | 0/0 Fil | 0/0 Lf</div>
            <div id="sum-status" style="font-size: 9px; font-weight: bold; color: red;">Status: Select Size</div>
            <div id="sum-price" style="color: var(--coral); font-size: 20px; font-weight: 900; margin: 5px 0;">₱0.00</div>
            
            <label style="display: flex; align-items: center; gap: 8px; font-size: 10px; color: #777; cursor: pointer; margin-bottom: 10px;">
                <input type="checkbox" id="termsCheckbox" onchange="updateSummary()">
                Agree to <span onclick="toggleTermsModal(true)" style="text-decoration: underline; color: var(--coral); font-weight: bold;">Terms</span>
            </label>
            <button id="request-btn" disabled onclick="submitRequest()">Request Bouquet</button>
        </div>

        <!-- CANVAS -->
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

        <!-- FLOATING TOOLS -->
        <div class="right-tools">
            <div class="dpad-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 3px;">
                <div></div><button class="dpad-btn" onclick="toolMove(0,-5)">▲</button><div></div>
                <button class="dpad-btn" onclick="toolMove(-5,0)">◀</button><div style="font-size:6px; text-align:center; color:var(--coral); line-height:24px;">MOVE</div><button class="dpad-btn" onclick="toolMove(5,0)">▶</button>
                <div></div><button class="dpad-btn" onclick="toolMove(0,5)">▼</button><div></div>
            </div>
            <button class="tool-btn" onclick="openLayersModal()">📂 LAYERS</button>
            <button class="tool-btn" onclick="toolZIndex(1)">⬆️ FORE</button>
            <button class="tool-btn" onclick="toolZIndex(-1)">⬇️ BACK</button>
            <button class="tool-btn" onclick="togglePopup('rotate')">🔄 ROTATE</button>
            <button class="tool-btn" onclick="togglePopup('bend')">⤴️ BEND</button>
            <button class="tool-btn" onclick="manipulate('flip')">↔️ FLIP</button>
            <button class="tool-btn" onclick="removeItem()" style="color:#ff4444; margin-top:10px;">❌ DEL</button>
        </div>
    </div>
</div>

<!-- MODALS -->
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
            <p>1. 2D preview is an approximation. Actual placement may vary upon assembly.</p>
            <p>2. Once requested, your order will be reviewed by our florist. You will receive a final price quotation.</p>
            <p>3. No refunds once assembly begins after the 50% downpayment is made.</p>
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

<script>
// --- CORE DATA ---
const RULES = {
    'Small': { f: [1,3], fil: 3, l: 2, price: 149, prefix: 's' },
    'Medium': { f: [4,6], fil: 4, l: 3, price: 299, prefix: 'm' },
    'Large': { f: [7,9], fil: 7, l: 4, price: 499, prefix: 'l' }
};
const MEASUREMENTS = { 'Small': '9"', 'Medium': '18"', 'Large': '22"' };

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

let state = { size: null, wrapper: 1, items: [], activeId: null };
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
    currentScale = (s === 'Small') ? 0.85 : (s === 'Large' ? 1.15 : 1);
    document.getElementById('bouquetScaleWrapper').style.transform = `scale(${currentScale})`;
    document.getElementById('sum-size').innerText = "Size: " + s + " (" + MEASUREMENTS[s] + ")";
    document.getElementById('sum-price').innerText = "₱" + price + ".00";
    renderWrappers(s); 
    
    updateCanvas(); 
    updateSummary(); 
    showPanel('wrapper-panel');
}

function addItem(src, type, name) {
    const r = RULES[state.size];
    if(type === 'flower' && counts.flower >= r.f[1]) return alert(`Limit reached! You can only add up to ${r.f[1]} flowers for this size.`);
    if(type === 'filler' && counts.filler >= r.fil) return alert(`Limit reached! You can only add up to ${r.fil} fillers for this size.`);
    if(type === 'leaf' && counts.leaf >= r.l) return alert(`Limit reached! You can only add up to ${r.l} leaves for this size.`);
    
    let sizeVal = (type === 'flower') ? 200 : 140; 
    let newItem = { id: Date.now(), type: type, name: name, file: src, x: 125, y: 150, w: sizeVal, h: sizeVal, rot: 0, bend: 0, flip: 1 };
    
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
    document.getElementById('sum-items').innerText = `Items: ${counts.flower}/${r.f[1]} Fl | ${counts.filler}/${r.fil} Fil | ${counts.leaf}/${r.l} Lf`;
    
    let hasEnoughFlowers = (counts.flower >= r.f[0] && counts.flower <= r.f[1]);
    let hasEnoughFillers = (counts.filler === r.fil);
    let hasEnoughLeaves = (counts.leaf === r.l);
    let isTermsChecked = document.getElementById('termsCheckbox').checked;
    
    if (hasEnoughFlowers && hasEnoughFillers && hasEnoughLeaves && isTermsChecked) {
        document.getElementById('request-btn').disabled = false;
        document.getElementById('sum-status').innerText = "Status: Ready ✓";
        document.getElementById('sum-status').style.color = "green";
    } else {
        document.getElementById('request-btn').disabled = true;
        let missingFlowers = r.f[0] - counts.flower;
        let missingFillers = r.fil - counts.filler;
        let missingLeaves = r.l - counts.leaf;
        let statusText = [];
        if (missingFlowers > 0) statusText.push(`${missingFlowers} Fl`);
        if (missingFillers > 0) statusText.push(`${missingFillers} Fil`);
        if (missingLeaves > 0) statusText.push(`${missingLeaves} Lf`);
        
        if (statusText.length > 0) {
            document.getElementById('sum-status').innerText = `Status: Add ` + statusText.join(', ');
        } else if (!isTermsChecked) {
            document.getElementById('sum-status').innerText = `Status: Please agree to Terms`;
        }
        document.getElementById('sum-status').style.color = "red";
    }
}

function renderAssets() {
    document.getElementById('flower-list').innerHTML = flowersData.map((f, i) => `<div class="asset-item" onclick="openColorModal(${i})"><img src="img/custom/bouquet/flower/${f.file}1.png"><span>${f.name}</span></div>`).join('');
    document.getElementById('filler-list').innerHTML = fillersData.map(f => `<div class="asset-item" onclick="addItem('img/custom/bouquet/filler/${f.file}', '${f.type}', '${f.name.replace(/'/g, "\\'")}')"><img src="img/custom/bouquet/filler/${f.file}"><span>${f.name}</span></div>`).join('');
}

function renderWrappers(size) {
    const list = document.getElementById('wrapper-list'); list.innerHTML = "";
    for(let i=1; i<=3; i++) { list.innerHTML += `<div class="asset-item" onclick="setWrapper('${RULES[size].prefix}${i}')"><img src="img/custom/bouquet/wrapper/${RULES[size].prefix}${i}.png"><span>Style ${i}</span></div>`; }
}

function setWrapper(id) {
    state.wrapper = id;
    document.getElementById('layerBack').style.backgroundImage = `url('img/custom/bouquet/wrapper/${id}.png')`;
    document.getElementById('layerFront').style.backgroundImage = `url('img/custom/bouquet/wrapper/t_${id}.png')`;
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

// --- BAGONG LOGIC: PRE-COMPUTE MATERIALS BAGO I-SAVE ---
function calculateMaterials() {
    let deductions = {};
    function addMat(name, qty) {
        if(!deductions[name]) deductions[name] = 0;
        deductions[name] += qty;
    }

    if (state.size === 'Small') { addMat('Wrapper', 2); addMat('Floral Foam', 1); addMat('Ribbon', 0.01); }
    else if (state.size === 'Medium') { addMat('Wrapper', 3); addMat('Floral Foam', 1); addMat('Ribbon', 0.03); }
    else if (state.size === 'Large') { addMat('Wrapper', 8); addMat('Floral Foam', 1); addMat('Ribbon', 0.06); }

    state.items.forEach(item => {
        let name = item.name.toLowerCase().trim();
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
                    'calla': ["Pink","Red","Orange","Yellow","White"], 
                    'poppy': ["Red","Orange","Yellow","Pink","White"],
                    'iris': ["Purple","Pink","Orange","Yellow"],
                    'corn': ["Blue","Purple","Pink","Pink","Purple"], 
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
        
        // BAGO: Ipapaloob natin yung computations sa pinakadulong part ng items_json!
        let payloadItems = [...state.items];
        payloadItems.push({
            type: 'deduction_data',
            materials: calculateMaterials()
        });
        
        const orderData = { 
            order_type: 'Custom Bouquet', 
            size: state.size + " (" + MEASUREMENTS[state.size] + ")", 
            total_price: 0, 
            custom_image: imageData, 
            items_json: JSON.stringify(payloadItems) 
        };
        
        const response = await fetch('save_order.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(orderData) });
        const result = await response.json();
        
        if(result.success) { 
            alert("Sent! Please wait for the florist to update the price quotation."); 
            window.location.href = "my_orders.php"; 
        } else { 
            alert("Error: Something went wrong."); 
            btn.disabled = false; btn.innerText = "Request Bouquet"; 
        }
    } catch (err) { console.error(err); btn.disabled = false; btn.innerText = "Request Bouquet"; }
}
renderAssets();
</script>
</body>
</html>