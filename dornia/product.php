<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$slug = isset($_GET['p']) && is_string($_GET['p']) ? $_GET['p'] : '';
$st = db()->prepare('SELECT p.*, c.name AS cat_name, c.slug AS cat_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = ? AND p.active = 1');
$st->execute([$slug]);
$p = $st->fetch();
if (!$p) {
    http_response_code(404);
    render_header('محصول پیدا نشد');
    echo '<div class="container"><div class="empty">این محصول پیدا نشد یا دیگر فعال نیست. <a href="' . e(base_url('shop.php')) . '">بازگشت به فروشگاه</a></div></div>';
    render_footer();
    exit;
}

$gp = gold_price();
$variants = product_variants($p);
$options = [];
if ($variants) {
    foreach ($variants as $i => $v) {
        $options[] = ['idx' => $i, 'label' => (string)$v['label'], 'weight' => (float)$v['weight'], 'price' => product_price($p, (float)$v['weight'], $gp)];
    }
} else {
    $options[] = ['idx' => -1, 'label' => '', 'weight' => (float)$p['weight'], 'price' => product_price($p, null, $gp)];
}
$first = $options[0];
$images = product_images($p);
$inStock = (int)$p['stock'] > 0;
$wa = preg_replace('/\D/', '', en_digits((string)setting('whatsapp')));
$productUrl = (!empty($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . base_url('product.php?p=' . rawurlencode($p['slug']));

$rel = db()->prepare('SELECT * FROM products WHERE active = 1 AND id != ? AND category_id IS ? ORDER BY featured DESC, created_at DESC LIMIT 4');
$rel->execute([$p['id'], $p['category_id']]);
$related = $rel->fetchAll();

render_header($p['title'], mb_substr($p['description'], 0, 150));
?>
<div class="container">
  <nav class="crumbs">
    <a href="<?= e(base_url('')) ?>">خانه</a> /
    <a href="<?= e(base_url('shop.php')) ?>">فروشگاه</a>
    <?php if ($p['cat_name']): ?> / <a href="<?= e(base_url('shop.php?cat=' . rawurlencode($p['cat_slug']))) ?>"><?= e($p['cat_name']) ?></a><?php endif; ?>
    / <span><?= e($p['title']) ?></span>
  </nav>

  <div class="product">
    <div class="gallery">
      <div class="gallery-main"><img id="mainImg" src="<?= e(product_image_url($p)) ?>" alt="<?= e($p['title']) ?>"></div>
      <?php if (count($images) > 1): ?>
        <div class="thumbs">
          <?php foreach ($images as $i => $_): ?>
            <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-src="<?= e(product_image_url($p, $i)) ?>"><img src="<?= e(product_image_url($p, $i)) ?>" alt=""></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="buybox">
      <h1><?= e($p['title']) ?></h1>
      <div class="pmeta">
        <?php if ($p['code']): ?><span>کد محصول: <b><?= e($p['code']) ?></b></span><?php endif; ?>
        <span>عیار: <b><?= fa_digits((string)$p['karat']) ?></b></span>
        <span class="<?= $inStock ? 'ok' : 'no' ?>"><?= $inStock ? '● موجود در انبار' : '● ناموجود' ?></span>
      </div>

      <form action="<?= e(base_url('cart.php')) ?>" method="post" id="buyForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="pid" value="<?= (int)$p['id'] ?>">

        <?php if ($variants): ?>
          <div class="variants">
            <span class="label">سایز / مدل:</span>
            <?php foreach ($options as $k => $o): ?>
              <label class="chip">
                <input type="radio" name="variant" value="<?= (int)$o['idx'] ?>" <?= $k === 0 ? 'checked' : '' ?>>
                <span><?= e($o['label']) ?><small><?= fa_num($o['weight'], 2) ?> گرم</small></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <input type="hidden" name="variant" value="-1">
        <?php endif; ?>

        <table class="breakdown" id="breakdown">
          <tr><th>وزن</th><td data-k="weight"><?= fa_num($first['weight'], 2) ?> گرم</td></tr>
          <?php if (!$first['price']['fixed']): ?>
            <tr><th>نرخ هر گرم طلای <?= fa_digits((string)$p['karat']) ?> عیار</th><td><?= toman($first['price']['gram']) ?></td></tr>
            <tr><th>ارزش طلا</th><td data-k="gold"><?= toman($first['price']['gold']) ?></td></tr>
            <tr><th>اجرت ساخت (<?= fa_num((float)$p['ojrat_percent'], 2) ?>٪)</th><td data-k="ojrat"><?= toman($first['price']['ojrat']) ?></td></tr>
            <tr><th>سود فروش (<?= fa_num(num_input(setting('profit_percent')), 2) ?>٪)</th><td data-k="profit"><?= toman($first['price']['profit']) ?></td></tr>
            <tr><th>مالیات بر ارزش افزوده (<?= fa_num(num_input(setting('tax_percent')), 2) ?>٪)</th><td data-k="tax"><?= toman($first['price']['tax']) ?></td></tr>
          <?php endif; ?>
        </table>

        <div class="final-price">
          <span>قیمت نهایی:</span>
          <strong id="finalPrice"><?= toman($first['price']['total']) ?></strong>
        </div>

        <?php if ($inStock): ?>
          <div class="buy-row">
            <div class="qty">
              <button type="button" data-q="1" aria-label="افزایش">+</button>
              <input type="number" name="qty" value="1" min="1" max="<?= max(1, (int)$p['stock']) ?>" aria-label="تعداد">
              <button type="button" data-q="-1" aria-label="کاهش">−</button>
            </div>
            <button class="btn btn-gold btn-lg" type="submit">افزودن به سبد خرید</button>
          </div>
        <?php else: ?>
          <p class="note">این محصول فعلاً موجود نیست. برای سفارش ساخت با ما تماس بگیرید.</p>
        <?php endif; ?>
        <?php if ($wa): ?>
          <a class="btn btn-wa" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('سلام، درباره‌ی این محصول سؤال داشتم: ' . $p['title'] . ' (' . $p['code'] . ")\n" . $productUrl) ?>">مشاوره و سؤال در واتس‌اپ</a>
        <?php endif; ?>
      </form>

      <ul class="perks">
        <li>ضمانت اصالت و عیار با فاکتور رسمی</li>
        <li><?= e(setting('shipping_note')) ?></li>
        <li>قیمت بر اساس نرخ لحظه‌ای طلا محاسبه شده است</li>
      </ul>
    </div>
  </div>

  <div class="tabs">
    <div class="tab-btns">
      <button class="active" data-tab="t-desc">توضیحات</button>
      <button data-tab="t-spec">مشخصات</button>
      <button data-tab="t-guide">نحوه محاسبه قیمت</button>
    </div>
    <div class="tab active" id="t-desc"><p><?= nl2br(e($p['description'])) ?></p></div>
    <div class="tab" id="t-spec">
      <table class="spec">
        <tr><th>جنس</th><td>طلای زرد <?= fa_digits((string)$p['karat']) ?> عیار</td></tr>
        <tr><th>وزن</th><td><?= $variants ? implode('، ', array_map(fn($o) => e($o['label']) . ': ' . fa_num($o['weight'], 2) . ' گرم', $options)) : fa_num((float)$p['weight'], 2) . ' گرم' ?></td></tr>
        <?php if ($p['cat_name']): ?><tr><th>دسته‌بندی</th><td><?= e($p['cat_name']) ?></td></tr><?php endif; ?>
        <tr><th>مناسب برای</th><td><?= ['men' => 'آقایان', 'women' => 'بانوان', 'unisex' => 'بانوان و آقایان'][$p['gender']] ?? '' ?></td></tr>
        <?php if ($p['code']): ?><tr><th>کد محصول</th><td><?= e($p['code']) ?></td></tr><?php endif; ?>
      </table>
    </div>
    <div class="tab" id="t-guide">
      <p>قیمت هر محصول طلا از چهار بخش تشکیل می‌شود:</p>
      <ol>
        <li><b>ارزش طلا:</b> وزن محصول × نرخ روز هر گرم طلا</li>
        <li><b>اجرت ساخت:</b> درصدی از ارزش طلا که به سختی و ظرافت ساخت بستگی دارد</li>
        <li><b>سود فروشنده:</b> <?= fa_num(num_input(setting('profit_percent')), 2) ?>٪ از مجموع ارزش طلا و اجرت</li>
        <li><b>مالیات:</b> <?= fa_num(num_input(setting('tax_percent')), 2) ?>٪ فقط از مجموع اجرت و سود (نه از خود طلا)</li>
      </ol>
      <p>چون نرخ طلا لحظه‌ای تغییر می‌کند، قیمت محصولات هم به‌طور خودکار به‌روز می‌شود.</p>
    </div>
  </div>

  <?php if ($related): ?>
    <section class="section">
      <div class="section-head"><h2>محصولات مشابه</h2></div>
      <div class="grid"><?php foreach ($related as $r) echo product_card($r, $gp); ?></div>
    </section>
  <?php endif; ?>
</div>
<script>window.DORNIA_OPTIONS = <?= json_encode(array_map(fn($o) => ['idx' => $o['idx'], 'weight' => $o['weight'], 'price' => $o['price']], $options), JSON_UNESCAPED_UNICODE) ?>;</script>
<?php render_footer();
