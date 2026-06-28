// زر الرجوع الذكي
function goBack() {
  const openModal = document.querySelector('.modal.show');
  if (openModal) { openModal.classList.remove('show'); return; }
  const sb = document.getElementById('sidebar');
  if (sb && sb.classList.contains('open')) { toggleSidebar(); return; }
  if (document.referrer && document.referrer.indexOf(location.host) !== -1) {
    history.back();
  } else if (history.length > 1) {
    history.back();
  } else {
    location.href = '/index.php';
  }
}

// السايدبار
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('show');
}

// الوضع الداكن/الفاتح
function toggleTheme() {
  const light = document.body.classList.toggle('light');
  document.cookie = 'theme=' + (light ? 'light' : 'dark') + ';path=/;max-age=31536000';
}
(function () {
  if (document.cookie.includes('theme=light')) document.body.classList.add('light');
})();

// تبديل العملة (ل.س / $)
function toggleCurrency() {
  const cur = (typeof CUR !== 'undefined' && CUR === 'usd') ? 'syp' : 'usd';
  document.cookie = 'currency=' + cur + ';path=/;max-age=31536000';
  location.reload();
}

// تنسيق سعر
function fmtPrice(syp) {
  if (typeof CUR !== 'undefined' && CUR === 'usd')
    return (syp / USD_RATE).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' $';
  return Number(syp).toLocaleString() + ' ل.س';
}

function copyText(t) {
  navigator.clipboard && navigator.clipboard.writeText(t);
}

// تحديث عرض الرصيد حسب العملة المختارة
function updateBalanceDisplay() {
  const usd = (typeof CUR !== 'undefined' && CUR === 'usd');
  document.querySelectorAll('.bal-amount').forEach(function(el) {
    const syp = parseFloat(el.dataset.syp || '0');
    el.textContent = usd
      ? (syp / USD_RATE).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' $'
      : Number(syp).toLocaleString() + ' ل.س';
  });
  document.querySelectorAll('.bal-amount-big').forEach(function(el) {
    const syp = parseFloat(el.dataset.syp || '0');
    el.innerHTML = usd
      ? (syp / USD_RATE).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' <span>$</span>'
      : Number(syp).toLocaleString() + ' <span>ل.س</span>';
  });
}
document.addEventListener('DOMContentLoaded', updateBalanceDisplay);

// ===== مودال الشراء =====
let curPrice = 0, qMin = 1, qMax = 0, needVerify = false, verified = false, softPass = false;

