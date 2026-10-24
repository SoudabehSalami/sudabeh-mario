<?php
// قالب مشترک صفحات سایت
declare(strict_types=1);

function categories(): array
{
    static $cats = null;
    return $cats ??= db()->query('SELECT * FROM categories ORDER BY sort, id')->fetchAll();
}

function render_header(string $title = '', string $description = ''): void
{
    $site = setting('site_name');
    $fullTitle = $title !== '' ? $title . ' | ' . $site : $site . ' — ' . setting('site_tagline');
    $gp = gold_price();
    $extra = gold_extra();
    $updated = gold_updated_at();
    $q = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
    ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($description !== '' ? $description : setting('site_tagline')) ?>">
<meta name="theme-color" content="#16120d">
<link rel="icon" href="<?= e(base_url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(base_url('assets/style.css')) ?>?v=1">
</head>
<body>
<div class="ticker" aria-label="قیمت لحظه‌ای طلا">
  <div class="container ticker-inner">
    <span class="ticker-live"><i></i>قیمت لحظه‌ای</span>
    <div class="ticker-items">
      <span class="ticker-item"><b>طلای ۱۸ عیار (هر گرم)</b> <?= toman($gp) ?></span>
      <?php foreach ($extra as $x): if (str_contains($x['label'], '۱۸')) continue; ?>
        <span class="ticker-item"><b><?= e($x['label']) ?></b> <?= fa_digits(number_format((float)$x['price'], $x['unit'] === 'دلار' ? 2 : 0)) ?> <?= e($x['unit']) ?>
          <?php if (!empty($x['change'])): ?><em class="<?= $x['change'] >= 0 ? 'up' : 'down' ?>"><?= $x['change'] >= 0 ? '▲' : '▼' ?> <?= fa_num(abs($x['change']), 2) ?>٪</em><?php endif; ?>
        </span>
      <?php endforeach; ?>
    </div>
    <?php if ($updated): ?><span class="ticker-time">به‌روزرسانی: <?= jdate_str($updated) ?></span><?php endif; ?>
  </div>
</div>
<header class="site-header">
  <div class="container header-inner">
    <button class="menu-toggle" aria-label="منو" onclick="document.body.classList.toggle('nav-open')"><span></span><span></span><span></span></button>
    <a class="brand" href="<?= e(base_url('')) ?>" aria-label="<?= e($site) ?>">
      <img src="<?= e(base_url('assets/img/logo.svg')) ?>" alt="<?= e($site) ?>" width="150" height="48">
    </a>
    <form class="search" action="<?= e(base_url('shop.php')) ?>" method="get" role="search">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="جستجوی محصول، مثلاً: دستبند" aria-label="جستجو">
      <button aria-label="جستجو"><svg viewBox="0 0 24 24" width="20" height="20"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m20 20-4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
    </form>
    <div class="header-actions">
      <a class="hdr-btn" href="<?= e(base_url('track.php')) ?>" title="پیگیری سفارش">
        <svg viewBox="0 0 24 24" width="22" height="22"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="7" cy="17.5" r="1.8" fill="currentColor"/><circle cx="17.5" cy="17.5" r="1.8" fill="currentColor"/></svg>
        <span>پیگیری سفارش</span>
      </a>
      <a class="hdr-btn cart-btn" href="<?= e(base_url('cart.php')) ?>" title="سبد خرید">
        <svg viewBox="0 0 24 24" width="22" height="22"><path d="M5 8h14l-1.3 11.2a2 2 0 0 1-2 1.8H8.3a2 2 0 0 1-2-1.8z M9 8V6a3 3 0 0 1 6 0v2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
        <span>سبد خرید</span>
        <?php if (cart_count() > 0): ?><b class="badge"><?= fa_digits((string)cart_count()) ?></b><?php endif; ?>
      </a>
    </div>
  </div>
  <nav class="main-nav">
    <div class="container">
      <a href="<?= e(base_url('')) ?>">خانه</a>
      <a href="<?= e(base_url('shop.php')) ?>">همه محصولات</a>
      <?php foreach (categories() as $c): ?>
        <a href="<?= e(base_url('shop.php?cat=' . rawurlencode($c['slug']))) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(base_url('calculator.php')) ?>">محاسبه قیمت طلا</a>
      <a href="<?= e(base_url('contact.php')) ?>">تماس با ما</a>
    </div>
  </nav>
</header>
<main>
<?php foreach (take_flashes() as [$type, $msg]): ?>
  <div class="container"><div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div></div>
<?php endforeach; ?>
<?php
}

