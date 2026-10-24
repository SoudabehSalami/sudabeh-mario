<?php
require __DIR__ . '/_lib.php';
require_admin();

$todayStart = strtotime('today');
$q = fn(string $sql, array $a = []) => (function () use ($sql, $a) { $st = db()->prepare($sql); $st->execute($a); return $st->fetchColumn(); })();
$stats = [
    'سفارش‌های امروز' => fa_digits((string)$q('SELECT COUNT(*) FROM orders WHERE created_at >= ?', [$todayStart])),
    'در انتظار بررسی' => fa_digits((string)$q("SELECT COUNT(*) FROM orders WHERE status IN ('pending','awaiting_payment')")),
    'فروش ۳۰ روز اخیر' => toman((int)$q("SELECT COALESCE(SUM(total),0) FROM orders WHERE status NOT IN ('cancelled') AND created_at >= ?", [time() - 30 * 86400])),
    'محصولات فعال' => fa_digits((string)$q('SELECT COUNT(*) FROM products WHERE active = 1')),
];
$recent = db()->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 8')->fetchAll();
$lowStock = db()->query('SELECT * FROM products WHERE active = 1 AND stock <= 0 ORDER BY title LIMIT 10')->fetchAll();
$auto = setting('gold_mode') === 'auto';
$updated = (int)setting('gold_last_update', '0');
$stale = $auto && (setting('gold_api_key', '') === '' && setting('gold_provider') === 'brsapi' || time() - $updated > 3600);

admin_header('داشبورد');
?>
<?php if ($stale): ?>
  <div class="alert alert-err">
    قیمت خودکار طلا به‌روز نمی‌شود<?= setting('gold_provider') === 'brsapi' && setting('gold_api_key', '') === '' ? ' (کلید سرویس قیمت وارد نشده)' : '' ?>. سایت فعلاً با آخرین قیمت ثبت‌شده کار می‌کند.
    <a href="<?= e(base_url('admin/settings.php#gold')) ?>">تنظیمات قیمت طلا ←</a>
  </div>
<?php endif; ?>
<div class="stats">
  <?php foreach ($stats as $label => $val): ?>
    <div class="stat"><span><?= e($label) ?></span><b><?= $val ?></b></div>
  <?php endforeach; ?>
</div>

<div class="two-col">
  <section class="panel">
    <div class="panel-head"><h2>آخرین سفارش‌ها</h2><a href="<?= e(base_url('admin/orders.php')) ?>">همه ←</a></div>
    <?php if (!$recent): ?><p class="muted">هنوز سفارشی ثبت نشده است.</p><?php else: ?>
    <table class="tbl">
      <tr><th>کد</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th></tr>
      <?php foreach ($recent as $o): ?>
        <tr onclick="location.href='<?= e(base_url('admin/order.php?id=' . $o['id'])) ?>'">
          <td dir="ltr"><?= e($o['code']) ?></td>
          <td><?= e($o['customer_name']) ?><br><small class="muted"><?= jdate_str((int)$o['created_at']) ?></small></td>
          <td><?= toman($o['total']) ?></td>
          <td><span class="status status-<?= e($o['status']) ?>"><?= e(ORDER_STATUSES[$o['status']] ?? '') ?></span></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>دسترسی سریع</h2></div>
    <div class="quick">
      <a class="btn btn-gold" href="<?= e(base_url('admin/product_edit.php')) ?>">+ افزودن محصول جدید</a>
      <a class="btn btn-ghost" href="<?= e(base_url('admin/settings.php#gold')) ?>">قیمت طلا</a>
      <a class="btn btn-ghost" href="<?= e(base_url('admin/settings.php#pricing')) ?>">درصد سود و مالیات</a>
    </div>
    <?php if ($lowStock): ?>
      <h3 class="mt">محصولات ناموجود</h3>
      <?php foreach ($lowStock as $p): ?>
        <a class="row-link" href="<?= e(base_url('admin/product_edit.php?id=' . $p['id'])) ?>"><?= e($p['title']) ?></a>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</div>
<?php admin_footer();
