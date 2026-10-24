<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$gp = gold_price();
render_header('محاسبه قیمت طلا', 'ماشین‌حساب قیمت طلا با نرخ لحظه‌ای، اجرت، سود و مالیات');
?>
<div class="container narrow">
  <h1 class="page-title">محاسبه قیمت طلا</h1>
  <div class="panel calc" id="calc"
       data-gold="<?= (int)$gp ?>"
       data-profit="<?= e((string)num_input(setting('profit_percent'))) ?>"
       data-tax="<?= e((string)num_input(setting('tax_percent'))) ?>">
    <div class="form-grid">
      <label>نرخ هر گرم طلای ۱۸ عیار (تومان)<input id="c-gold" inputmode="numeric" value="<?= e(number_format($gp)) ?>"></label>
      <label>وزن (گرم)<input id="c-weight" inputmode="decimal" value="1"></label>
      <label>اجرت ساخت (درصد)<input id="c-ojrat" inputmode="decimal" value="<?= e((string)setting('default_ojrat')) ?>"></label>
      <label>عیار
        <select id="c-karat">
          <option value="18" selected>۱۸ عیار (۷۵۰)</option>
          <option value="21">۲۱ عیار (۸۷۵)</option>
          <option value="24">۲۴ عیار (۹۹۹)</option>
        </select>
      </label>
      <label>سود فروشنده (درصد)<input id="c-profit" inputmode="decimal" value="<?= e((string)setting('profit_percent')) ?>"></label>
      <label>مالیات (درصد)<input id="c-tax" inputmode="decimal" value="<?= e((string)setting('tax_percent')) ?>"></label>
    </div>
    <table class="breakdown">
      <tr><th>ارزش طلا</th><td id="r-gold">—</td></tr>
      <tr><th>اجرت ساخت</th><td id="r-ojrat">—</td></tr>
      <tr><th>سود فروشنده</th><td id="r-profit">—</td></tr>
      <tr><th>مالیات (روی اجرت و سود)</th><td id="r-tax">—</td></tr>
    </table>
    <div class="final-price"><span>قیمت نهایی:</span><strong id="r-total">—</strong></div>
    <p class="note">این محاسبه تقریبی است و بر اساس فرمول رایج بازار انجام می‌شود.</p>
  </div>
</div>
<?php render_footer();