function openBuy(card) {
  if (card.classList.contains('oos')) return;
  curPrice = parseFloat(card.dataset.price) || 0;
  qMin = parseInt(card.dataset.qmin) || 1;
  qMax = parseInt(card.dataset.qmax) || 0;
  const pType = card.dataset.type || '';
  
  // قراءة اسم المنتج من الكرت مباشرة لمنع مشاكل الـ PHP والشاشة البيضاء
  const pNameEl = card.querySelector('.p-name');
  const pName = pNameEl ? pNameEl.textContent.toLowerCase() : (card.dataset.name || '').toLowerCase();
  
  // فحص ذكي للأقسام لتمكين الكميات فقط في الرصيد والتواصل
  const isBalanceOrSocial = pType === 'amount' || 
                            pName.includes('رصيد') || 
                            pName.includes('متابعين') || 
                            pName.includes('لايكات') || 
                            pName.includes('انستغرام') || 
                            pName.includes('فيسبوك') || 
                            pName.includes('تيك') || 
                            pName.includes('تواصل') || 
                            pName.includes('سوشيال');

  document.getElementById('mName').textContent = card.dataset.name || (pNameEl ? pNameEl.textContent : '');
  document.getElementById('mPrice').textContent = fmtPrice(curPrice);
  document.getElementById('mDesc').textContent = card.dataset.desc || '';
  const qty = document.getElementById('mQty');
  const qtyRow = document.getElementById('mQtyRow');
  const qtySelectRow = document.getElementById('mQtySelectRow');
  const qtySelect = document.getElementById('mQtySelect');

  if (isBalanceOrSocial) {
    const fixedQty = (pType === 'specificPackage');
    if (fixedQty) {
      const startVal = 1.92, step = 0.96, count = 2502; // ممتدة لتصل إلى قيمة 2403 تماماً
      qtySelect.innerHTML = '';
      for (let i = 0; i < count; i++) {
        const v = Math.round((startVal + step * i) * 100) / 100;
        const opt = document.createElement('option');
        opt.value = v;
        opt.textContent = v;
        qtySelect.appendChild(opt);
      }
      qtySelect.value = startVal;
      qty.value = startVal; qMin = startVal; qMax = 0;
      if (qtyRow) qtyRow.style.display = 'none';
      if (qtySelectRow) qtySelectRow.style.display = '';
    } else {
      qty.value = qMin; qty.min = qMin; qty.step = 1;
      if (qMax > 0) qty.max = qMax; else qty.removeAttribute('max');
      const hint = document.getElementById('mQtyHint');
      if (hint) hint.textContent = qMax > 0 ? ('(من ' + qMin.toLocaleString() + ' إلى ' + qMax.toLocaleString() + ')') : ('(الحد الأدنى ' + qMin.toLocaleString() + ')');
      if (qtyRow) qtyRow.style.display = '';
      if (qtySelectRow) qtySelectRow.style.display = 'none';
    }
  } else {
    // إخفاء حقول الكمية نهائياً في الألعاب وتثبيتها على 1
    qty.value = qMin;
    if (qtyRow) qtyRow.style.display = 'none';
    if (qtySelectRow) qtySelectRow.style.display = 'none';
  }

  needVerify = card.dataset.verify === '1';
  verified = false; softPass = false;
  const vb = document.getElementById('mVerify');
  vb.style.display = 'none'; vb.textContent = '';
  document.getElementById('mBuyBtn').textContent = needVerify ? 'تحقق من الاسم 🔍' : 'شراء';
  const param = card.dataset.param || '';
  const wrap = document.getElementById('mPlayerWrap');
  if (param) {
    wrap.style.display = '';
    document.getElementById('mPlayerLabel').textContent = param;
    document.getElementById('mPlayer').placeholder = param;
  } else {
    wrap.style.display = 'none';
  }
  document.getElementById('mPlayer').value = '';
  document.getElementById('mMsg').textContent = '';
  document.getElementById('mMsg').className = 'm-msg';
  document.getElementById('buyModal').dataset.pid = card.dataset.id;
  updateTotal();
  document.getElementById('buyModal').classList.add('show');
}

function closeBuy() { document.getElementById('buyModal').classList.remove('show'); }

function qtyStep(d) {
  const i = document.getElementById('mQty');
  let v = (parseInt(i.value) || qMin) + d;
  if (v < qMin) v = qMin;
  if (qMax > 0 && v > qMax) v = qMax;
  i.value = v;
  i.classList.remove('invalid');
  updateTotal();
}

function onQtyType() {
  const i = document.getElementById('mQty');
  const v = parseInt(i.value);
  const invalid = i.value !== '' && (isNaN(v) || v < qMin || (qMax > 0 && v > qMax));
  i.classList.toggle('invalid', invalid);
  updateTotal();
}

function clampQty() {
  const i = document.getElementById('mQty');
  let v = parseInt(i.value);
  if (isNaN(v) || v < qMin) v = qMin;
  if (qMax > 0 && v > qMax) v = qMax;
  i.value = v;
  i.classList.remove('invalid');
  updateTotal();
}

function onQtySelect() {
  const sel = document.getElementById('mQtySelect');
  const v = parseFloat(sel.value) || qMin;
  document.getElementById('mQty').value = v;
  updateTotal();
}

function getQty() {
  const selRow = document.getElementById('mQtySelectRow');
  if (selRow && selRow.style.display !== 'none') {
    return parseFloat(document.getElementById('mQtySelect').value) || qMin;
  }
  const input = document.getElementById('mQty');
  let v = parseInt(input.value);
  if (isNaN(v) || v < qMin) v = qMin;
  if (qMax > 0 && v > qMax) v = qMax;
  if (String(v) !== input.value) input.value = v;
  return v;
}

