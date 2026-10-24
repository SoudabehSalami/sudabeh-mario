<?php
require __DIR__ . '/_lib.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        foreach ((array)($_POST['name'] ?? []) as $id => $name) {
            $name = trim((string)$name);
            if ($name !== '') {
                db()->prepare('UPDATE categories SET name = ?, sort = ? WHERE id = ?')->execute([$name, (int)num_input($_POST['sort'][$id] ?? 0), (int)$id]);
            }
        }
        $new = trim((string)($_POST['new_name'] ?? ''));
        if ($new !== '') {
            $slug = slugify($new);
            $chk = db()->prepare('SELECT 1 FROM categories WHERE slug = ?');
            $chk->execute([$slug]);
            if ($chk->fetchColumn()) {
                $slug .= '-' . bin2hex(random_bytes(2));
            }
            db()->prepare('INSERT INTO categories(name, slug, sort) VALUES(?, ?, ?)')->execute([$new, $slug, (int)db()->query('SELECT COALESCE(MAX(sort),0)+1 FROM categories')->fetchColumn()]);
        }
        flash('دسته‌بندی‌ها ذخیره شد.');
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('دسته حذف شد؛ محصولات آن بدون دسته شدند.');
    }
    redirect('admin/categories.php');
}

$cats = db()->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS n FROM categories c ORDER BY sort, id')->fetchAll();
admin_header('دسته‌بندی‌ها');
?>
<form method="post" class="panel narrow-panel">
  <?= csrf_field() ?>
  <button class="sr-default" name="action" value="save" tabindex="-1" aria-hidden="true"></button>
  <table class="tbl">
    <tr><th>نام دسته</th><th>ترتیب نمایش</th><th>تعداد محصول</th><th></th></tr>
    <?php foreach ($cats as $c): ?>
      <tr>
        <td><input name="name[<?= (int)$c['id'] ?>]" value="<?= e($c['name']) ?>"></td>
        <td><input name="sort[<?= (int)$c['id'] ?>]" value="<?= (int)$c['sort'] ?>" class="w-sm" inputmode="numeric"></td>
        <td><?= fa_digits((string)$c['n']) ?></td>
        <td><button class="link-btn" name="action" value="delete" formnovalidate onclick="if(!confirm('این دسته حذف شود؟'))return false;this.form.elements['id'].value='<?= (int)$c['id'] ?>'">حذف</button></td>
      </tr>
    <?php endforeach; ?>
    <tr><td colspan="4"><input name="new_name" placeholder="+ نام دسته جدید (مثلاً: سرویس)"></td></tr>
  </table>
  <input type="hidden" name="id" value="">
  <button class="btn btn-gold mt" name="action" value="save">ذخیره</button>
</form>
<?php admin_footer();
