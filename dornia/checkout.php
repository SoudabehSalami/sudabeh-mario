<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';
require __DIR__ . '/app/cart_lib.php';

$gp = gold_price();
$lines = cart_lines($gp);
if (!$lines) {
    redirect('cart.php');
}
$total = array_sum(array_column($lines, 'sum'));
$methods = [];
if (setting('pay_card_enabled') === '1') {
    $methods['card'] = 'کارت به کارت و بارگذاری رسید';
}
if (setting('pay_call_enabled') === '1' || !$methods) {
    $methods['call'] = 'هماهنگی تلفنی برای پرداخت (تماس کارشناس)';
}

$errors = [];
$old = ['customer_name' => '', 'phone' => '', 'province' => '', 'city' => '', 'address' => '', 'postal_code' => '', 'note' => '', 'payment_method' => array_key_first($methods)];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $k => $_) {
        $old[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $old['phone'] = preg_replace('/\D/', '', en_digits($old['phone']));
    $old['postal_code'] = preg_replace('/\D/', '', en_digits($old['postal_code']));

    if (mb_strlen($old['customer_name']) < 3) $errors[] = 'نام و نام خانوادگی را کامل وارد کنید.';
    if (!preg_match('/^09\d{9}$/', $old['phone'])) $errors[] = 'شماره موبایل را به شکل ۰۹۱۲۳۴۵۶۷۸۹ وارد کنید.';
    if ($old['province'] === '' || $old['city'] === '') $errors[] = 'استان و شهر را وارد کنید.';
    if (mb_strlen($old['address']) < 10) $errors[] = 'آدرس کامل را وارد کنید.';
    if ($old['postal_code'] !== '' && strlen($old['postal_code']) !== 10) $errors[] = 'کد پستی باید ۱۰ رقم باشد.';
    if (!isset($methods[$old['payment_method']])) $errors[] = 'روش پرداخت را انتخاب کنید.';
    foreach ($lines as $l) {
        if (!$l['in_stock']) $errors[] = '«' . $l['product']['title'] . '» ناموجود شده است؛ لطفاً از سبد حذف کنید.';
    }
    $receipt = '';
    if (!$errors && $old['payment_method'] === 'card' && ($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $receipt = save_uploaded_image($_FILES['receipt'], RECEIPT_DIR) ?? '';
        if ($receipt === '') $errors[] = 'فایل رسید باید عکس (jpg، png یا webp) و کمتر از ۶ مگابایت باشد.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        do {
            $code = 'DN' . random_int(100000, 999999);
            $exists = $pdo->prepare('SELECT 1 FROM orders WHERE code = ?');
            $exists->execute([$code]);
        } while ($exists->fetchColumn());
        $now = time();
        $status = $old['payment_method'] === 'card' && $receipt === '' ? 'awaiting_payment' : 'pending';
        $pdo->prepare('INSERT INTO orders(code, customer_name, phone, province, city, address, postal_code, note, payment_method, receipt, gold_price, total, status, created_at, updated_at)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$code, $old['customer_name'], $old['phone'], $old['province'], $old['city'], $old['address'], $old['postal_code'], $old['note'], $old['payment_method'], $receipt, $gp, $total, $status, $now, $now]);
        $orderId = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO order_items(order_id, product_id, title, variant, weight, karat, ojrat_percent, unit_price, qty) VALUES(?,?,?,?,?,?,?,?,?)');
        $dec = $pdo->prepare('UPDATE products SET stock = MAX(0, stock - ?) WHERE id = ?');
        foreach ($lines as $l) {
            $p = $l['product'];
            $ins->execute([$orderId, $p['id'], $p['title'], $l['variant'], $l['weight'], $p['karat'], $p['ojrat_percent'], $l['unit'], $l['qty']]);
            $dec->execute([$l['qty'], $p['id']]);
        }
        $pdo->commit();
        $_SESSION['cart'] = [];
        $_SESSION['my_orders'][] = $code;
        redirect('track.php?code=' . $code . '&new=1');
    }
}

render_header('ثبت سفارش');
?>
<div class="container">
  <h1 class="page-title">ثبت سفارش</h1>
  <?php foreach ($errors as $err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data" class="checkout">
    <?= csrf_field() ?>
    <section class="panel">
      <h2>مشخصات گیرنده</h2>
      <div class="form-grid">
        <label>نام و نام خانوادگی *<input name="customer_name" value="<?= e($old['customer_name']) ?>" required></label>
        <label>شماره موبایل *<input name="phone" value="<?= e($old['phone']) ?>" inputmode="tel" placeholder="۰۹۱۲۳۴۵۶۷۸۹" required></label>
        <label>استان *<input name="province" value="<?= e($old['province']) ?>" required></label>
        <label>شهر *<input name="city" value="<?= e($old['city']) ?>" required></label>
        <label class="full">آدرس کامل *<textarea name="address" rows="3" required><?= e($old['address']) ?></textarea></label>
        <label>کد پستی<input name="postal_code" value="<?= e($old['postal_code']) ?>" inputmode="numeric"></label>
        <label>توضیحات سفارش<input name="note" value="<?= e($old['note']) ?>" placeholder="مثلاً: بسته‌بندی کادویی"></label>
      </div>

      <h2>روش پرداخت</h2>
      <div class="pay-methods">
        <?php foreach ($methods as $k => $label): ?>
          <label class="pay-method">
            <input type="radio" name="payment_method" value="<?= e($k) ?>" <?= $old['payment_method'] === $k ? 'checked' : '' ?>>
            <span><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <?php if (isset($methods['card'])): ?>
        <div class="card-pay" id="cardPay">
          <p>مبلغ <b><?= toman($total) ?></b> را به کارت زیر واریز کنید:</p>
          <div class="card-number" dir="ltr"><?= e(fa_digits((string)setting('card_number'))) ?></div>
          <p class="muted"><?= e(setting('card_owner')) ?></p>
          <label>عکس رسید واریز <span class="muted">(اگر الان ندارید، بعداً از صفحه پیگیری سفارش بارگذاری کنید)</span>
            <input type="file" name="receipt" accept="image/jpeg,image/png,image/webp">
          </label>
        </div>
      <?php endif; ?>
      <p class="note"><?= e(setting('order_price_note')) ?></p>
    </section>

    <aside class="panel summary">
      <h2>خلاصه سفارش</h2>
      <?php foreach ($lines as $l): ?>
        <div class="sum-line">
          <span><?= e($l['product']['title']) ?><?= $l['variant'] !== '' ? ' — ' . e($l['variant']) : '' ?> × <?= fa_digits((string)$l['qty']) ?></span>
          <b><?= toman($l['sum']) ?></b>
        </div>
      <?php endforeach; ?>
      <div class="sum-line"><span>هزینه ارسال</span><b>رایگان</b></div>
      <div class="sum-total"><span>مبلغ قابل پرداخت</span><strong><?= toman($total) ?></strong></div>
      <button class="btn btn-gold btn-lg btn-block" type="submit">ثبت نهایی سفارش</button>
    </aside>
  </form>
</div>
<?php render_footer();
