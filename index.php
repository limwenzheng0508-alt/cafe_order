<?php
session_start();
if(empty($_SESSION['csrf_token'])){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$user_name = $_SESSION['user_name'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';

include 'db.php';
include 'qr_helper.php';

// Cleanup expired QR codes (older than 1 day) - runs once per page load
if (rand(1, 10) === 1) { // Run cleanup ~10% of the time to avoid overload
    cleanup_expired_qr_codes(1);
}

// Fetch menu items once
$menu_items = mysqli_query($conn, "SELECT * FROM MenuItem ORDER BY name");
$menu_data = [];
while($item = mysqli_fetch_assoc($menu_items)){
    $menu_data[] = $item;
}
// Reset pointer for reuse
$menu_items = $menu_data;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Café Reservation</title>
<link rel="stylesheet" href="assets/style.css">
<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<!-- Header -->
<header class="site-header">
    <div class="container">
        <div class="logo">
            <!-- simple SVG logo -->
            <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                <defs><linearGradient id="g" x1="0" x2="1"><stop offset="0" stop-color="#06b6d4"/><stop offset="1" stop-color="#3b82f6"/></linearGradient></defs>
                <circle cx="32" cy="24" r="12" fill="url(#g)" />
                <rect x="12" y="36" width="40" height="14" rx="6" fill="#fefefe" opacity="0.95" />
            </svg>
            <span>Café Reservations</span>
        </div>
        <nav class="site-nav">
            <a href="index.php">Home</a>
            <?php if(!empty($_SESSION['user_id'])): ?>
                <a href="my_reservations.php">My Reservations</a>
                <a href="history.php">History</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="admin_login.php">Admin</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<div class="container">
    <!-- Menu Display Section -->
    <div class="card menu-section">
        <div class="meta">
            <h2>Our Menu</h2>
            <p style="color: var(--muted); font-size: 14px;">Browse our selection of beverages and snacks</p>
        </div>
        <div class="menu-grid">
            <?php 
            foreach($menu_data as $item): 
            ?>
                <div class="menu-item">
                    <div class="menu-item-header">
                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                        <span class="menu-price">RM<?php echo htmlspecialchars($item['price']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="meta">
            <h2>Reserve Your Table</h2>
            <div>
                <?php if(!empty($_SESSION['user_id'])): ?>
                    <small class="small">已登入為 <?php echo htmlspecialchars($user_name); ?> — <a href="logout.php">登出</a></small>
                <?php else: ?>
                    <small class="small"><a href="login.php">登入</a> or <a href="register.php">註冊</a> to save reservations.</small>
                <?php endif; ?>
            </div>
        </div>

        <form id="reservationForm">
            <div class="form-grid">
                <div>
                    <label>Name</label>
                    <input type="text" name="name" placeholder="Your Name" required value="<?php echo htmlspecialchars($user_name); ?>">
                </div>
                <div>
                    <label>Email (optional)</label>
                    <input type="email" name="email" placeholder="Email (optional)" value="<?php echo htmlspecialchars($user_email); ?>">
                </div>
            </div>

            <div class="form-row">
                <div style="flex:0 0 35%;">
                    <label>Country</label>
                    <select name="country" id="countrySelect" required>
                        <option value="MY">Malaysia (+60)</option>
                        <option value="US">United States (+1)</option>
                        <option value="GB">United Kingdom (+44)</option>
                        <option value="OTHER">Other</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label>Phone</label>
                    <input type="tel" name="phone" id="phoneInput" placeholder="Phone Number" required value="<?php echo htmlspecialchars($user_phone); ?>">
                </div>
            </div>

            <div class="form-grid">
                <?php
                $minDate = (new DateTime('today'))->format('Y-m-d');
                $maxDate = (new DateTime('today'))->modify('+365 days')->format('Y-m-d');
                ?>
                <div>
                    <label>Date</label>
                    <input type="date" name="date" required min="<?php echo $minDate; ?>" max="<?php echo $maxDate; ?>">
                </div>
                <div>
                    <label>Time (AM/PM)</label>
                    <div style="display: flex; gap: 10px;">
                        <select id="hour" name="hour" required style="flex: 1;">
                            <option value="">Hour</option>
                            <option value="10">10 AM</option>
                            <option value="11">11 AM</option>
                            <option value="12">12 PM</option>
                            <option value="13">1 PM</option>
                            <option value="14">2 PM</option>
                            <option value="15">3 PM</option>
                            <option value="16">4 PM</option>
                            <option value="17">5 PM</option>
                            <option value="18">6 PM</option>
                            <option value="19">7 PM</option>
                            <option value="20">8 PM</option>
                            <option value="21">9 PM</option>
                        </select>
                        <select id="minute" name="minute" required style="flex: 1;">
                            <option value="">Minute</option>
                            <option value="00">:00</option>
                            <option value="15">:15</option>
                            <option value="30">:30</option>
                            <option value="45">:45</option>
                        </select>
                    </div>
                    <input type="hidden" name="time" id="timeInput">
                </div>
                <script>
                function updateTimeInput() {
                    const hour = document.getElementById('hour').value;
                    const minute = document.getElementById('minute').value;
                    const timeInput = document.getElementById('timeInput');
                    
                    if (hour && minute) {
                        // Validate that 9 PM can only have times up to 45 minutes
                        if (hour === '21' && minute !== '00' && minute !== '15' && minute !== '30' && minute !== '45') {
                            timeInput.value = '';
                        } else if (hour === '21' && minute === '45') {
                            // 9:45 PM (21:45) is the last allowed time
                            timeInput.value = `${hour}:${minute}`;
                        } else if (hour < '22') {
                            timeInput.value = `${hour}:${minute}`;
                        }
                    } else {
                        timeInput.value = '';
                    }
                }
                
                document.getElementById('hour').addEventListener('change', updateTimeInput);
                document.getElementById('minute').addEventListener('change', updateTimeInput);
                </script>
            </div>

            <div>
                <label>Number of People</label>
                <select name="num_people" id="numPeople" required>
                    <option value="">Select number of people</option>
                    <option value="1">1 person</option>
                    <option value="2">2 people</option>
                    <option value="3">3 people</option>
                    <option value="4">4 people</option>
                    <option value="5">5 people</option>
                    <option value="6">6 people</option>
                    <option value="7">7 people</option>
                    <option value="8">8 people</option>
                    <option value="9">9 people</option>
                    <option value="10">10+ people</option>
                </select>
            </div>

            <!-- Capacity Check Status -->
            <div id="capacityStatus" style="margin-top:12px; padding:12px; border-radius:10px; display:none; font-size:14px;">
            </div>

            <div>
                <label>Pre-order Menu (optional)</label>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                
                <!-- Menu Items with +/- buttons -->
                <div id="menuOrderContainer" style="background: #f9fafb; border-radius: 10px; padding: 16px; margin-bottom: 12px">
                    <div id="menuItemsList" style="max-height: 400px; overflow-y: auto">
                        <?php foreach($menu_data as $item): ?>
                            <div class="menu-order-item" data-item-id="<?php echo (int)$item['menuitem_id']; ?>" data-item-price="<?php echo htmlspecialchars($item['price']); ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #fff; border-radius: 8px; margin-bottom: 8px; border: 1px solid #e5e7eb">
                                <div style="flex: 1">
                                    <h4 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 600"><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p style="margin: 0; font-size: 12px; color: var(--muted)">RM<?php echo htmlspecialchars($item['price']); ?></p>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px">
                                    <button type="button" class="qty-btn minus-btn" onclick="decreaseQty(this)" style="width: 32px; height: 32px; padding: 0; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer; font-weight: 600; color: #ef4444; font-size: 18px">−</button>
                                    <span class="item-qty" style="min-width: 30px; text-align: center; font-weight: 600; font-size: 14px">0</span>
                                    <button type="button" class="qty-btn plus-btn" onclick="increaseQty(this)" style="width: 32px; height: 32px; padding: 0; border: 1px solid #ddd; background: linear-gradient(90deg, var(--primary-1), var(--primary-2)); color: #fff; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 18px">+</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order Summary -->
                <div id="orderSummary" style="background: linear-gradient(135deg, #e0f2fe, #f0f9ff); border: 1px solid #06b6d4; border-radius: 8px; padding: 12px; margin-bottom: 16px; display: none">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px">
                        <strong style="color: #0c4a6e">订单摘要 (Order Summary)</strong>
                        <span id="totalItems" style="background: #06b6d4; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600">0 items</span>
                    </div>
                    <div id="selectedItemsList" style="font-size: 12px; color: #0c4a6e; margin-bottom: 8px"></div>
                    <div style="border-top: 1px solid rgba(6,182,212,0.3); padding-top: 8px; display: flex; justify-content: space-between">
                        <strong style="color: #0c4a6e">总计 (Total):</strong>
                        <strong id="totalPrice" style="color: #0c4a6e">RM0.00</strong>
                    </div>
                </div>

                <!-- Order Actions -->
                <div style="display: flex; gap: 8px; margin-bottom: 16px">
                    <button type="button" id="cancelOrderBtn" onclick="cancelAllItems()" style="flex: 1; padding: 10px; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 6px; font-weight: 600; cursor: pointer; display: none">✕ Cancel All</button>
                    <button type="button" id="confirmOrderBtn" onclick="confirmMenuOrder()" style="flex: 1; padding: 10px; background: linear-gradient(90deg, var(--primary-1), var(--primary-2)); color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; display: none">✓ Confirm Order</button>
                </div>

                <!-- Hidden input to store selected items -->
                <input type="hidden" name="menuitem[]" id="selectedMenuItems" value="">
            </div>

            <div class="summary" id="menuSummary" style="display:none"></div>

            <div style="margin-top:12px; display:flex; gap:10px; align-items:center">
                <button class="btn primary" type="submit" id="reserveBtn">Reserve Now</button>
                <button class="btn secondary" type="button" id="previewBtn">Preview</button>
            </div>

            <div class="footer-note">Open hours: 10:00 AM - 9:45 PM • Max 10 people per reservation</div>
        </form>
    </div>
</div>

<div id="confirmationMessage" style="max-width:var(--max-width); margin:12px auto; padding:0 20px"></div>

<!-- QR Code Access Panel -->
<div id="qrAccessPanel" class="card" style="display:none; max-width:var(--max-width); margin:40px auto; padding:0 20px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f7ff 100%); border: 2px solid #06b6d4; border-left: 6px solid #06b6d4">
    <div style="padding: 25px">
        <h3 style="margin-top:0; color:#0c4a6e">✓ Reservation Saved</h3>
        <p style="color:#0c4a6e; font-size:15px; margin:12px 0">Your QR code has been saved. You can access it anytime within 24 hours:</p>
        
        <div style="background:#fff; padding:16px; border-radius:8px; margin:15px 0; border:1px solid #06b6d4">
            <p style="font-size:12px; color:#475569; margin:0 0 8px 0"><strong>📱 Access Your QR Code:</strong></p>
            <a id="qrAccessLink" href="#" style="display:inline-block; padding:10px 16px; background:linear-gradient(90deg, #06b6d4, #3b82f6); color:#fff; border-radius:6px; text-decoration:none; font-weight:600; transition:all 0.2s ease; cursor:pointer" target="_blank">
                View QR Code
            </a>
            <button onclick="copyQRLink()" style="display:inline-block; margin-left:8px; padding:10px 16px; background:#f5f5f5; border:1px solid #ddd; color:#333; border-radius:6px; font-weight:600; cursor:pointer; transition:all 0.2s ease">
                📋 Copy Link
            </button>
        </div>
        
        <p style="font-size:12px; color:#0c4a6e; margin:12px 0">
            💡 <strong>Tip:</strong> If you're logged in, you can also view your QR code from "My Reservations" page anytime.
        </p>
        <p style="font-size:11px; color:#0c4a6e; margin-bottom:0">
            ⏰ Your QR code expires in 24 hours. Make sure to save it or screenshot it before it expires.
        </p>
    </div>
</div>

<!-- Modal (hidden) -->
<div id="reservationModalBackdrop" class="modal-backdrop" style="display:none" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <h3 id="modalTitle">Reservation Confirmed</h3>
        <div id="modalBody">
            <p>Reservation ID: <strong id="mResId"></strong></p>
            <p>Tables: <strong id="mTables"></strong></p>
            <div id="qrCodeContainer" style="display:none; margin:20px 0; text-align:center">
                <p style="font-size:13px; color:var(--muted); margin:0 0 10px 0">Show this QR code to the staff</p>
                <img id="qrCodeImage" alt="Reservation QR Code" style="max-width:250px; border-radius:8px; border:2px solid var(--primary-1)">
            </div>
            <p id="qrMessage" style="display:none; background:#e0f2fe; color:#0c4a6e; padding:10px; border-radius:6px; font-size:12px; text-align:center; border:1px solid #06b6d4">
                ⏰ Your QR code is available for 24 hours. Please print or save it before it expires.
            </p>
        </div>
        <div class="modal-actions">
            <button class="btn secondary" id="modalClose">Close</button>
            <button class="btn primary" id="modalPrint">Print</button>
        </div>
    </div>
</div>

<script>
// Menu ordering system
const menuQuantities = {};

function increaseQty(btn) {
    event.preventDefault();
    const item = btn.closest('.menu-order-item');
    const itemId = item.dataset.itemId;
    const qtySpan = item.querySelector('.item-qty');
    let qty = parseInt(qtySpan.textContent) || 0;
    qty++;
    qtySpan.textContent = qty;
    menuQuantities[itemId] = qty;
    updateOrderSummary();
}

function decreaseQty(btn) {
    event.preventDefault();
    const item = btn.closest('.menu-order-item');
    const itemId = item.dataset.itemId;
    const qtySpan = item.querySelector('.item-qty');
    let qty = parseInt(qtySpan.textContent) || 0;
    if(qty > 0) {
        qty--;
        qtySpan.textContent = qty;
        menuQuantities[itemId] = qty;
        updateOrderSummary();
    }
}

function updateOrderSummary() {
    const selectedItems = Object.entries(menuQuantities).filter(([id, qty]) => qty > 0);
    const orderSummary = document.getElementById('orderSummary');
    const selectedItemsList = document.getElementById('selectedItemsList');
    const confirmBtn = document.getElementById('confirmOrderBtn');
    const cancelBtn = document.getElementById('cancelOrderBtn');
    const totalItemsSpan = document.getElementById('totalItems');
    const totalPriceSpan = document.getElementById('totalPrice');
    
    if(selectedItems.length === 0) {
        orderSummary.style.display = 'none';
        confirmBtn.style.display = 'none';
        cancelBtn.style.display = 'none';
        return;
    }
    
    orderSummary.style.display = 'block';
    confirmBtn.style.display = 'block';
    cancelBtn.style.display = 'block';
    
    let html = '';
    let totalPrice = 0;
    let totalQty = 0;
    
    selectedItems.forEach(([itemId, qty]) => {
        const menuItem = document.querySelector(`[data-item-id="${itemId}"]`);
        const itemName = menuItem.querySelector('h4').textContent;
        const itemPrice = parseFloat(menuItem.dataset.itemPrice);
        const itemTotal = itemPrice * qty;
        totalPrice += itemTotal;
        totalQty += qty;
        
        html += `<div style="display: flex; justify-content: space-between">${itemName} <strong>×${qty}</strong> <strong>RM${itemTotal.toFixed(2)}</strong></div>`;
    });
    
    selectedItemsList.innerHTML = html;
    totalItemsSpan.textContent = totalQty + ' item' + (totalQty > 1 ? 's' : '');
    totalPriceSpan.textContent = 'RM' + totalPrice.toFixed(2);
}

function cancelAllItems() {
    if(!confirm('Clear all selected items?')) return;
    
    document.querySelectorAll('.menu-order-item .item-qty').forEach(span => {
        span.textContent = '0';
    });
    
    Object.keys(menuQuantities).forEach(key => menuQuantities[key] = 0);
    updateOrderSummary();
}

function confirmMenuOrder() {
    const selectedItems = Object.entries(menuQuantities).filter(([id, qty]) => qty > 0);
    if(selectedItems.length === 0) {
        alert('Please select items to continue');
        return;
    }
    
    // Build hidden input with selected items
    const itemsStr = selectedItems.map(([id, qty]) => {
        let result = id;
        for(let i = 1; i < qty; i++) {
            result += ',' + id;
        }
        return result;
    }).join(',');
    
    document.getElementById('selectedMenuItems').value = itemsStr;
    
    // Scroll to confirm button
    document.getElementById('reserveBtn').scrollIntoView({behavior: 'smooth', block: 'center'});
}

document.getElementById('previewBtn').addEventListener('click', function(){ 
    updateOrderSummary(); 
    window.scrollTo({top:0, behavior:'smooth'}); 
});

// Capacity check function
async function checkCapacity() {
    const dateInput = document.querySelector('input[name="date"]');
    const timeInput = document.getElementById('timeInput');
    const numPeopleSelect = document.getElementById('numPeople');
    const capacityStatus = document.getElementById('capacityStatus');
    
    const date = dateInput.value;
    const time = timeInput.value;
    const numPeople = numPeopleSelect.value;
    
    // Don't check if any field is empty
    if (!date || !time || !numPeople) {
        capacityStatus.style.display = 'none';
        return;
    }
    
    try {
        const response = await fetch('check_capacity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `date=${encodeURIComponent(date)}&time=${encodeURIComponent(time)}&num_people=${encodeURIComponent(numPeople)}`
        });
        
        const data = await response.json();
        capacityStatus.style.display = 'block';
        
        if (data.success) {
            capacityStatus.style.background = '#d1fae5';
            capacityStatus.style.border = '1px solid #6ee7b7';
            capacityStatus.style.color = '#047857';
            capacityStatus.innerHTML = `
                <strong>✓ 座位充足</strong><br>
                <small>已预订: ${data.booked_people} 人 / 总容量: ${data.max_capacity} 人<br>
                剩余座位: ${data.available_capacity} 人</small>
            `;
            document.getElementById('reserveBtn').disabled = false;
        } else if (data.full) {
            capacityStatus.style.background = '#fee2e2';
            capacityStatus.style.border = '1px solid #fca5a5';
            capacityStatus.style.color = '#991b1b';
            capacityStatus.innerHTML = `
                <strong>✗ 餐厅已满</strong><br>
                <small>${data.message}<br>
                已预订: ${data.booked_people} 人 / 总容量: ${data.max_capacity} 人</small>
            `;
            document.getElementById('reserveBtn').disabled = true;
        } else {
            capacityStatus.style.background = '#fef3c7';
            capacityStatus.style.border = '1px solid #fcd34d';
            capacityStatus.style.color = '#92400e';
            capacityStatus.innerHTML = `
                <strong>⚠ 无法预订</strong><br>
                <small>${data.message}</small>
            `;
            document.getElementById('reserveBtn').disabled = true;
        }
    } catch (error) {
        console.error('Capacity check error:', error);
        capacityStatus.style.display = 'none';
    }
}

// Add event listeners to trigger capacity check
document.querySelector('input[name="date"]').addEventListener('change', checkCapacity);
document.getElementById('hour').addEventListener('change', () => {
    setTimeout(checkCapacity, 100);
});
document.getElementById('minute').addEventListener('change', () => {
    setTimeout(checkCapacity, 100);
});
document.getElementById('numPeople').addEventListener('change', checkCapacity);

document.getElementById('reservationForm').addEventListener('submit', async function(e){
    e.preventDefault();
    
    // Check if user has selected menu items but not confirmed
    const selectedItems = Object.entries(menuQuantities).filter(([id, qty]) => qty > 0);
    if(selectedItems.length > 0){
        const confirmedItems = document.getElementById('selectedMenuItems').value;
        if(!confirmedItems){
            alert('Please click "Confirm Order" to confirm your menu selections');
            document.getElementById('confirmOrderBtn').scrollIntoView({behavior: 'smooth', block: 'center'});
            return;
        }
    }
    
    const btn = document.getElementById('reserveBtn');
    btn.disabled = true;
    btn.textContent = 'Reserving...';
    let formData = new FormData(this);
    // Client-side phone validation by selected country
    const country = document.getElementById('countrySelect').value;
    const phone = (document.getElementById('phoneInput').value || '').trim();
    function validPhone(country, phone){
        const digits = phone.replace(/[^0-9+]/g,'');
        if(country === 'MY'){
            return /^\+?6?0?[0-9]{9,10}$/.test(digits) || /^[0-9]{9,10}$/.test(digits);
        }
        if(country === 'US'){
            return /^\+?1?[2-9][0-9]{9}$/.test(digits) || /^[2-9][0-9]{9}$/.test(digits);
        }
        if(country === 'GB'){
            return /^\+?44?[0-9]{9,10}$/.test(digits) || /^[0-9]{9,10}$/.test(digits);
        }
        return digits.replace(/[^0-9]/g,'').length >= 7;
    }
    if(!validPhone(country, phone)){
        document.getElementById('confirmationMessage').innerText = 'Invalid phone number for selected country.';
        btn.disabled = false;
        btn.textContent = 'Reserve Now';
        return;
    }
    try{
        let res = await fetch('reserve.php', {method:'POST', body:formData});
        if(!res.ok) throw new Error('Network error');
        let result = await res.json();
            if(result.success){
                // show modal with details
                document.getElementById('mResId').innerText = result.reservation_id;
                document.getElementById('mTables').innerText = result.table_number;
                
                // Show QR code if available
                if(result.qr_code) {
                    const qrContainer = document.getElementById('qrCodeContainer');
                    const qrImage = document.getElementById('qrCodeImage');
                    const file_ext = result.qr_code.split('.').pop().toLowerCase();
                    
                    if (file_ext === 'png' || file_ext === 'jpg' || file_ext === 'jpeg') {
                        qrImage.src = result.qr_code;
                        qrContainer.style.display = 'block';
                    } else if (file_ext === 'txt') {
                        // For text file, show a message with a link
                        const textMsg = document.createElement('div');
                        textMsg.style.cssText = 'background: #f0f9ff; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center';
                        textMsg.innerHTML = '<p style="margin: 0; font-size: 13px; color: var(--muted)">Text-based QR code:</p>' +
                            '<a href="' + result.qr_view_url + '" style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: linear-gradient(90deg, #06b6d4, #3b82f6); color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600">View Full QR Code</a>';
                        qrContainer.appendChild(textMsg);
                        qrContainer.style.display = 'block';
                    }
                    // Show message about 24-hour expiration
                    document.getElementById('qrMessage').style.display = 'block';
                }
                
                const backdrop = document.getElementById('reservationModalBackdrop');
                backdrop.style.display = 'flex';
                backdrop.setAttribute('aria-hidden','false');
                document.getElementById('modalClose').focus();
                this.reset();
            } else {
                document.getElementById('confirmationMessage').innerText = result.message || 'Reservation failed';
            }
    } catch(err){
        document.getElementById('confirmationMessage').innerText = 'Error: ' + (err.message || 'Request failed');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Reserve Now';
    }
});
</script>

<script>
// Modal controls
document.getElementById('modalClose').addEventListener('click', function(){
    const b = document.getElementById('reservationModalBackdrop');
    b.style.display='none'; 
    b.setAttribute('aria-hidden','true');
    
    // Show QR code access panel after closing modal
    const resId = document.getElementById('mResId').innerText;
    if(resId){
        const qrPanel = document.getElementById('qrAccessPanel');
        const qrLink = document.getElementById('qrAccessLink');
        qrLink.href = 'view_qr.php?id=' + encodeURIComponent(resId);
        qrPanel.style.display = 'block';
        // Scroll to show the panel
        setTimeout(() => qrPanel.scrollIntoView({behavior: 'smooth', block: 'start'}), 100);
    }
});

// Copy QR code link to clipboard
function copyQRLink() {
    const link = document.getElementById('qrAccessLink').href;
    const fullLink = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) + '/' + link;
    
    navigator.clipboard.writeText(fullLink).then(() => {
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = '✓ Copied!';
        btn.style.background = '#d1fae5';
        btn.style.color = '#047857';
        setTimeout(() => {
            btn.textContent = originalText;
            btn.style.background = '';
            btn.style.color = '';
        }, 2000);
    }).catch(() => {
        alert('Failed to copy link. Try again.');
    });
}
document.getElementById('modalPrint').addEventListener('click', function(){
    const id = document.getElementById('mResId').innerText;
    const tables = document.getElementById('mTables').innerText;
    const content = `Reservation ID: ${id}\nTables: ${tables}`;
    const win = window.open('', '_blank');
    win.document.write('<pre>' + content + '</pre>');
    win.print();
    win.close();
});
</script>

</body>
