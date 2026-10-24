<?php
require __DIR__ . '/_lib.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$p = null;
if ($id) {
    $st = db()->prepare('SELECT * FROM products WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch() ?: null;
    if (!$p) {
        redirect('admin/products.php');
    }
}
$p ??= [
    'id' => 0, 'category_id' => null, 'title' => '', 'slug' => '', 'code' => '', 'karat' => 18, 'weight' => '',
    'ojrat_percent' => setting('default_ojrat'), 'fixed_price' => 0, 'variants' => '[]', 'description' => '',
    'images' => '[]', 'stock' => 1, 'featured' => 0, 'active' => 1, 'gender' => 'unisex',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $p['title'] = trim((string)($_POST['title'] ?? ''));
    $p['code'] = trim((string)($_POST['code'] ?? ''));
    $p['category_id'] = (int)($_POST['category_id'] ?? 0) ?: null;
    $p['karat'] = in_array((int)($_POST['karat'] ?? 18), [18, 21, 24], true) ? (int)$_POST['karat'] : 18;
    $p['weight'] = num_input($_POST['weight'] ?? 0);
    $p['ojrat_percent'] = num_input($_POST['ojrat_percent'] ?? 0);
    $p['fixed_price'] = (int)num_input($_POST['fixed_price'] ?? 0);
    $p['description'] = trim((string)($_POST['description'] ?? ''));
    $p['stock'] = max(0, (int)num_input($_POST['stock'] ?? 0));
    $p['featured'] = isset($_POST['featured']) ? 1 : 0;
    $p['active'] = isset($_POST['active']) ? 1 : 0;
    $p['gender'] = in_array($_POST['gender'] ?? '', ['men', 'women', 'unisex'], true) ? $_POST['gender'] : 'unisex';

    // سایزها
    $variants = [];
    foreach ((array)($_POST['v_label'] ?? []) as $i => $label) {
        $label = trim((string)$label);
        $w = num_input($_POST['v_weight'][$i] ?? 0);
        if ($label !== '' && $w > 0) {
            $variants[] = ['label' => $label, 'weight' => $w];
        }
    }
    $p['variants'] = json_encode($variants, JSON_UNESCAPED_UNICODE);
    if ($variants && $p['weight'] <= 0) {
        $p['weight'] = $variants[0]['weight'];
    }

    // عکس‌ها: حذف، انتخاب عکس اصلی، افزودن
    $images = product_images($p);
    $remove = array_map('strval', (array)($_POST['remove_img'] ?? []));
    foreach ($images as $k => $img) {
        if (in_array($img, $remove, true)) {
            @unlink(UPLOAD_DIR . '/' . basename($img));
            unset($images[$k]);
        }
    }
    $images = array_values($images);
    $main = (string)($_POST['main_img'] ?? '');
    if ($main !== '' && ($k = array_search($main, $images, true)) !== false) {
        array_splice($images, $k, 1);
        array_unshift($images, $main);
    }
    $bad = 0;
    foreach (normalize_files($_FILES['images'] ?? []) as $f) {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $name = save_uploaded_image($f);
        $name ? $images[] = $name : $bad++;
    }
    if ($bad) $errors[] = fa_digits((string)$bad) . ' فایل پذیرفته نشد (فقط عکس jpg، png یا webp تا ۶ مگابایت).';
    $p['images'] = json_encode($images, JSON_UNESCAPED_UNICODE);

    if ($p['title'] === '') $errors[] = 'نام محصول را وارد کنید.';
    if ($p['weight'] <= 0 && $p['fixed_price'] <= 0) $errors[] = 'وزن محصول (یا قیمت ثابت) را وارد کنید.';

    if (!$errors || ($bad && count($errors) === 1)) {
        $slugBase = slugify($p['code'] !== '' ? $p['code'] . '-' . $p['title'] : $p['title']);
        $slug = $slugBase;
        for ($n = 2; ; $n++) {
            $chk = db()->prepare('SELECT id FROM products WHERE slug = ? AND id != ?');
            $chk->execute([$slug, (int)$p['id']]);
            if (!$chk->fetchColumn()) break;
            $slug = $slugBase . '-' . $n;
        }
        $p['slug'] = $p['id'] && $p['slug'] !== '' ? $p['slug'] : $slug;
        $cols = ['category_id', 'title', 'slug', 'code', 'karat', 'weight', 'ojrat_percent', 'fixed_price', 'variants', 'description', 'images', 'stock', 'featured', 'active', 'gender'];
        $vals = array_map(fn($c) => $p[$c], $cols);
        if ($p['id']) {
            db()->prepare('UPDATE products SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?')->execute([...$vals, $p['id']]);
        } else {
            db()->prepare('INSERT INTO products(' . implode(', ', $cols) . ', created_at) VALUES(' . str_repeat('?, ', count($cols)) . '?)')->execute([...$vals, time()]);
            $p['id'] = (int)db()->lastInsertId();
        }
        flash('محصول ذخیره شد.' . ($bad ? ' ' . $errors[0] : ''), $bad ? 'err' : 'ok');
        redirect('admin/product_edit.php?id=' . $p['id']);
    }
}

$cats = db()->query('SELECT * FROM categories ORDER BY sort, id')->fetchAll();
$variants = product_variants($p);
$images = product_images($p);
admin_header($p['id'] ? 'ویرایش محصول' : 'افزودن محصول');
?>
<?php foreach ($errors as $err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="edit-grid" id="productForm">
  <?= csrf_field() ?>
  <div>
    <section class="panel">
      <h2>اطلاعات اصلی</h2>
      <div class="form-grid">
        <label class="full">نام محصول *<input name="title" value="<?= e($p['title']) ?>" required placeholder="مثلاً: دستبند کارتیه ظریف"></label>
        <label>کد محصول<input name="code" value="<?= e($p['code']) ?>" placeholder="DN-110" dir="ltr"></label>
        <label>دسته‌بندی
          <select name="category_id">
            <option value="">— بدون دسته —</option>
            <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$p['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="full">توضیحات<textarea name="description" rows="5"><?= e($p['description']) ?></textarea></label>
      </div>
    </section>

    <section class="panel">
      <h2>وزن، اجرت و قیمت</h2>
      <div class="form-grid">
        <label>وزن (گرم) *<input name="weight" id="f-weight" value="<?= e((string)$p['weight']) ?>" inputmode="decimal" placeholder="مثلاً ۳٫۲۵"></label>
        <label>اجرت ساخت (درصد)<input name="ojrat_percent" id="f-ojrat" value="<?= e((string)$p['ojrat_percent']) ?>" inputmode="decimal"></label>
        <label>عیار
          <select name="karat" id="f-karat"><?php foreach ([18, 21, 24] as $k): ?><option value="<?= $k ?>" <?= (int)$p['karat'] === $k ? 'selected' : '' ?>><?= fa_digits((string)$k) ?> عیار</option><?php endforeach; ?></select>
        </label>
        <label>قیمت ثابت (اختیاری، تومان)<input name="fixed_price" value="<?= (int)$p['fixed_price'] ?: '' ?>" inputmode="numeric" placeholder="خالی = محاسبه خودکار"></label>
      </div>
      <div class="live-price" id="livePrice"
           data-gold="<?= (int)gold_price() ?>" data-profit="<?= e((string)num_input(setting('profit_percent'))) ?>"
           data-tax="<?= e((string)num_input(setting('tax_percent'))) ?>" data-round="<?= (int)setting('round_to', '1000') ?>">
        قیمت با نرخ امروز: <b>—</b>
      </div>

      <h3 class="mt">سایزها / مدل‌ها <small class="muted">(اختیاری — مثلاً طول زنجیر یا سایز انگشتر با وزن متفاوت)</small></h3>
      <div id="variants">
        <?php foreach ($variants ?: [['label' => '', 'weight' => '']] as $v): ?>
          <div class="variant-row">
            <input name="v_label[]" value="<?= e((string)$v['label']) ?>" placeholder="عنوان، مثلاً ۵۰ سانتی‌متر">
            <input name="v_weight[]" value="<?= e((string)$v['weight']) ?>" placeholder="وزن (گرم)" inputmode="decimal">
            <button type="button" class="link-btn" data-remove-row>حذف</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" id="addVariant">+ افزودن سایز</button>
    </section>
  </div>

  <div>
    <section class="panel">
      <h2>انتشار</h2>
      <label class="check"><input type="checkbox" name="active" <?= $p['active'] ? 'checked' : '' ?>> نمایش در سایت</label>
      <label class="check"><input type="checkbox" name="featured" <?= $p['featured'] ? 'checked' : '' ?>> محصول ویژه (در صفحه اول)</label>
      <div class="form-grid one">
        <label>موجودی (تعداد)<input name="stock" value="<?= (int)$p['stock'] ?>" inputmode="numeric"></label>
        <label>مناسب برای
          <select name="gender">
            <?php foreach (['unisex' => 'همه', 'women' => 'بانوان', 'men' => 'آقایان'] as $k => $l): ?><option value="<?= $k ?>" <?= $p['gender'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
      <button class="btn btn-gold btn-block" type="submit">ذخیره محصول</button>
      <?php if ($p['id']): ?><a class="btn btn-ghost btn-block mt" target="_blank" href="<?= e(base_url('product.php?p=' . rawurlencode($p['slug']))) ?>">مشاهده در سایت ↗</a><?php endif; ?>
    </section>

    <section class="panel">
      <h2>عکس‌ها</h2>
      <?php if ($images): ?>
        <div class="img-list">
          <?php foreach ($images as $k => $img): ?>
            <div class="img-item">
              <img src="<?= e(base_url('uploads/' . rawurlencode($img))) ?>" alt="">
              <label><input type="radio" name="main_img" value="<?= e($img) ?>" <?= $k === 0 ? 'checked' : '' ?>> عکس اصلی</label>
              <label class="text-err"><input type="checkbox" name="remove_img[]" value="<?= e($img) ?>"> حذف</label>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted">هنوز عکسی ندارد؛ تا آن موقع یک تصویر طرح‌دار نمایش داده می‌شود.</p>
      <?php endif; ?>
      <label class="upload">
        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
        <span>انتخاب عکس (می‌توانید چند عکس با هم انتخاب کنید)</span>
      </label>
      <p class="muted small">پیشنهاد: عکس مربعی، پس‌زمینه روشن و یکدست، حداقل ۸۰۰×۸۰۰ پیکسل.</p>
    </section>
  </div>
</form>
<?php admin_footer();
