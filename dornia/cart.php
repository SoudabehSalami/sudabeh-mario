<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';
require __DIR__ . '/app/cart_lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pid = (int)($_POST['pid'] ?? 0);
        $variant = (int)($_POST['variant'] ?? -1);
        $qty = max(1, (int)num_input($_POST['qty'] ?? 1));
        $st = db()->prepare('SELECT * FROM products WHERE id = ? AND active = 1');
        $st->execute([$pid]);
        $p = $st->fetch();
        if ($p && (int)$p['stock'] > 0) {
            if ($variant >= 0 && !isset(product_variants($p)[$variant])) {
                $variant = -1;
            }
            $key = $pid . ':' . $variant;
            $_SESSION['cart'][$key] = ['pid' => $pid, 'variant' => $variant, 'qty' => ($_SESSION['cart'][$key]['qty'] ?? 0) + $qty];
            flash('«' . $p['title'] . '» به سبد خرید اضافه شد.');
        }
    } elseif ($action === 'update') {
        foreach ((array)($_POST['qty'] ?? []) as $key => $q) {
            if (isset($_SESSION['cart'][$key])) {
                $q = (int)num_input($q);
                if ($q <= 0) {
                    unset($_SESSION['cart'][$key]);
                } else {
                    $_SESSION['cart'][$key]['qty'] = $q;
                }
            }
        }
    } elseif ($action === 'remove') {
        unset($_SESSION['cart'][(string)($_POST['key'] ?? '')]);
    }
    redirect('cart.php');
}

$gp = gold_price();
$lines = cart_lines($gp);
$total = array_sum(array_column($lines, 'sum'));

render_header('سبد خرید');
?>
<div class="container narrow">
  <h1 class="page-title">سبد خرید</h1>
  <?php if (!$lines): ?>
    <div class="empty">سبد خرید شما خالی است. <a href="<?= e(base_url('shop.php')) ?>">مشاهده محصولات</a></div>
  <?php else: ?>
    <form method="post" class="cart">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <button class="sr-default" name="action" value="update" tabindex="-1" aria-hidden="true"></button>
      <?php foreach ($lines as $l): $p = $l['product']; ?>
        <div class="cart-line">
          <img src="<?= e(product_image_url($p)) ?>" alt="">
          <div class="cart-info">
            <a href="<?= e(base_url('product.php?p=' . rawurlencode($p['slug']))) ?>"><?= e($p['title']) ?></a>
            <small><?= $l['variant'] !== '' ? e($l['variant']) . ' — ' : '' ?><?= fa_num($l['weight'], 2) ?> گرم — عیار <?= fa_digits((string)$p['karat']) ?></small>
            <small>قیمت واحد: <?= toman($l['unit']) ?></small>
          </div>
          <input class="cart-qty" type="number" min="0" name="qty[<?= e($l['key']) ?>]" value="<?= (int)$l['qty'] ?>" aria-label="تعداد">
          <strong><?= toman($l['sum']) ?></strong>
          <button class="link-btn" name="action" value="remove" formaction="<?= e(base_url('cart.php')) ?>" onclick="this.form.elements['key'].value='<?= e($l['key']) ?>'">حذف</button>
        </div>
      <?php endforeach; ?>
      <input type="hidden" name="key" value="">
      <div class="cart-foot">
        <button class="btn btn-ghost" type="submit">به‌روزرسانی سبد</button>
        <div class="cart-total"><span>جمع کل:</span> <strong><?= toman($total) ?></strong></div>
      </div>
      <p class="note">قیمت‌ها بر اساس نرخ لحظه‌ای طلا (هر گرم <?= toman($gp) ?>) محاسبه شده‌اند.</p>
      <div class="cart-actions">
        <a class="btn btn-ghost" href="<?= e(base_url('shop.php')) ?>">ادامه خرید</a>
        <a class="btn btn-gold btn-lg" href="<?= e(base_url('checkout.php')) ?>">ثبت سفارش و تسویه</a>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php render_footer();
