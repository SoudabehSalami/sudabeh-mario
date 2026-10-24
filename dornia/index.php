<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$gp = gold_price();
$featured = db()->query('SELECT * FROM products WHERE active = 1 AND featured = 1 ORDER BY created_at DESC LIMIT 8')->fetchAll();
$latest = db()->query('SELECT * FROM products WHERE active = 1 ORDER BY created_at DESC LIMIT 8')->fetchAll();
$icons = ['necklace', 'bracelet', 'ring', 'earring', 'pendant', 'chain'];

render_header();
?>
<section class="hero">
  <div class="container hero-inner">
    <div class="hero-text">
      <span class="eyebrow">طلای ۱۸ عیار با ضمانت اصالت</span>
      <h1>زیبایی <span class="gold-text">ماندگار</span><br>برای لحظه‌های خاص</h1>
      <p><?= e(setting('site_tagline')) ?>. زیورآلاتی که با دقت انتخاب و ساخته شده‌اند، با قیمت‌گذاری شفاف بر اساس نرخ لحظه‌ای طلا.</p>
      <div class="hero-cta">
        <a class="btn btn-gold" href="<?= e(base_url('shop.php')) ?>">مشاهده محصولات</a>
        <a class="btn btn-ghost" href="<?= e(base_url('calculator.php')) ?>">محاسبه قیمت طلا</a>
      </div>
      <div class="hero-price">
        <span>نرخ امروز هر گرم طلای ۱۸ عیار</span>
        <strong><?= toman($gp) ?></strong>
      </div>
    </div>
    <div class="hero-art" aria-hidden="true">
      <img src="<?= e(base_url('assets/img/hero.svg')) ?>" alt="">
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head"><h2>دسته‌بندی‌ها</h2></div>
    <div class="cats">
      <?php foreach (categories() as $c): $ic = in_array($c['slug'], $icons, true) ? $c['slug'] : 'pendant'; ?>
        <a class="cat" href="<?= e(base_url('shop.php?cat=' . rawurlencode($c['slug']))) ?>">
          <span class="cat-icon"><img src="<?= e(base_url('assets/img/c-' . $ic . '.svg')) ?>" alt=""></span>
          <b><?= e($c['name']) ?></b>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($featured): ?>
<section class="section section-alt">
  <div class="container">
    <div class="section-head"><h2>محصولات ویژه</h2><a href="<?= e(base_url('shop.php?featured=1')) ?>">مشاهده همه ←</a></div>
    <div class="grid"><?php foreach ($featured as $p) echo product_card($p, $gp); ?></div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="section-head"><h2>جدیدترین‌ها</h2><a href="<?= e(base_url('shop.php')) ?>">مشاهده همه ←</a></div>
    <div class="grid"><?php foreach ($latest as $p) echo product_card($p, $gp); ?></div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="banner">
      <div>
        <h2>قیمت طلا را خودتان حساب کنید</h2>
        <p>وزن، اجرت و عیار را وارد کنید تا قیمت نهایی با سود و مالیات را بر اساس نرخ لحظه‌ای ببینید.</p>
      </div>
      <a class="btn btn-gold" href="<?= e(base_url('calculator.php')) ?>">ماشین‌حساب طلا</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container steps">
    <div class="section-head center"><h2>خرید از درنیا، در ۴ قدم ساده</h2></div>
    <ol>
      <li><b>انتخاب محصول</b><span>محصول و سایز دلخواه را انتخاب کنید</span></li>
      <li><b>ثبت سفارش</b><span>مشخصات و آدرس را وارد کنید</span></li>
      <li><b>پرداخت</b><span>واریز و بارگذاری رسید، یا هماهنگی تلفنی</span></li>
      <li><b>دریافت</b><span>ارسال بیمه‌شده تا درب منزل</span></li>
    </ol>
  </div>
</section>
<?php render_footer();
