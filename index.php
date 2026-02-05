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

// fetch menu items
$menu_res = mysqli_query($conn, "SELECT * FROM MenuItem");

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
        <nav class="site-nav"><a href="index.php">Home</a><a href="admin_login.php">Admin</a></nav>
    </div>
</header>

<div class="container">
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
                    <label>Time</label>
                    <input type="time" name="time" min="10:00" max="22:00" required>
                </div>
            </div>

            <div>
                <label>Number of People</label>
                <input type="number" name="num_people" min="1" max="300" placeholder="Number of People" required>
            </div>

            <div>
                <label>Pre-order Menu (optional)</label>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <select name="menuitem[]" multiple id="menuSelect">
                    <?php while($row = mysqli_fetch_assoc($menu_res)): ?>
                        <option value="<?php echo (int)$row['menuitem_id']; ?>"><?php echo htmlspecialchars($row['name']); ?> - RM<?php echo htmlspecialchars($row['price']); ?></option>
                    <?php endwhile; ?>
                </select>
                <div class="small footer-note">Tip: hold Ctrl (or Cmd) to select multiple items.</div>
            </div>

            <div class="summary" id="menuSummary" style="display:none"></div>

            <div style="margin-top:12px; display:flex; gap:10px; align-items:center">
                <button class="btn primary" type="submit" id="reserveBtn">Reserve Now</button>
                <button class="btn secondary" type="button" id="previewBtn">Preview</button>
            </div>

            <div class="footer-note">Open hours: 10:00 - 22:00 • Max 300 people</div>
        </form>
    </div>
</div>

<div id="confirmationMessage" style="max-width:var(--max-width); margin:12px auto; padding:0 20px"></div>

<!-- Modal (hidden) -->
<div id="reservationModalBackdrop" class="modal-backdrop" style="display:none" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <h3 id="modalTitle">Reservation Confirmed</h3>
        <div id="modalBody">
            <p>Reservation ID: <strong id="mResId"></strong></p>
            <p>Tables: <strong id="mTables"></strong></p>
        </div>
        <div class="modal-actions">
            <button class="btn secondary" id="modalClose">Close</button>
            <button class="btn primary" id="modalPrint">Print</button>
        </div>
    </div>
</div>

<script>
// helper: render selected menu items
function renderMenuSummary(){
    const sel = document.getElementById('menuSelect');
    const out = document.getElementById('menuSummary');
    const items = Array.from(sel.selectedOptions).map(o => o.textContent.trim());
    if(items.length === 0){ out.style.display='none'; out.innerHTML=''; return; }
    out.style.display='block';
    out.innerHTML = '<strong>Selected items:</strong><br>' + items.join('<br>');
}

document.getElementById('menuSelect').addEventListener('change', renderMenuSummary);

document.getElementById('previewBtn').addEventListener('click', function(){ renderMenuSummary(); window.scrollTo({top:0, behavior:'smooth'}); });

document.getElementById('reservationForm').addEventListener('submit', async function(e){
    e.preventDefault();
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
    b.style.display='none'; b.setAttribute('aria-hidden','true');
});
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
