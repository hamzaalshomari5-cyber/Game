// زر الرجوع
function goBack() {
  // إذا في صفحة سابقة بنفس الموقع، ارجع لها — وإلا روح للرئيسية
  if (document.referrer && document.referrer.indexOf(location.host) !== -1 && history.length > 1) {
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
// تنسيق سعر (المخزن دائماً ل.س)
function fmtPrice(syp) {
  if (typeof CUR !== 'undefined' && CUR === 'usd')
    return (syp / USD_RATE).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' $';
  return Number(syp).toLocaleString() + ' ل.س';
}

function copyText(t) {
  navigator.clipboard && navigator.clipboard.writeText(t);
}

// ===== مودال الشراء =====
let curPrice = 0, qMin = 1, qMax = 0, needVerify = false, verified = false, softPass = false;

function openBuy(card) {
  if (card.classList.contains('oos')) return;
  curPrice = parseFloat(card.dataset.price) || 0;
  qMin = parseInt(card.dataset.qmin) || 1;
  qMax = parseInt(card.dataset.qmax) || 0;
  document.getElementById('mName').textContent = card.dataset.name;
  document.getElementById('mPrice').textContent = fmtPrice(curPrice);
  document.getElementById('mDesc').textContent = card.dataset.desc || '';
  const qty = document.getElementById('mQty');
  qty.value = qMin; qty.min = qMin;
  if (qMax > 0) qty.max = qMax; else qty.removeAttribute('max');
  // حقل المعرف حسب متطلبات المنتج من API
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
  updateTotal();
}
function updateTotal() {
  const q = parseInt(document.getElementById('mQty').value) || qMin;
  document.getElementById('mTotal').textContent = fmtPrice(curPrice * q);
}
document.addEventListener('input', e => { if (e.target.id === 'mQty') updateTotal(); });
document.addEventListener('click', e => { if (e.target.id === 'buyModal') closeBuy(); });

// إعادة ضبط التحقق عند تغيير الـ ID
function resetVerify() {
  if (!needVerify) return;
  verified = false; softPass = false;
  const vb = document.getElementById('mVerify');
  vb.style.display = 'none';
  document.getElementById('mBuyBtn').textContent = 'تحقق من الاسم 🔍';
}

// التحقق من اسم اللاعب (ببجي / فري فاير)
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
  // منتجات ببجي/فري فاير: لازم تحقق من الاسم أول
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
        qty: parseInt(document.getElementById('mQty').value) || 1,
        player_id: document.getElementById('mPlayer').value.trim(),
      }),
    });
    const d = await res.json();
    if (d.login) { location.href = '/auth.php'; return; }
    msg.textContent = d.msg;
    msg.className = 'm-msg ' + (d.ok ? 'ok' : 'no');
    if (d.ok) setTimeout(() => location.href = '/orders.php', 1800);
  } catch (err) {
    msg.textContent = 'خطأ في الاتصال — حاول مرة ثانية';
    msg.className = 'm-msg no';
  }
  btn.disabled = false;
}

// المفضلة ❤
async function toggleFav(ev, pid, btn) {
  ev.stopPropagation();
  if (typeof IS_LOGGED !== 'undefined' && !IS_LOGGED) { location.href = '/auth.php'; return; }
  try {
    const res = await fetch('/index.php?action=fav&pid=' + encodeURIComponent(pid));
    const d = await res.json();
    if (d.login) { location.href = '/auth.php'; return; }
    if (d.ok) btn.classList.toggle('on', d.fav);
  } catch (e) {}
}