function render_footer(): void
{
    $wa = preg_replace('/\D/', '', en_digits((string)setting('whatsapp')));
    $ig = trim((string)setting('instagram'), '@ ');
    $tg = trim((string)setting('telegram'), '@ ');
    ?>
</main>
<section class="trust">
  <div class="container trust-grid">
    <div><img src="<?= e(base_url('assets/img/i-cert.svg')) ?>" alt="" width="44" height="44"><b>ضمانت اصالت و عیار</b><span>همراه با فاکتور رسمی و کد محصول</span></div>
    <div><img src="<?= e(base_url('assets/img/i-ship.svg')) ?>" alt="" width="44" height="44"><b>ارسال بیمه‌شده</b><span><?= e(setting('shipping_note')) ?></span></div>
    <div><img src="<?= e(base_url('assets/img/i-price.svg')) ?>" alt="" width="44" height="44"><b>قیمت شفاف</b><span>محاسبه بر اساس نرخ لحظه‌ای با ریز اجرت و سود</span></div>
    <div><img src="<?= e(base_url('assets/img/i-support.svg')) ?>" alt="" width="44" height="44"><b>مشاوره خرید</b><span><?= e(setting('work_hours')) ?></span></div>
  </div>
</section>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-about">
      <img src="<?= e(base_url('assets/img/logo-light.svg')) ?>" alt="<?= e(setting('site_name')) ?>" width="150" height="48">
      <p><?= e(setting('site_tagline')) ?>. طراحی و فروش زیورآلات طلای ۱۸ عیار با ضمانت اصالت.</p>
    </div>
    <div>
      <h4>دسترسی سریع</h4>
      <a href="<?= e(base_url('shop.php')) ?>">فروشگاه</a>
      <a href="<?= e(base_url('calculator.php')) ?>">محاسبه قیمت طلا</a>
      <a href="<?= e(base_url('track.php')) ?>">پیگیری سفارش</a>
      <a href="<?= e(base_url('contact.php')) ?>">تماس با ما</a>
    </div>
    <div>
      <h4>تماس با ما</h4>
      <p><?= e(setting('address')) ?></p>
      <p>تلفن: <a href="tel:<?= e(en_digits((string)setting('phone'))) ?>"><?= e(setting('phone')) ?></a></p>
      <p>همراه: <a href="tel:<?= e(en_digits((string)setting('mobile'))) ?>"><?= e(setting('mobile')) ?></a></p>
      <div class="socials">
        <?php if ($ig): ?><a href="https://instagram.com/<?= e($ig) ?>" target="_blank" rel="noopener" aria-label="اینستاگرام"><svg viewBox="0 0 24 24" width="22" height="22"><rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="17.3" cy="6.7" r="1.2" fill="currentColor"/></svg></a><?php endif; ?>
        <?php if ($wa): ?><a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="واتس‌اپ"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M4 20l1.3-4A8 8 0 1 1 8 18.7z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 9.5c.3 2 2.3 4.2 4.6 4.8l1-1.1 1.6.8c-.3 1.2-1.3 1.7-2.4 1.5-2.8-.6-5.3-3.2-5.8-6 0-1 .6-1.9 1.6-2.1l.8 1.6z" fill="currentColor"/></svg></a><?php endif; ?>
        <?php if ($tg): ?><a href="https://t.me/<?= e($tg) ?>" target="_blank" rel="noopener" aria-label="تلگرام"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M21 4 3 11l6 2 2 6 3-4 5 4z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></a><?php endif; ?>
      </div>
    </div>
    <div>
      <h4>نماد اعتماد</h4>
      <div class="enamad-box">جای نماد اعتماد الکترونیکی</div>
    </div>
  </div>
  <div class="copyright container">© <?= fa_digits((string)gregorian_to_jalali((int)date('Y'), (int)date('n'), (int)date('j'))[0]) ?> —تمامی حقوق برای <?= e(setting('site_name')) ?> محفوظ است.</div>
</footer>
<?php if ($wa): ?>
<a class="wa-float" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="مشاوره در واتس‌اپ">
  <svg viewBox="0 0 24 24" width="28" height="28"><path d="M4 20l1.3-4A8 8 0 1 1 8 18.7z" fill="none" stroke="#fff" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 9.5c.3 2 2.3 4.2 4.6 4.8l1-1.1 1.6.8c-.3 1.2-1.3 1.7-2.4 1.5-2.8-.6-5.3-3.2-5.8-6 0-1 .6-1.9 1.6-2.1l.8 1.6z" fill="#fff"/></svg>
</a>
<?php endif; ?>
<script src="<?= e(base_url('assets/app.js')) ?>?v=1"></script>
</body>
</html>
<?php
}

function product_card(array $p, int $gp): string
{
    $price = product_price($p, null, $gp);
    $url = base_url('product.php?p=' . rawurlencode($p['slug']));
    $imgs = product_images($p);
    $img2 = count($imgs) > 1 ? product_image_url($p, 1) : null;
    ob_start(); ?>
  <article class="card">
    <a href="<?= e($url) ?>" class="card-img">
      <img src="<?= e(product_image_url($p)) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
      <?php if ($img2): ?><img class="alt" src="<?= e($img2) ?>" alt="" loading="lazy"><?php endif; ?>
      <?php if ((int)$p['stock'] <= 0): ?><span class="tag tag-out">ناموجود</span><?php elseif ($p['featured']): ?><span class="tag">ویژه</span><?php endif; ?>
    </a>
    <div class="card-body">
      <h3><a href="<?= e($url) ?>"><?= e($p['title']) ?></a></h3>
      <div class="card-meta">
        <span>وزن: <?= fa_num((float)$p['weight'], 2) ?> گرم</span>
        <span>عیار <?= fa_digits((string)$p['karat']) ?></span>
      </div>
      <div class="card-price"><?= (int)$p['stock'] > 0 ? toman($price['total']) : 'ناموجود' ?></div>
    </div>
  </article>
<?php
    return (string)ob_get_clean();
}
