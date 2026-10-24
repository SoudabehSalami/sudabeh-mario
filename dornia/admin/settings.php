<?php
require __DIR__ . '/_lib.php';
require_admin();

$text = ['site_name', 'site_tagline', 'phone', 'mobile', 'whatsapp', 'instagram', 'telegram', 'address', 'work_hours', 'shipping_note',
    'card_number', 'card_owner', 'order_price_note', 'gold_api_key', 'gold_custom_url', 'gold_custom_path'];
$numeric = ['profit_percent', 'tax_percent', 'default_ojrat', 'round_to', 'gold_manual_price', 'gold_cache_minutes'];
$choices = ['gold_mode' => ['auto', 'manual'], 'gold_provider' => ['brsapi', 'custom'], 'gold_custom_unit' => ['toman', 'rial']];
$checks = ['pay_card_enabled', 'pay_call_enabled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify($cur, (string)setting('admin_password'))) {
            flash('رمز فعلی اشتباه است.', 'err');
        } elseif (mb_strlen($new) < 8) {
            flash('رمز جدید باید حداقل ۸ حرف باشد.', 'err');
        } else {
            save_setting('admin_password', password_hash($new, PASSWORD_DEFAULT));
            flash('رمز عبور تغییر کرد.');
        }
        redirect('admin/settings.php#security');
    }
    foreach ($text as $k) {
        if (isset($_POST[$k])) save_setting($k, trim((string)$_POST[$k]));
    }
    foreach ($numeric as $k) {
        if (isset($_POST[$k])) save_setting($k, (string)num_input($_POST[$k]));
    }
    foreach ($choices as $k => $allowed) {
        if (isset($_POST[$k]) && in_array($_POST[$k], $allowed, true)) save_setting($k, $_POST[$k]);
    }
    foreach ($checks as $k) {
        save_setting($k, isset($_POST[$k]) ? '1' : '0');
    }
    if ($action === 'test_gold') {
        save_setting('gold_last_update', '0');
        $res = gold_fetch_remote();
        if ($res) {
            save_setting('gold_last_price', (string)$res['price']);
            save_setting('gold_extra', json_encode($res['extra'], JSON_UNESCAPED_UNICODE));
            save_setting('gold_last_update', (string)time());
            flash('اتصال موفق بود. قیمت هر گرم طلای ۱۸ عیار: ' . toman($res['price']));
        } else {
            flash('دریافت قیمت ناموفق بود. کلید یا آدرس سرویس را بررسی کنید. تا رفع مشکل، آخرین قیمت ثبت‌شده استفاده می‌شود.', 'err');
        }
        redirect('admin/settings.php#gold');
    }
    flash('تنظیمات ذخیره شد.');
    redirect('admin/settings.php');
}

