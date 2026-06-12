// السايدبار
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('show');
}

// الوضع الداكن/الفاتح (بدون localStorage — كوكي)
function toggleTheme() {
  const light = document.body.classList.toggle('light');
  document.cookie = 'theme=' + (light ? 'light' : 'dark') + ';path=/;max-age=31536000';
}
(function () {
  if (document.cookie.includes('theme=light')) document.body.classList.add('light');
})();

function copyText(t) {
  navigator.clipboard && navigator.clipboard.writeText(t);
}

// ===== مودال الشراء =====
let curPrice = 0;

function openBuy(card) {
  if (card.classList.contains('oos')) return;
  curPrice = parseFloat(card.dataset.price) || 0;
  document.getElementById('mName').textContent = card.dataset.name;
  document.getElementById('mPrice').textContent = Number(curPrice).toLocaleString() + ' ل.س';
  document.getElementById('mDesc').textContent = card.dataset.desc || '';
  document.getElementById('mQty').value = 1;
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
  i.value = Math.max(1, (parseInt(i.value) || 1) + d);
  updateTotal();
}
function updateTotal() {
  const q = parseInt(document.getElementById('mQty').value) || 1;
  document.getElementById('mTotal').textContent = (curPrice * q).toLocaleString();
}
document.addEventListener('input', e => { if (e.target.id === 'mQty') updateTotal(); });
document.addEventListener('click', e => { if (e.target.id === 'buyModal') closeBuy(); });

async function submitBuy() {
  const modal = document.getElementById('buyModal');
  const msg = document.getElementById('mMsg');
  const btn = document.getElementById('mBuyBtn');

  if (typeof IS_LOGGED !== 'undefined' && !IS_LOGGED) {
    location.href = '/auth.php';
    return;
  }
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