function allDiscounts() { return (typeof MY_DISCOUNTS !== 'undefined' && Array.isArray(MY_DISCOUNTS)) ? MY_DISCOUNTS : []; }
function activeDiscount() { const ds = allDiscounts(); return ds.length ? ds[0] : null; }
function fmtNum(n) { n = parseFloat(n) || 0; return (n % 1 === 0) ? String(n) : String(Math.round(n * 100) / 100); }
function discLabel(d) { return d.type === 'percent' ? (fmtNum(d.amount) + '%') : fmtPrice(parseFloat(d.amount)); }

function discValue(type, amount, total) {
  let v = (type === 'percent') ? total * (amount / 100) : amount;
  if (v > total) v = total;
  return Math.round(v);
}

function updateTotal() {
  const q = getQty();
  const base = Math.round(curPrice * q);
  const d = activeDiscount();
  const line  = document.getElementById('mDiscLine');
  const oldT  = document.getElementById('mOldTotal');
  const oldP  = document.getElementById('mOldPrice');
  if (d) {
    const disc = discValue(d.type, parseFloat(d.amount), base);
    const final = Math.max(1, base - disc);
    document.getElementById('mTotal').textContent = fmtPrice(final);
    if (oldT) { oldT.style.display = ''; oldT.textContent = fmtPrice(base); }
    if (d.type === 'percent') {
      const unitNew = Math.max(1, Math.round(curPrice * (1 - parseFloat(d.amount) / 100)));
      document.getElementById('mPrice').textContent = fmtPrice(unitNew);
      if (oldP) { oldP.style.display = ''; oldP.textContent = fmtPrice(curPrice); }
    } else {
      document.getElementById('mPrice').textContent = fmtPrice(curPrice);
      if (oldP) oldP.style.display = 'none';
    }
    if (line) {
      line.style.display = '';
      line.className = 'm-disc ok';
      line.textContent = '🎁 خصمك الدائم ' + discLabel(d) + ' — وفّرت ' + fmtPrice(disc);
    }
  } else {
    document.getElementById('mTotal').textContent = fmtPrice(base);
    document.getElementById('mPrice').textContent = fmtPrice(curPrice);
    if (oldT) oldT.style.display = 'none';
    if (oldP) oldP.style.display = 'none';
    if (line) line.style.display = 'none';
  }
}

document.addEventListener('input', e => { if (e.target.id === 'mQty' || e.target.id === 'mPlayer') updateTotal(); });
document.addEventListener('click', e => { if (e.target.id === 'buyModal') closeBuy(); });

function resetVerify() {
  if (!needVerify) return;
  verified = false; softPass = false;
  const vb = document.getElementById('mVerify');
  vb.style.display = 'none';
  document.getElementById('mBuyBtn').textContent = 'تحقق من الاسم 🔍';
}

async function verifyName() {
  const modal = document.getElementById('buyModal');
  const btn = document.getElementById('mBuyBtn');
  const vb = document.getElementById('mVerify');
  const player = document.getElementById('mPlayer').value.trim();
  const msg = document.getElementById('mMsg');
  if (!player) { msg.textContent = 'أدخل ID اللاعب أولاً'; msg.className = 'm-msg no'; return; }
  btn.disabled = true;
  msg.className = 'm-msg'; msg.textContent = '';
  vb.style.display = ''; vb.className = 'verify-box'; vb.textContent = 'جارٍ التحقق من الاسم... ⏳';
  try {
    const res = await fetch('/check_name.php?player=' + encodeURIComponent(player) + '&product=' + encodeURIComponent(modal.dataset.pid));
    const d = await res.json();
    if (d.ok) {
      verified = true;
      vb.className = 'verify-box ok';
      vb.textContent = '👤 اسم اللاعب: ' + d.name + ' — إذا الاسم صحيح اضغط شراء';
      btn.textContent = 'شراء ✅';
    } else if (d.soft) {
      softPass = true;
      vb.className = 'verify-box warn';
      vb.textContent = '⚠️ ' + d.msg + ' — تأكد من الـ ID بنفسك ثم اضغط شراء';
      btn.textContent = 'شراء';
    } else {
      vb.className = 'verify-box no';
      vb.textContent = '❌ ' + d.msg;
    }
  } catch (e) {
    softPass = true;
    vb.className = 'verify-box warn';
    vb.textContent = '⚠️ تعذّر التحقق — تأكد من الـ ID بنفسك ثم اضغط شراء';
    btn.textContent = 'شراء';
  }
  btn.disabled = false;
}