$s = fn(string $k) => e((string)setting($k, ''));
$updated = (int)setting('gold_last_update', '0');
admin_header('تنظیمات و قیمت طلا');
?>
<form method="post" class="settings">
  <?= csrf_field() ?>
  <button class="sr-default" name="action" value="save" tabindex="-1" aria-hidden="true"></button>

  <section class="panel" id="gold">
    <h2>قیمت طلا</h2>
    <div class="radio-cards">
      <label class="pay-method"><input type="radio" name="gold_mode" value="auto" <?= setting('gold_mode') === 'auto' ? 'checked' : '' ?>><span><b>خودکار</b> — قیمت هر چند دقیقه از اینترنت گرفته می‌شود</span></label>
      <label class="pay-method"><input type="radio" name="gold_mode" value="manual" <?= setting('gold_mode') === 'manual' ? 'checked' : '' ?>><span><b>دستی</b> — خودم قیمت را وارد می‌کنم</span></label>
    </div>
    <div class="form-grid">
      <label>قیمت دستی هر گرم طلای ۱۸ عیار (تومان)<input name="gold_manual_price" value="<?= e(number_format((float)setting('gold_manual_price', '0'))) ?>" inputmode="numeric"></label>
      <label>به‌روزرسانی خودکار هر چند دقیقه؟<input name="gold_cache_minutes" value="<?= $s('gold_cache_minutes') ?>" inputmode="numeric"></label>
    </div>
    <div class="sub-box">
      <h3>سرویس قیمت خودکار</h3>
      <div class="radio-cards">
        <label class="pay-method"><input type="radio" name="gold_provider" value="brsapi" <?= setting('gold_provider') === 'brsapi' ? 'checked' : '' ?>><span><b>BrsApi.ir</b> (پیشنهادی) — در سایت brsapi.ir ثبت‌نام کنید و کلید رایگان را این‌جا وارد کنید</span></label>
        <label class="pay-method"><input type="radio" name="gold_provider" value="custom" <?= setting('gold_provider') === 'custom' ? 'checked' : '' ?>><span><b>سرویس دیگر</b> (برای کاربر فنی)</span></label>
      </div>
      <div class="form-grid">
        <label class="full">کلید BrsApi<input name="gold_api_key" value="<?= $s('gold_api_key') ?>" dir="ltr" autocomplete="off"></label>
        <label>آدرس سرویس دیگر (JSON)<input name="gold_custom_url" value="<?= $s('gold_custom_url') ?>" dir="ltr" placeholder="https://..."></label>
        <label>مسیر فیلد قیمت<input name="gold_custom_path" value="<?= $s('gold_custom_path') ?>" dir="ltr" placeholder="data.gold18.price"></label>
        <label>واحد قیمت سرویس
          <select name="gold_custom_unit"><option value="toman" <?= setting('gold_custom_unit') === 'toman' ? 'selected' : '' ?>>تومان</option><option value="rial" <?= setting('gold_custom_unit') === 'rial' ? 'selected' : '' ?>>ریال</option></select>
        </label>
      </div>
      <p class="muted">آخرین قیمت دریافتی: <b><?= toman((int)setting('gold_last_price', '0')) ?></b><?= $updated ? ' — ' . jdate_str($updated) : '' ?></p>
      <button class="btn btn-ghost" name="action" value="test_gold">ذخیره و تست دریافت قیمت</button>
    </div>
  </section>

  <section class="panel" id="pricing">
    <h2>فرمول قیمت‌گذاری</h2>
    <div class="form-grid">
      <label>سود فروشنده (درصد)<input name="profit_percent" value="<?= $s('profit_percent') ?>" inputmode="decimal"></label>
      <label>مالیات بر ارزش افزوده (درصد)<input name="tax_percent" value="<?= $s('tax_percent') ?>" inputmode="decimal"></label>
      <label>اجرت پیش‌فرض محصولات جدید (درصد)<input name="default_ojrat" value="<?= $s('default_ojrat') ?>" inputmode="decimal"></label>
      <label>گرد کردن قیمت نهایی به (تومان)<input name="round_to" value="<?= $s('round_to') ?>" inputmode="numeric"></label>
    </div>
    <p class="note">قیمت = ارزش طلا + اجرت + سود (روی طلا و اجرت) + مالیات (فقط روی اجرت و سود)</p>
  </section>

  <section class="panel">
    <h2>پرداخت</h2>
    <label class="check"><input type="checkbox" name="pay_card_enabled" <?= setting('pay_card_enabled') === '1' ? 'checked' : '' ?>> کارت به کارت با بارگذاری رسید</label>
    <label class="check"><input type="checkbox" name="pay_call_enabled" <?= setting('pay_call_enabled') === '1' ? 'checked' : '' ?>> هماهنگی تلفنی</label>
    <div class="form-grid">
      <label>شماره کارت<input name="card_number" value="<?= $s('card_number') ?>" dir="ltr"></label>
      <label>نام صاحب کارت<input name="card_owner" value="<?= $s('card_owner') ?>"></label>
      <label class="full">پیام زیر فرم سفارش<textarea name="order_price_note" rows="2"><?= $s('order_price_note') ?></textarea></label>
    </div>
  </section>

  <section class="panel">
    <h2>اطلاعات فروشگاه</h2>
    <div class="form-grid">
      <label>نام فروشگاه<input name="site_name" value="<?= $s('site_name') ?>"></label>
      <label>شعار<input name="site_tagline" value="<?= $s('site_tagline') ?>"></label>
      <label>تلفن ثابت<input name="phone" value="<?= $s('phone') ?>"></label>
      <label>موبایل<input name="mobile" value="<?= $s('mobile') ?>"></label>
      <label>شماره واتس‌اپ (با ۹۸، مثل 989121234567)<input name="whatsapp" value="<?= $s('whatsapp') ?>" dir="ltr"></label>
      <label>آیدی اینستاگرام<input name="instagram" value="<?= $s('instagram') ?>" dir="ltr"></label>
      <label>آیدی تلگرام<input name="telegram" value="<?= $s('telegram') ?>" dir="ltr"></label>
      <label>ساعات کاری<input name="work_hours" value="<?= $s('work_hours') ?>"></label>
      <label class="full">آدرس<input name="address" value="<?= $s('address') ?>"></label>
      <label class="full">متن ارسال<input name="shipping_note" value="<?= $s('shipping_note') ?>"></label>
    </div>
  </section>

  <div class="sticky-save"><button class="btn btn-gold btn-lg" name="action" value="save">ذخیره تنظیمات</button></div>
</form>

<form method="post" class="panel narrow-panel" id="security">
  <?= csrf_field() ?>
  <h2>تغییر رمز عبور</h2>
  <div class="form-grid">
    <label>رمز فعلی<input type="password" name="current" autocomplete="current-password" required></label>
    <label>رمز جدید (حداقل ۸ حرف)<input type="password" name="new" autocomplete="new-password" required></label>
  </div>
  <button class="btn btn-ghost" name="action" value="password">تغییر رمز</button>
</form>
<?php admin_footer();
