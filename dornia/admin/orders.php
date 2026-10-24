<?php
require __DIR__ . '/_lib.php';
require_admin();

$status = isset($_GET['status']) && is_string($_GET['status']) && isset(ORDER_STATUSES[$_GET['status']]) ? $_GET['status'] : '';
$q = isset($_GET['q']) && is_string($_GET['q']) ? trim(en_digits($_GET['q'])) : '';
$where = [];
$args = [];
if ($status !== '') {
    $where[] = 'status = ?';
    $args[] = $status;
}
if ($q !== '') {
    $where[] = '(code LIKE ? OR phone LIKE ? OR customer_name LIKE ?)';
    array_push($args, "%$q%", "%$q%", "%$q%");
}
$st = db()->prepare('SELECT * FROM orders' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC LIMIT 300');
$st->execute($args);
$orders = $st->fetchAll();
$counts = db()->query('SELECT status, COUNT(*) n FROM orders GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);

admin_header('سفارش‌ها');
?>
<div class="toolbar">
  <div class="status-tabs">
    <a class="<?= $status === '' ? 'active' : '' ?>" href="<?= e(base_url('admin/orders.php')) ?>">همه</a>
    <?php foreach (ORDER_STATUSES as $k => $label): ?>
      <a class="<?= $status === $k ? 'active' : '' ?>" href="<?= e(base_url('admin/orders.php?status=' . $k)) ?>"><?= e($label) ?> <small>(<?= fa_digits((string)($counts[$k] ?? 0)) ?>)</small></a>
    <?php endforeach; ?>
  </div>
  <form method="get" class="tsearch">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input name="q" value="<?= e($q) ?>" placeholder="کد، موبایل یا نام مشتری"><button class="btn btn-ghost">جستجو</button>
  </form>
</div>
<div class="panel">
  <?php if (!$orders): ?><p class="muted">سفارشی پیدا نشد.</p><?php else: ?>
  <table class="tbl">
    <tr><th>کد</th><th>تاریخ</th><th>مشتری</th><th>موبایل</th><th>مبلغ</th><th>پرداخت</th><th>وضعیت</th></tr>
    <?php foreach ($orders as $o): ?>
      <tr onclick="location.href='<?= e(base_url('admin/order.php?id=' . $o['id'])) ?>'">
        <td dir="ltr"><b><?= e($o['code']) ?></b></td>
        <td><?= jdate_str((int)$o['created_at']) ?></td>
        <td><?= e($o['customer_name']) ?><br><small class="muted"><?= e($o['city']) ?></small></td>
        <td dir="ltr"><?= e(fa_digits($o['phone'])) ?></td>
        <td><?= toman($o['total']) ?></td>
        <td><?= $o['payment_method'] === 'card' ? 'کارت به کارت' . ($o['receipt'] ? ' <span class="pill pill-ok">رسید دارد</span>' : '') : 'تلفنی' ?></td>
        <td><span class="status status-<?= e($o['status']) ?>"><?= e(ORDER_STATUSES[$o['status']] ?? '') ?></span></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php admin_footer();