async function submitBuy() {
  const modal = document.getElementById('buyModal');
  const msg = document.getElementById('mMsg');
  const btn = document.getElementById('mBuyBtn');

  if (typeof IS_LOGGED !== 'undefined' && !IS_LOGGED) {
    location.href = '/auth.php';
    return;
  }
  if (needVerify && !verified && !softPass) { verifyName(); return; }
  btn.disabled = true;
  msg.className = 'm-msg';
  msg.textContent = 'جارٍ إرسال الطلب...';
  try {
    const res = await fetch('/buy.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        product_id: modal.dataset.pid,
        qty: getQty(),
        player_id: document.getElementById('mPlayer').value.trim(),
      }),
    });
    const d = await res.json();
    if (d.login) { location.href = '/auth.php'; return; }
    msg.textContent = d.msg + (d.eta ? ' — ' + d.eta : '');
    msg.className = 'm-msg ' + (d.ok ? 'ok' : 'no');
    if (d.ok) setTimeout(() => location.href = '/orders.php', 2600);
  } catch (err) {
    msg.textContent = 'خطأ في الاتصال — حاول مرة ثانية';
    msg.className = 'm-msg no';
  }
  btn.disabled = false;
}

/* ===== عجلة الحظ الكود الأصلي المدمج للـ Wheel ===== */
async function spinWheel() {
  const btn = document.getElementById('spinBtn');
  const wheel = document.getElementById('wheelImg');
  const msg = document.getElementById('wheelMsg');
  if (!btn || !wheel || !msg) return;
  btn.disabled = true;
  msg.style.display = 'none';
  try {
    const res = await fetch('/wheel_spin.php', { method: 'POST' });
    const d = await res.json();
    if (d.login) { location.href = '/auth.php'; return; }
    if (!d.ok) { msg.textContent = d.msg; msg.className = 'alert no'; msg.style.display = 'block'; btn.disabled = false; return; }
    const degs = [30, 90, 150, 210, 270, 330];
    const idx = d.slice_index !== undefined ? d.slice_index : 0;
    const targetDeg = degs[idx] || 30;
    const extraRot = 3600; 
    const finalRot = extraRot + (360 - targetDeg);
    wheel.style.transition = 'transform 5s cubic-bezier(0.25, 0.1, 0.25, 1)';
    wheel.style.transform = 'rotate(' + finalRot + 'deg)';
    setTimeout(function () {
      msg.textContent = d.msg;
      msg.className = 'alert ' + (d.value > 0 ? 'ok' : '');
      msg.style.display = 'block';
      if (d.value > 0) {
        const bal = document.querySelector('.bal-amount');
        if (bal) { const cur = parseInt(bal.dataset.syp || '0') + d.value; bal.dataset.syp = cur; bal.textContent = cur.toLocaleString() + ' ل.س'; }
      }
      showWheelTimer(86400);
    }, 5000);
  } catch (e) {
    msg.textContent = 'خطأ بالاتصال، حاول مجدداً'; msg.className = 'alert no'; msg.style.display = 'block';
    btn.disabled = false;
  }
}

function showWheelTimer(secs) {
  const t = document.getElementById('wheelTimer');
  const btn = document.getElementById('spinBtn');
  if (!t) return;
  t.style.display = 'block';
  if (btn) btn.style.display = 'none';
  function tick() {
    if (secs <= 0) { location.reload(); return; }
    const h = Math.floor(secs / 3600), m = Math.floor((secs % 3600) / 60), s = secs % 60;
    t.textContent = '⏳ الدوران التالي بعد: ' + h + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    secs--;
    setTimeout(tick, 1000);
  }
  tick();
}
