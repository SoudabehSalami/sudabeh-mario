<?php
require __DIR__ . '/_lib.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle') {
        db()->prepare('UPDATE products SET active = 1 - active WHERE id = ?')->execute([$id]);
    } elseif ($action === 'delete') {
        $st = db()->prepare('SELECT images FROM products WHERE id = ?');
        $st->execute([$id]);
        foreach (json_decode((string)$st->fetchColumn(), true) ?: [] as $img) {
            @unlink(UPLOAD_DIR . '/' . basename($img));
        }
        db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        flash('محصول حذف شد.');
    }
    redirect('admin/products.php');
}

$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$sql = 'SELECT p.*, c.name AS cat FROM products p LEFT JOIN categories c ON c.id = p.category_id';
$args = [];
if ($q !== '') {
    $sql .= ' WHERE p.title LIKE ? OR p.code LIKE ?';
    $args = ['%' . $q . '%', '%' . $q . '%'];
}
$st = db()->prepare($sql . ' ORDER BY p.created_at DESC');
$st->execute($args);
$products = $st->fetchAll();
$gp = gold_price();

admin_header('محصولات');
?>
<div class="toolbar">
  <a class="btn btn-gold" href="<?= e(base_url('admin/product_edit.php')) ?>">+ افزودن محصول</a>
  <form method="get" class="tsearch"><input name="q" value="<?= e($q) ?>" placeholder="جستجوی نام یا کد"><button class="btn btn-ghost">جستجو</button></form>
</div>
<div class="panel">
  <table class="tbl">
    <tr><th></th><th>محصول</th><th>وزن</th><th>اجرت</th><th>قیمت فعلی</th><th>موجودی</th><th>وضعیت</th><th></th></tr>
    <?php foreach ($products as $p): ?>
      <tr>
        <td><img class="thumb" src="<?= e(product_image_url($p)) ?>" alt=""></td>
        <td><a href="<?= e(base_url('admin/product_edit.php?id=' . $p['id'])) ?>"><b><?= e($p['title']) ?></b></a><br><small class="muted"><?= e($p['code']) ?> <?= $p['cat'] ? '— ' . e($p['cat']) : '' ?></small></td>
        <td><?= fa_num((float)$p['weight'], 2) ?> گرم<?= product_variants($p) ? '<br><small class="muted">' . fa_digits((string)count(product_variants($p))) . ' سایز</small>' : '' ?></td>
        <td><?= (int)$p['fixed_price'] > 0 ? 'قیمت ثابت' : fa_num((float)$p['ojrat_percent'], 2) . '٪' ?></td>
        <td><?= toman(product_price($p, null, $gp)['total']) ?></td>
        <td><?= (int)$p['stock'] > 0 ? fa_digits((string)$p['stock']) : '<span class="text-err">ناموجود</span>' ?></td>
        <td>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="toggle <?= $p['active'] ? 'on' : '' ?>" name="action" value="toggle" title="فعال/غیرفعال"><?= $p['active'] ? 'نمایش' : 'مخفی' ?></button>
          </form>
        </td>
        <td class="actions">
          <a href="<?= e(base_url('admin/product_edit.php?id=' . $p['id'])) ?>">ویرایش</a>
          <form method="post" onsubmit="return confirm('این محصول برای همیشه حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="link-btn" name="action" value="delete">حذف</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php if (!$products): ?><p class="muted">محصولی پیدا نشد.</p><?php endif; ?>
</div>
<?php admin_footer();
