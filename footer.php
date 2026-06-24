</main>
<footer class="footer">
  <div><?= e(STORE_NAME) ?> © <?= date('Y') ?> — جميع الحقوق محفوظة</div>
  <div class="foot-links">
    <?php if (WHATSAPP_1): ?><a href="<?= e(WHATSAPP_1) ?>" target="_blank">واتساب</a><?php endif; ?>
    <?php if (WHATSAPP_GROUP): ?><a href="<?= e(WHATSAPP_GROUP) ?>" target="_blank">مجموعة الواتساب</a><?php endif; ?>
    <?php if (INSTAGRAM): ?><a href="<?= e(INSTAGRAM) ?>" target="_blank">انستغرام</a><?php endif; ?>
  </div>
</footer>
<script src="/app.js?v=8" defer></script>
</body>
</html>
<?php /* تتبّع الطلبات تلقائياً بالخلفية لو المستخدم مسجّل دخول */ ?>
<?php if (current_user()): ?>
<script>
(function () {
  // تتبّع الطلبات المعلّقة كل 35 ثانية
  function trackOrders() {
    fetch('/track.php', { credentials: 'same-origin' }).catch(function(){});
  }
  setTimeout(trackOrders, 8000);
  setInterval(trackOrders, 35000);

  // ===== إشعارات المتصفح =====
  var swReg = null;
  // تسجيل Service Worker
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').then(function (reg) {
      swReg = reg;
    }).catch(function(){});
  }

  // طلب صلاحية الإشعارات (بلطف - بعد 5 ثواني من فتح الموقع)
  function askNotifPermission() {
    if (!('Notification' in window)) return;
    if (Notification.permission === 'default') {
      // نطلب الصلاحية فقط مرة واحدة
      var asked = false;
      try { asked = localStorage.getItem('notif_asked') === '1'; } catch(e){}
      if (!asked) {
        Notification.requestPermission().then(function(){
          try { localStorage.setItem('notif_asked', '1'); } catch(e){}
        });
      }
    }
  }
  setTimeout(askNotifPermission, 5000);

  // عرض إشعار متصفح
  function showBrowserNotif(title, body) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    // عبر Service Worker (أفضل) أو مباشرة
    if (swReg && swReg.active) {
      swReg.active.postMessage({ type: 'notify', title: title, body: body, url: '/notifications.php' });
    } else {
      try { new Notification(title, { body: body, icon: '/logo.svg', dir: 'rtl' }); } catch(e){}
    }
  }

  // تحديث جرس الإشعارات + عرض إشعار متصفح للجديد
  var lastNotifId = null;
  var firstCheck = true;
  function updateBell() {
    fetch('/notif_count.php', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => {
        const badge = document.getElementById('notifBadge');
        if (badge) {
          const bell = document.querySelector('.notif-bell');
          if (d.count > 0) {
            badge.textContent = d.count > 99 ? '99+' : d.count; badge.style.display = '';
            if (bell) bell.classList.add('has-new');
          }
          else { badge.style.display = 'none'; if (bell) bell.classList.remove('has-new'); }
        }
        // إشعار متصفح لأي إشعار جديد (مش بأول فحص عشان ما نزعج)
        if (d.latest) {
          if (!firstCheck && lastNotifId !== null && d.latest.id !== lastNotifId) {
            showBrowserNotif(d.latest.title, d.latest.body);
          }
          lastNotifId = d.latest.id;
        }
        firstCheck = false;
      }).catch(function(){});
  }
  updateBell();
  setInterval(updateBell, 20000);
})();
</script>
<?php endif; ?>
