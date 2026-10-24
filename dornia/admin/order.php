<?php
require __DIR__ . '/_lib.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM orders WHERE id = ?');
$st->execute([$id]);
$o = $st->fetch();
if (!$o) {
    redirect('admin/orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $new = (string)($_POST['status'] ?? $o['status']);
    if (isset(ORDER_STATUSES[$new])) {
        // برگرداندن موجودی هنگام لغو و کم کردن دوباره در صورت بازگشت از لغو
        if ($new === 'cancelled' && $o['status'] !== 'cancelled') {
            db()->prepare('UPDATE products SET stock = stock + (SELECT COALESCE(SUM(qty),0) FROM order_items WHERE order_id = ? AND product_id = products.id) WHERE id IN (SELECT product_id FROM order_items WHERE order_id = ?)')->execute([$id, $id]);
        } elseif ($o['status'] === 'cancelled' && $new !== 'cancelled') {
            db()->prepare('UPDATE products SET stock = MAX(0, stock - (SELECT COALESCE(SUM(qty),0) FROM order_items WHERE order_id = ? AND product_id = products.id)) WHERE id IN (SELECT product_id FROM order_items WHERE order_id = ?)')->execute([$id, $id]);
        }
        db()->prepare('UPDATE orders SET status = ?, admin_note = ?, updated_at = ? WHERE id = ?')
            ->execute([$new, trim((string)($_POST['admin_note'] ?? '')), time(), $id]);
        flash('سفارش به‌روز شد.');
    }
    redirect('admin/order.php?id=' . $id);
}

$items = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$items->execute([$id]);
$items = $items->fetchAll();
$wa = '98' . ltrim($o['phone'], '0');
$msg = 'سلام ' . $o['customer_name'] . ' عزیز، سفارش شما با کد ' . $o['code'] . ' در گالری طلای درنیا ' . (ORDER_STATUSES[$o['status']] ?? '') . '.';

admin_header('سفارش ' . $o['code']);
?>
<div class="edit-grid">
  <div>
    <section class="panel">
      <div class="panel-head"><h2>اقلام سفارش</h2><span class="status status-<?= e($o['status']) ?>"><?= e(ORDER_STATUSES[$o['status']] ?? '') ?></span></div>
      <table class="tbl">
        <tr><th>محصول</th><th>وزن</th><th>اجرت</th><th>تعداد</th><th>قیمت واحد</th><th>جمع</th></tr>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?php if ($it['product_id']): ?><a href="<?= e(base_url('admin/product_edit.php?id=' . $it['product_id'])) ?>"><?= e($it['title']) ?></a><?php else: ?><?= e($it['title']) ?><?php endif; ?><?= $it['variant'] !== '' ? '<br><small class="muted">' . e($it['variant']) . '</small>' : '' ?></td>
            <td><?= fa_num((float)$it['weight'], 2) ?> گرم — <?= fa_digits((string)$it['karat']) ?> عیار</td>
            <td><?= fa_num((float)$it['ojrat_percent'], 2) ?>٪</td>
            <td><?= fa_digits((string)$it['qty']) ?></td>
            <td><?= toman($it['unit_price']) ?></td>
            <td><?= toman($it['unit_price'] * $it['qty']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <div class="sum-total"><span>مبلغ کل (با نرخ طلای <?= toman($o['gold_price']) ?>)</span><strong><?= toman($o['total']) ?></strong></div>
    </section>

    <section class="panel">
      <h2>پرداخت</h2>
      <p>روش: <b><?= $o['payment_method'] === 'card' ? 'کارت به کارت' : 'هماهنگی تلفنی' ?></b></p>
      <?php if ($o['receipt']): ?>
        <a href="<?= e(base_url('admin/receipt.php?id=' . $o['id'])) ?>" target="_blank"><img class="receipt" src="<?= e(base_url('admin/receipt.php?id=' . $o['id'])) ?>" alt="رسید"></a>
      <?php elseif ($o['payment_method'] === 'card'): ?>
        <p class="muted">مشتری هنوز رسید بارگذاری نکرده است.</p>
      <?php endif; ?>
    </section>
  </div>

  <div>
    <section class="panel">
      <h2>مشتری</h2>
      <p><b><?= e($o['customer_name']) ?></b></p>
      <p>موبایل: <a dir="ltr" href="tel:<?= e($o['phone']) ?>"><?= e(fa_digits($o['phone'])) ?></a></p>
      <p><?= e($o['province']) ?>، <?= e($o['city']) ?><br><?= nl2br(e($o['address'])) ?></p>
      <?php if ($o['postal_code']): ?><p>کد پستی: <?= e(fa_digits($o['postal_code'])) ?></p><?php endif; ?>
      <?php if ($o['note']): ?><p class="note">توضیح مشتری: <?= e($o['note']) ?></p><?php endif; ?>
      <p class="muted">ثبت: <?= jdate_str((int)$o['created_at']) ?></p>
      <div class="cta-row">
        <a class="btn btn-ghost btn-sm" href="tel:<?= e($o['phone']) ?>">تماس</a>
        <a class="btn btn-wa btn-sm" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode($msg) ?>">پیام واتس‌اپ</a>
      </div>
    </section>

    <form class="panel" method="post">
      <?= csrf_field() ?>
      <h2>تغییر وضعیت</h2>
      <div class="form-grid one">
        <label>وضعیت سفارش
          <select name="status"><?php foreach (ORDER_STATUSES as $k => $l): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
        </label>
        <label>پیام برای مشتری <small class="muted">(در صفحه پیگیری نمایش داده می‌شود، مثلاً کد رهگیری پست)</small>
          <textarea name="admin_note" rows="3"><?= e($o['admin_note']) ?></textarea>
        </label>
      </div>
      <button class="btn btn-gold btn-block">ذخیره</button>
    </form>
  </div>
</div>
<?php admin_footer();
