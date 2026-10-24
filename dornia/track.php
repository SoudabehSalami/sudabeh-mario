<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$code = strtoupper(trim(en_digits((string)($_REQUEST['code'] ?? ''))));
$phone = preg_replace('/\D/', '', en_digits((string)($_REQUEST['phone'] ?? '')));
$order = null;
$error = '';

if ($code !== '') {
    $st = db()->prepare('SELECT * FROM orders WHERE code = ?');
    $st->execute([$code]);
    $found = $st->fetch();
    $mine = in_array($code, $_SESSION['my_orders'] ?? [], true);
    if ($found && ($mine || ($phone !== '' && hash_equals($found['phone'], $phone)))) {
        $order = $found;
        $_SESSION['my_orders'][] = $code;
        $_SESSION['my_orders'] = array_values(array_unique($_SESSION['my_orders']));
    } elseif ($phone !== '' || !$mine) {
        $error = $phone === '' ? '' : 'سفارشی با این کد و شماره موبایل پیدا نشد.';
    }
}

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'receipt') {
    csrf_check();
    if (in_array($order['status'], ['pending', 'awaiting_payment'], true)) {
        $file = save_uploaded_image($_FILES['receipt'] ?? [], RECEIPT_DIR);
        if ($file) {
            db()->prepare("UPDATE orders SET receipt = ?, status = 'pending', updated_at = ? WHERE id = ?")->execute([$file, time(), $order['id']]);
            flash('رسید شما ثبت شد و پس از بررسی، وضعیت سفارش به‌روز می‌شود.');
        } else {
            flash('فایل رسید باید عکس (jpg، png یا webp) و کمتر از ۶ مگابایت باشد.', 'err');
        }
    }
    redirect('track.php?code=' . $order['code']);
}

render_header('پیگیری سفارش');
?>
<div class="container narrow">
  <h1 class="page-title">پیگیری سفارش</h1>
  <?php if (!$order): ?>
    <?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
    <form class="panel track-form" method="get">
      <label>کد سفارش<input name="code" value="<?= e($code) ?>" placeholder="DN123456" dir="ltr" required></label>
      <label>شماره موبایل<input name="phone" value="<?= e($phone) ?>" placeholder="09123456789" dir="ltr" inputmode="tel" required></label>
      <button class="btn btn-gold" type="submit">پیگیری</button>
    </form>
  <?php else:
    $items = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $items->execute([$order['id']]);
    $flow = ['pending', 'paid', 'preparing', 'shipped', 'delivered'];
    $curIdx = array_search($order['status'] === 'awaiting_payment' ? 'pending' : $order['status'], $flow, true);
  ?>
    <?php if (isset($_GET['new'])): ?>
      <div class="success-box">
        <div class="check">✓</div>
        <h2>سفارش شما با موفقیت ثبت شد</h2>
        <p>کد پیگیری: <b dir="ltr"><?= e($order['code']) ?></b> — این کد را نگه دارید.</p>
        <p class="muted">همکاران ما به‌زودی برای تأیید سفارش با شما تماس می‌گیرند.</p>
      </div>
    <?php endif; ?>

    <div class="panel">
      <div class="order-head">
        <div>سفارش <b dir="ltr"><?= e($order['code']) ?></b><br><small class="muted"><?= jdate_str((int)$order['created_at']) ?></small></div>
        <span class="status status-<?= e($order['status']) ?>"><?= e(ORDER_STATUSES[$order['status']] ?? $order['status']) ?></span>
      </div>
      <?php if ($order['status'] !== 'cancelled'): ?>
        <ol class="timeline">
          <?php foreach ($flow as $i => $s): ?>
            <li class="<?= $curIdx !== false && $i <= $curIdx ? 'done' : '' ?>"><?= e(ORDER_STATUSES[$s]) ?></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>

      <?php foreach ($items as $it): ?>
        <div class="sum-line"><span><?= e($it['title']) ?><?= $it['variant'] !== '' ? ' — ' . e($it['variant']) : '' ?> (<?= fa_num((float)$it['weight'], 2) ?> گرم) × <?= fa_digits((string)$it['qty']) ?></span><b><?= toman($it['unit_price'] * $it['qty']) ?></b></div>
      <?php endforeach; ?>
      <div class="sum-total"><span>مبلغ کل</span><strong><?= toman($order['total']) ?></strong></div>

      <?php if ($order['admin_note'] !== ''): ?><div class="alert alert-ok">پیام فروشگاه: <?= e($order['admin_note']) ?></div><?php endif; ?>

      <?php if ($order['payment_method'] === 'card' && in_array($order['status'], ['pending', 'awaiting_payment'], true)): ?>
        <div class="card-pay">
          <?php if ($order['receipt'] === ''): ?>
            <p>مبلغ <b><?= toman($order['total']) ?></b> را به کارت زیر واریز و رسید را بارگذاری کنید:</p>
            <div class="card-number" dir="ltr"><?= e(fa_digits((string)setting('card_number'))) ?></div>
            <p class="muted"><?= e(setting('card_owner')) ?></p>
          <?php else: ?>
            <p>رسید شما دریافت شده و در حال بررسی است. در صورت نیاز می‌توانید رسید دیگری بارگذاری کنید.</p>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="receipt">
            <input type="hidden" name="code" value="<?= e($order['code']) ?>">
            <input type="file" name="receipt" accept="image/jpeg,image/png,image/webp" required>
            <button class="btn btn-gold" type="submit">بارگذاری رسید</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_footer();
