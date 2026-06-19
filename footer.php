</main>
<footer class="footer">
  <div><?= e(STORE_NAME) ?> © <?= date('Y') ?> — جميع الحقوق محفوظة</div>
  <div class="foot-links">
    <?php if (WHATSAPP_1): ?><a href="<?= e(WHATSAPP_1) ?>" target="_blank">واتساب</a><?php endif; ?>
    <?php if (WHATSAPP_GROUP): ?><a href="<?= e(WHATSAPP_GROUP) ?>" target="_blank">مجموعة الواتساب</a><?php endif; ?>
    <?php if (INSTAGRAM): ?><a href="<?= e(INSTAGRAM) ?>" target="_blank">انستغرام</a><?php endif; ?>
  </div>
</footer>
<script src="/app.js?v=3" defer></script>
<script src="/i18n.js?v=1" defer></script>
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

  // تحديث جرس الإشعارات
  function updateBell() {
    fetch('/notif_count.php', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => {
        const badge = document.getElementById('notifBadge');
        if (!badge) return;
        const bell = document.querySelector('.notif-bell');
        if (d.count > 0) {
          badge.textContent = d.count > 99 ? '99+' : d.count; badge.style.display = '';
          if (bell) bell.classList.add('has-new');
        }
        else { badge.style.display = 'none'; if (bell) bell.classList.remove('has-new'); }
      }).catch(function(){});
  }
  updateBell();
  setInterval(updateBell, 20000);
})();
</script>
<?php endif; ?>
