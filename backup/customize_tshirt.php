<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_logged_in'])) { header("Location: login.php"); exit(); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Shirt Designer | Vinescraft</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&family=Playfair+Display:ital,wght@1,900&family=Bebas+Neue&family=Pacifico&display=swap');

        :root {
            --coral: #f18973;
            --peach: #fce0d8;
            --text: #444;
            --bg: #fdf2f2;
        }

        body { 
            font-family: 'Montserrat', sans-serif; 
            background: var(--bg); 
            margin: 0; 
            display: flex; 
            flex-direction: column;
            height: 100vh; 
            overflow: hidden; 
            color: var(--text);
        }

        /* NAVIGATION */
        .top-nav { background: var(--coral); padding: 12px 5%; display: flex; justify-content: space-between; align-items: center; color: white; z-index: 1001; }
        .top-nav a { color: white; text-decoration: none; font-size: 10px; font-weight: 900; text-transform: uppercase; margin-left: 15px; letter-spacing: 1px; }

        .main-layout { display: flex; flex-grow: 1; overflow: hidden; }

        /* SIDEBAR */
        .sidebar { width: 340px; background: #fff; border-right: 1px solid var(--peach); display: flex; flex-direction: column; z-index: 100; overflow-y: auto; }
        .sidebar-header { padding: 20px; color: var(--coral); font-weight: 900; font-size: 18px; text-align: center; text-transform: lowercase; border-bottom: 1px solid var(--peach); }
        .category-tab { padding: 15px 20px; border-bottom: 1px solid var(--bg); cursor: pointer; font-weight: 700; color: #888; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; }
        .category-tab.active-tab { color: var(--coral); background: var(--bg); border-left: 4px solid var(--coral); }
        .panel { display: none; padding: 20px; background: white; }
        .panel.active { display: block; }

        /* BAGO: COLOR PICKER CSS */
        .color-btn { width: 100%; aspect-ratio: 1; border-radius: 50%; cursor: pointer; border: 2px solid transparent; transition: 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .color-btn:hover { transform: scale(1.15); border-color: var(--coral); }

        /* CANVAS AREA */
        .editor-container { flex-grow: 1; position: relative; display: flex; align-items: center; justify-content: center; background: var(--bg); }
        
        #canvas { width: 450px; height: 550px; background: white; border: 1px solid var(--peach); position: relative; overflow: hidden; border-radius: 25px; box-shadow: 0 15px 40px rgba(241, 137, 115, 0.1); user-select: none; }
        .shirt-layer { position: absolute; width: 100%; height: 100%; pointer-events: none; display: flex; justify-content: center; align-items: center; }
        .shirt-img { width: 90%; height: 90%; object-fit: contain; }
        .shirt-tint { position: absolute; width: 90%; height: 90%; mix-blend-mode: multiply; -webkit-mask-size: contain; mask-size: contain; -webkit-mask-repeat: no-repeat; mask-repeat: no-repeat; -webkit-mask-position: center; mask-position: center; }
        
        .items-layer { position: absolute; width: 100%; height: 100%; top: 0; left: 0; }
        .canvas-item { position: absolute; transform-origin: center center; cursor: grab; display: flex; align-items: center; justify-content: center; }
        .canvas-item.active { outline: 2px dashed var(--coral); }

        .curved-text-char { display: inline-block; position: relative; transform-origin: center bottom; }

        /* TOOLS BAR */
        .right-tools { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); display: flex; flex-direction: column; gap: 8px; background: white; padding: 15px 8px; border-radius: 50px; border: 1px solid var(--peach); z-index: 50; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .tool-btn { background: none; border: none; cursor: pointer; font-size: 8px; color: var(--coral); font-weight: 900; text-transform: uppercase; }

        /* MODAL */
        .modal-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(241, 137, 115, 0.4); backdrop-filter: blur(5px); z-index: 2000; display: none; justify-content: center; align-items: center; }
        .modal-content { background: white; width: 90%; max-width: 450px; padding: 40px; border-radius: 30px; border: 1px solid var(--peach); box-shadow: 0 20px 60px rgba(0,0,0,0.1); }

        /* GRID BUTTONS & INPUTS */
        .grid-btn { width: 100%; padding: 10px; margin-bottom: 8px; border: 1px solid var(--peach); background: white; border-radius: 10px; font-weight: 700; font-size: 10px; cursor: pointer; transition: 0.2s; }
        .grid-btn:hover { background: var(--peach); }
        .asset-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 15px; }
        .img-item { background: var(--bg); border: 1px solid var(--peach); border-radius: 10px; padding: 5px; cursor: pointer; text-align: center; }
        .img-item img { width: 100%; height: 40px; object-fit: contain; }

        .floating-slider-popup { position: absolute; display: none; flex-direction: column; background: white; padding: 15px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border: 1px solid var(--peach); z-index: 1000; gap: 5px; }
        
        #summary-box { position: absolute; bottom: 20px; left: 20px; background: white; padding: 20px; border-radius: 20px; border: 1px solid var(--peach); min-width: 220px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        
        #request-btn:disabled { background: #ccc; cursor: not-allowed; }
    </style>
    <link rel="stylesheet" href="assets/css/floral-theme.css">
    
    <!-- YUNG ORIGINAL MONG FANCY STYLES -->
    <style>
        body {
            background:
                radial-gradient(circle at 9% 14%, rgba(244, 125, 168, 0.13), transparent 260px),
                radial-gradient(circle at 88% 78%, rgba(127, 155, 115, 0.12), transparent 270px),
                linear-gradient(180deg, #fffdfd 0%, #fff6f9 100%) !important;
        }

        .top-nav { padding: 14px 4% !important; }
        .top-nav a { font-size: 14px !important; padding: 10px 12px !important; }
        .sidebar { width: 370px !important; box-shadow: 10px 0 28px rgba(137, 91, 108, 0.08); }
        .sidebar-header { background: linear-gradient(135deg, #fff, #fff3f7); color: #b84c76 !important; font-size: 28px !important; padding: 24px !important; }
        .category-tab { color: #5a4b51 !important; font-size: 18px !important; line-height: 1.25; letter-spacing: 0.5px; padding: 18px 24px !important; }
        .category-tab.active-tab, .category-tab:hover { background: #fff3f7 !important; color: var(--coral) !important; }
        .panel { padding: 24px !important; }
        .panel label { color: #806773 !important; font-size: 15px !important; letter-spacing: 0.7px; }
        .grid-btn { min-height: 58px; border-radius: 12px !important; font-size: 18px !important; line-height: 1.25; padding: 12px !important; }
        #customTextInput, #editTextInput { min-height: 54px; font-size: 17px !important; }
        #elements-panel button[onclick^="addTextItem"] { font-size: 18px !important; }
        .asset-grid { gap: 14px !important; }
        .img-item { border-radius: 14px !important; padding: 10px !important; box-shadow: 0 8px 18px rgba(137, 91, 108, 0.08); }
        .img-item:hover { border-color: var(--coral) !important; transform: translateY(-2px); }
        .img-item img { height: 58px !important; }
        .editor-container { background: linear-gradient(135deg, rgba(255,255,255,0.62), rgba(255,243,247,0.82)), radial-gradient(circle at 80% 14%, rgba(244,125,168,0.1), transparent 230px) !important; }
        #canvas { border-radius: 18px !important; border: 2px solid var(--peach) !important; box-shadow: 0 24px 60px rgba(137, 91, 108, 0.18) !important; }
        .canvas-item div { font-size: 58px !important; line-height: 1.1; text-shadow: 0 2px 8px rgba(255, 255, 255, 0.72); }
        .canvas-item img { min-width: 180px; }
        .right-tools { border-radius: 22px !important; padding: 16px 12px !important; gap: 10px !important; }
        .tool-btn { min-width: 78px; font-size: 13px !important; line-height: 1.25; }
        #summary-box { border-radius: 16px !important; min-width: 260px !important; box-shadow: 0 18px 42px rgba(137, 91, 108, 0.14) !important; }
        #summary-box > div:first-child, #summary-box label { font-size: 16px !important; }
        #sum-price { font-size: 36px !important; }
        #request-btn { min-height: 58px; font-size: 16px !important; }
        .modal-content { border-radius: 18px !important; }
        .modal-content h3 { font-size: 24px !important; }
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
        <div class="sidebar-header">design center</div>

        <div class="category-tab active-tab" id="tab-size" onclick="showPanel('size-panel', 'tab-size')">1. Size</div>
        <div id="size-panel" class="panel active">
            <button class="grid-btn" onclick="setSize('Small', 259)">Small (₱259)</button>
            <button class="grid-btn" onclick="setSize('Medium', 279)">Medium (₱279)</button>
            <button class="grid-btn" onclick="setSize('Large', 299)">Large (₱299)</button>
            <button class="grid-btn" onclick="setSize('Extra Large', 319)">XL (₱319)</button>
        </div>

        <!-- BAGO: UPDATED COLOR PICKER BUTTONS -->
        <div class="category-tab" id="tab-color" onclick="showPanel('color-panel', 'tab-color')">2. Shirt Color</div>
        <div id="color-panel" class="panel">
            <label style="font-size: 9px; font-weight: 900; color: #bbb;">SELECT AVAILABLE COLOR</label>
            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-top: 15px;">
                <div class="color-btn" style="background:#FF0000;" onclick="setShirtColor('#FF0000', 'Red')"></div>
                <div class="color-btn" style="background:#FFA500;" onclick="setShirtColor('#FFA500', 'Orange')"></div>
                <div class="color-btn" style="background:#FFFF00;" onclick="setShirtColor('#FFFF00', 'Yellow')"></div>
                <div class="color-btn" style="background:#008000;" onclick="setShirtColor('#008000', 'Green')"></div>
                <div class="color-btn" style="background:#0000FF;" onclick="setShirtColor('#0000FF', 'Blue')"></div>
                <div class="color-btn" style="background:#800080;" onclick="setShirtColor('#800080', 'Purple')"></div>
                <div class="color-btn" style="background:#FFC0CB;" onclick="setShirtColor('#FFC0CB', 'Pink')"></div>
                <div class="color-btn" style="background:#A52A2A;" onclick="setShirtColor('#A52A2A', 'Brown')"></div>
                <div class="color-btn" style="background:#FFFFFF; border: 1px solid #ccc;" onclick="setShirtColor('#FFFFFF', 'White')"></div>
                <div class="color-btn" style="background:#000000;" onclick="setShirtColor('#000000', 'Black')"></div>
            </div>
            <div id="selected-color-text" style="text-align:center; font-size: 11px; font-weight: bold; margin-top: 15px; color: var(--coral);">Selected: White</div>
        </div>

        <div class="category-tab" id="tab-view" onclick="showPanel('view-panel', 'tab-view')">3. Change View</div>
        <div id="view-panel" class="panel">
            <button class="grid-btn" onclick="setView('f')">Front Side</button>
            <button class="grid-btn" onclick="setView('b')">Back Side</button>
            <button class="grid-btn" onclick="setView('ls')">Left Sleeve</button>
            <button class="grid-btn" onclick="setView('rs')">Right Sleeve</button>
        </div>

        <div class="category-tab" id="tab-elements" onclick="showPanel('elements-panel', 'tab-elements')">4. Elements</div>
        <div id="elements-panel" class="panel">
            <label style="font-size: 9px; font-weight: 900; color: #bbb;">ADD MY OWN TEXT</label>
            <div style="display:flex; gap:5px; margin-bottom:15px; margin-top:5px;">
                <input type="text" id="customTextInput" placeholder="Type here..." style="flex:1; padding:10px; border:1px solid var(--peach); border-radius:8px; font-family:'Montserrat';">
                <button onclick="addTextItem(document.getElementById('customTextInput').value, 'Montserrat')" style="background:var(--coral); color:white; border:none; border-radius:8px; padding:0 15px; font-weight:900; cursor:pointer;">+</button>
            </div>

            <label style="font-size: 9px; font-weight: 900; color: #bbb;">PRESETS (5)</label>
            <button class="grid-btn" onclick="addTextItem('FEARLESS', 'Bebas Neue')">Fearless (Bold)</button>
            <button class="grid-btn" onclick="addTextItem('Bloom.', 'Pacifico')">Bloom. (Cursive)</button>
            <button class="grid-btn" onclick="addTextItem('URBAN', 'Impact')">Urban (Street)</button>
            <button class="grid-btn" onclick="addTextItem('VINESCRAFT', 'Playfair Display')">Vines (Fancy)</button>
            <button class="grid-btn" onclick="addTextItem('EST. 2026', 'Georgia')">Est. 2026</button>

            <hr style="border:none; border-top:1px solid var(--bg); margin:15px 0;">
            
            <label class="grid-btn" style="display:block; text-align:center; border-style:dashed; color:var(--coral);">
                <input type="file" accept=".ttf,.otf" style="display:none;" onchange="handleFontUpload(event)">
                Upload Custom Font
            </label>

            <label class="grid-btn" style="display:block; text-align:center; border-style:dashed; color:var(--coral); margin-top:10px;">
                <input type="file" accept="image/*" style="display:none;" onchange="handleCustomGraphicUpload(event)">
                Upload Own Graphic
            </label>

            <div class="asset-grid" id="graphic-list"></div>
        </div>

        <div class="category-tab" id="tab-edit" onclick="showPanel('edit-panel', 'tab-edit')" style="display:none;">5. Edit Item</div>
        <div id="edit-panel" class="panel">
            <label style="font-size: 9px; font-weight: 900;">CHANGE COLOR</label>
            <input type="color" id="itemColorPicker" style="width:100%; height:45px; border:none; border-radius:12px; margin-bottom:15px; cursor:pointer;" oninput="updateItemColor(this.value)">
            
            <div id="text-edit-fields" style="display:none;">
                <label style="font-size: 9px; font-weight: 900;">EDIT TEXT</label>
                <input type="text" id="editTextInput" style="width:100%; padding:12px; border:1px solid var(--peach); border-radius:12px; font-family:'Montserrat';" oninput="updateTextContent(this.value)">
            </div>
        </div>
    </div>

    <div class="editor-container">
        <!-- TOOLS BAR -->
        <div class="right-tools">
            <button class="tool-btn" onclick="toolZIndex(1)">⬆️<br>Fore</button>
            <button class="tool-btn" onclick="toolZIndex(-1)">⬇️<br>Back</button>
            <button class="tool-btn" onclick="togglePopup('rotate')">🔄<br>Rot</button>
            <button class="tool-btn" onclick="togglePopup('size')">📏<br>Size</button>
            <button class="tool-btn" onclick="togglePopup('curve')">🌈<br>Bend</button>
            <button class="tool-btn" onclick="togglePopup('spacing')">↔️<br>Space</button>
            <button class="tool-btn" onclick="togglePopup('opacity')">👻<br>Fade</button>
            <button class="tool-btn" onclick="removeItem()" style="color:#ff4757;">❌<br>Del</button>
        </div>

        <div id="canvas">
            <div class="shirt-layer">
                <img id="shirtBaseImg" src="img/custom/tshirt/shirt/f_shirt.png" class="shirt-img" crossorigin="anonymous">
                <div id="shirtTint" class="shirt-tint" style="background-color: #FFFFFF;"></div>
            </div>
            <div class="items-layer" id="layerItems"></div>
            <div id="toolPopup" class="floating-slider-popup">
                <label id="popupLabel">Adjust</label>
                <input type="range" id="popupSlider" oninput="updateItemProp(this.value)">
                <button onclick="closePopup()" style="border:none; background:none; color:var(--coral); font-weight:900; font-size:9px; cursor:pointer; margin-top:5px;">DONE</button>
            </div>
        </div>

        <div id="summary-box">
            <div style="color:var(--coral); font-weight:900; font-size:12px; text-transform:lowercase;">order summary.</div>
            <div id="sum-price" style="font-size:24px; font-weight:900; margin:5px 0;">₱0.00</div>
            <label style="font-size:9px; color:#aaa; display:flex; align-items:center; gap:8px; cursor:pointer;">
                <input type="checkbox" id="termsCheck" onchange="updateSummary()" style="accent-color:var(--coral);"> 
                Agree to <a href="javascript:void(0)" onclick="toggleTermsModal(true)" style="color:var(--coral); font-weight:900; text-decoration:none;">Terms & Conditions</a>
            </label>
            <button id="request-btn" disabled onclick="requestShirt()" style="width:100%; background:var(--coral); color:white; border:none; padding:12px; border-radius:50px; font-weight:900; margin-top:15px; cursor:pointer;">REQUEST SHIRT</button>
        </div>
    </div>
</div>

<div id="termsModal" class="modal-bg">
    <div class="modal-content">
        <h3 style="color:var(--coral); font-weight:900; margin-top:0;">terms and conditions.</h3>
        <div style="font-size:12px; color:#666; max-height:250px; overflow-y:auto; margin-bottom:20px; text-align:justify; padding-right:10px;">
            <p>1. <strong>Custom Orders:</strong> Designs submitted are reviewed and approved manually. Pricing may adjust based on complexity.</p>
            <p>2. <strong>Delivery:</strong> Production takes 3-5 days. Delivery is centered in Las Piñas and nearby areas.</p>
            <p>3. <strong>Quality:</strong> We use premium fuzzy wire and fabric. Actual colors may slightly vary from screen previews.</p>
        </div>
        <button class="grid-btn" style="background:var(--coral); color:white; border:none; border-radius:50px; padding:12px;" onclick="toggleTermsModal(false)">I UNDERSTAND</button>
    </div>
</div>

<script>
// BAGO: Idinagdag ang shirtColorName sa state para ma-save ang pangalan ng kulay
let state = { size: null, shirtColor: '#FFFFFF', shirtColorName: 'White', view: 'f', items: [], activeId: null };
let dragInfo = { dragging: false, id: null, startX: 0, startY: 0, origX: 0, origY: 0 };
let currentToolMode = '';
const MEASUREMENTS = { 'Small': '18"x26"', 'Medium': '19"x27"', 'Large': '20"x28"', 'Extra Large': '21"x29"' };

function showPanel(pid, tid) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active-tab'));
    document.getElementById(pid).classList.add('active');
    document.getElementById(tid).classList.add('active-tab');
}
function toggleTermsModal(show) { document.getElementById('termsModal').style.display = show ? 'flex' : 'none'; }

function setSize(s, price) {
    state.size = s;
    document.getElementById('sum-price').innerText = "₱" + price.toFixed(2);
    updateSummary(); showPanel('color-panel', 'tab-color');
}

// BAGO: Ise-save yung name kasama ng hex code para ipasa sa backend
function setShirtColor(hex, name = 'White') {
    state.shirtColor = hex;
    state.shirtColorName = name; 
    document.getElementById('selected-color-text').innerText = "Selected: " + name;

    const tint = document.getElementById('shirtTint');
    tint.style.backgroundColor = hex;
    const src = `url('img/custom/tshirt/shirt/${state.view}_shirt.png')`;
    tint.style.webkitMaskImage = src; 
    tint.style.maskImage = src;
}

function setView(v) {
    state.view = v;
    document.getElementById('shirtBaseImg').src = `img/custom/tshirt/shirt/${v}_shirt.png`;
    setShirtColor(state.shirtColor, state.shirtColorName); 
    updateCanvas();
}

function handleFontUpload(event) {
    const file = event.target.files[0];
    if (file) {
        const fontName = 'CustomFont_' + Date.now();
        const reader = new FileReader();
        reader.onload = function(e) {
            const newFont = new FontFace(fontName, `url(${e.target.result})`);
            newFont.load().then(function(loaded) {
                document.fonts.add(loaded);
                addTextItem("Custom Font", fontName);
            });
        };
        reader.readAsDataURL(file);
    }
}
function handleCustomGraphicUpload(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) { addGraphicItem(e.target.result); };
        reader.readAsDataURL(file);
    }
}

function addTextItem(content, font) {
    if(!content || !state.size) return alert(state.size ? "Type something!" : "Select size first!");
    let newItem = { id: Date.now(), type: 'text', content: content, font: font, view: state.view, x: 120, y: 180, scale: 1, rot: 0, color: '#000000', opacity: 1, spacing: 0, curve: 0, z: state.items.length };
    state.items.push(newItem); setActive(newItem.id); updateSummary();
}
function addGraphicItem(src) {
    if(!state.size) return alert("Select size first!");
    state.items.push({ id: Date.now(), type: 'graphic', src: src, view: state.view, x: 120, y: 180, scale: 1, rot: 0, opacity: 1, z: state.items.length });
    setActive(state.items[state.items.length-1].id);
    updateSummary();
}
function setActive(id) {
    state.activeId = id;
    let it = state.items.find(i => i.id === id);
    document.getElementById('tab-edit').style.display = 'block';
    if(it.type === 'text') {
        document.getElementById('text-edit-fields').style.display = 'block';
        document.getElementById('editTextInput').value = it.content;
    } else { document.getElementById('text-edit-fields').style.display = 'none'; }
    updateCanvas();
}

function updateCanvas() {
    const layer = document.getElementById('layerItems');
    layer.innerHTML = state.items.filter(it => it.view === state.view).map(it => {
        let activeCls = (it.id === state.activeId) ? 'active' : '';
        let baseStyle = `left:${it.x}px; top:${it.y}px; z-index:${it.z}; transform:rotate(${it.rot}deg) scale(${it.scale}); opacity:${it.opacity};`;
        let content = '';
        if(it.type === 'text') {
            const chars = it.content.split('');
            content = `<div style="font-family:'${it.font}'; color:${it.color}; font-size:30px; font-weight:900; letter-spacing:${it.spacing}px; position:relative;">`;
            if(Math.abs(it.curve) > 0) {
                chars.forEach((char, index) => {
                    const offset = index - (chars.length - 1) / 2;
                    const ty = Math.pow(offset, 2) * (it.curve * 0.5);
                    content += `<span class="curved-text-char" style="transform:rotate(${offset * it.curve}deg) translateY(${ty}px);">${char}</span>`;
                });
            } else { content += it.content; }
            content += `</div>`;
        } else { content = `<img src="${it.src}" style="width:160px; height:auto;">`; }
        return `<div class="canvas-item ${activeCls}" style="${baseStyle}" onmousedown="startDrag(event, ${it.id})">${content}</div>`;
    }).join('');
}

function togglePopup(mode) {
    currentToolMode = mode;
    const it = state.items.find(i => i.id === state.activeId);
    if(!it) return;
    const popup = document.getElementById('toolPopup');
    const slider = document.getElementById('popupSlider');
    popup.style.display = 'flex';
    
    popup.style.left = '20px'; 
    popup.style.top = '20px';
    popup.style.transform = 'none';

    if(mode === 'rotate') { slider.min = 0; slider.max = 360; slider.value = it.rot; }
    else if(mode === 'size') { slider.min = 0.5; slider.max = 3; slider.step = 0.1; slider.value = it.scale; }
    else if(mode === 'curve') { slider.min = -15; slider.max = 15; slider.step = 1; slider.value = it.curve; }
    else if(mode === 'spacing') { slider.min = -5; slider.max = 25; slider.step = 1; slider.value = it.spacing; }
    else if(mode === 'opacity') { slider.min = 0.1; slider.max = 1; slider.step = 0.1; slider.value = it.opacity; }
}

function updateItemProp(val) {
    let it = state.items.find(i => i.id === state.activeId);
    if(!it) return;
    if(currentToolMode === 'rotate') it.rot = val;
    else if(currentToolMode === 'size') it.scale = val;
    else if(currentToolMode === 'curve') it.curve = parseFloat(val);
    else if(currentToolMode === 'spacing') it.spacing = val;
    else if(currentToolMode === 'opacity') it.opacity = val;
    updateCanvas();
}
function closePopup() { document.getElementById('toolPopup').style.display = 'none'; }
function updateItemColor(h) { let it = state.items.find(i => i.id === state.activeId); if(it) { it.color = h; updateCanvas(); } }
function updateTextContent(v) { let it = state.items.find(i => i.id === state.activeId); if(it) { it.content = v; updateCanvas(); } }
function removeItem() { state.items = state.items.filter(i => i.id !== state.activeId); state.activeId = null; updateCanvas(); updateSummary(); }
function toolZIndex(dir) { let it = state.items.find(i => i.id === state.activeId); if(it) { it.z += dir; updateCanvas(); } }

function startDrag(e, id) { setActive(id); dragInfo = { dragging: true, id: id, startX: e.clientX, startY: e.clientY }; let it = state.items.find(i => i.id === id); dragInfo.origX = it.x; dragInfo.origY = it.y; closePopup(); }
document.addEventListener('mousemove', (e) => { if(!dragInfo.dragging) return; let it = state.items.find(i => i.id === dragInfo.id); it.x = dragInfo.origX + (e.clientX - dragInfo.startX); it.y = dragInfo.origY + (e.clientY - dragInfo.startY); updateCanvas(); });
document.addEventListener('mouseup', () => dragInfo.dragging = false);

function updateSummary() {
    const check = document.getElementById('termsCheck').checked;
    document.getElementById('request-btn').disabled = !(state.size && state.items.length > 0 && check);
}

function getTintedShirtImage(imgElement, colorHex) {
    const canvas = document.createElement('canvas');
    canvas.width = imgElement.naturalWidth || imgElement.width;
    canvas.height = imgElement.naturalHeight || imgElement.height;
    if(canvas.width === 0) return imgElement.src;

    const ctx = canvas.getContext('2d');
    ctx.drawImage(imgElement, 0, 0, canvas.width, canvas.height);
    ctx.globalCompositeOperation = 'multiply';
    ctx.fillStyle = colorHex;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.globalCompositeOperation = 'destination-in';
    ctx.drawImage(imgElement, 0, 0, canvas.width, canvas.height);
    
    return canvas.toDataURL('image/png');
}

const wait = (ms) => new Promise(res => setTimeout(res, ms));

async function requestShirt() {
    const btn = document.getElementById('request-btn');
    const originalView = state.view;
    btn.disabled = true;
    btn.innerText = "CAPTURING SIDES...";

    try {
        const views = ['f', 'b', 'ls', 'rs'];
        const snapshots = {};

        for (let v of views) {
            await new Promise((resolve) => {
                const imgElement = document.getElementById('shirtBaseImg');
                imgElement.onload = resolve;
                imgElement.onerror = resolve;
                setView(v); 
            });
            
            await wait(800); 

            const baseImg = document.getElementById('shirtBaseImg');
            const tintedDataUrl = getTintedShirtImage(baseImg, state.shirtColor);

            const capture = await html2canvas(document.getElementById('canvas'), {
                useCORS: true,
                scale: 1.5,
                backgroundColor: "#ffffff",
                onclone: (clonedDoc) => {
                    const tint = clonedDoc.getElementById('shirtTint');
                    const clonedBase = clonedDoc.getElementById('shirtBaseImg');
                    if (tint) tint.style.display = 'none';
                    if (clonedBase && tintedDataUrl) {
                        clonedBase.src = tintedDataUrl;
                    }
                }
            });
            snapshots[v] = capture.toDataURL("image/png");
        }

        setView(originalView);

        // BAGO: Ipapasa na ang pangalan ng kulay (e.g. "Purple") papunta sa database!
        const orderData = {
            order_type: 'Custom T-Shirt',
            size: state.size + " (" + MEASUREMENTS[state.size] + ")",
            custom_image: JSON.stringify(snapshots),
            items_json: JSON.stringify(state.items),
            shirt_color: state.shirtColorName 
        };

        const response = await fetch('save_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(orderData)
        });

        const result = await response.json();
        if (result.success) {
            alert("Order Submitted! Please wait for the admin to provide the final quotation.");
            window.location.href = "my_orders.php";
        } else {
            alert("Error: " + result.message);
            btn.disabled = false;
            btn.innerText = "REQUEST SHIRT";
        }
    } catch (err) {
        console.error(err);
        btn.disabled = false;
        btn.innerText = "REQUEST SHIRT";
        alert("Submission failed. Please try again.");
    }
}

function renderAssets() {
    document.getElementById('graphic-list').innerHTML = [1,2,3,4,5].map(i => `
        <div class="img-item" onclick="addGraphicItem('img/custom/tshirt/graphic/graphic${i}.png')">
            <img src="img/custom/tshirt/graphic/graphic${i}.png">
        </div>`).join('');
}

renderAssets();
</script>
</body>
</html>