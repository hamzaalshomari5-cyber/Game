</main>
<footer class="footer">
  <div><?= e(STORE_NAME) ?> © <?= date('Y') ?> — جميع الحقوق محفوظة</div>
  <div class="foot-links">
    <?php if (WHATSAPP_1): ?><a href="<?= e(WHATSAPP_1) ?>" target="_blank">واتساب</a><?php endif; ?>
    <?php if (WHATSAPP_GROUP): ?><a href="<?= e(WHATSAPP_GROUP) ?>" target="_blank">مجموعة الواتساب</a><?php endif; ?>
    <?php if (INSTAGRAM): ?><a href="<?= e(INSTAGRAM) ?>" target="_blank">انستغرام</a><?php endif; ?>
  </div>
</footer>
<script src="/app.js"></script>
</body>
</html>
