<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$wa = preg_replace('/\D/', '', en_digits((string)setting('whatsapp')));
$ig = trim((string)setting('instagram'), '@ ');
render_header('تماس با ما');
?>
<div class="container narrow">
  <h1 class="page-title">تماس با ما</h1>
  <div class="contact-grid">
    <div class="panel">
      <h2>درباره <?= e(setting('site_name')) ?></h2>
      <p>«درنیا» یعنی مروارید دریا؛ نامی برای زیورآلاتی که قرار است سال‌ها همراهتان بمانند. ما در درنیا زیورآلات طلای ۱۸ عیار را با دقت انتخاب می‌کنیم و قیمت هر محصول را شفاف و بر اساس نرخ لحظه‌ای طلا، همراه با ریز اجرت و سود، نمایش می‌دهیم.</p>
    </div>
    <div class="panel contact-list">
      <h2>راه‌های ارتباطی</h2>
      <p><b>آدرس:</b> <?= e(setting('address')) ?></p>
      <p><b>تلفن:</b> <a href="tel:<?= e(en_digits((string)setting('phone'))) ?>"><?= e(setting('phone')) ?></a></p>
      <p><b>موبایل:</b> <a href="tel:<?= e(en_digits((string)setting('mobile'))) ?>"><?= e(setting('mobile')) ?></a></p>
      <p><b>ساعات کاری:</b> <?= e(setting('work_hours')) ?></p>
      <div class="cta-row">
        <?php if ($wa): ?><a class="btn btn-wa" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">گفتگو در واتس‌اپ</a><?php endif; ?>
        <?php if ($ig): ?><a class="btn btn-ghost" href="https://instagram.com/<?= e($ig) ?>" target="_blank" rel="noopener">اینستاگرام @<?= e($ig) ?></a><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php render_footer();
