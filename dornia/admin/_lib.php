<?php
// مشترکات پنل مدیریت
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

function is_admin(): bool
{
    return !empty($_SESSION['admin']) && ($_SESSION['admin_ip'] ?? '') === ($_SERVER['REMOTE_ADDR'] ?? '');
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect('admin/login.php');
    }
}

function admin_header(string $title): void
{
    $nav = [
        'index.php' => 'داشبورد',
        'orders.php' => 'سفارش‌ها',
        'products.php' => 'محصولات',
        'categories.php' => 'دسته‌بندی‌ها',
        'settings.php' => 'تنظیمات و قیمت طلا',
    ];
    $cur = basename($_SERVER['SCRIPT_NAME']);
    $pending = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','awaiting_payment')")->fetchColumn();
    ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> | پنل مدیریت درنیا</title>
<link rel="icon" href="<?= e(base_url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(base_url('assets/style.css')) ?>?v=1">
<link rel="stylesheet" href="<?= e(base_url('assets/admin.css')) ?>?v=1">
</head>
<body class="admin">
<aside class="side">
  <a class="side-brand" href="<?= e(base_url('admin/')) ?>"><img src="<?= e(base_url('assets/img/logo-light.svg')) ?>" alt="درنیا"></a>
  <nav>
    <?php foreach ($nav as $file => $label): ?>
      <a class="<?= $cur === $file || ($file === 'products.php' && $cur === 'product_edit.php') || ($file === 'orders.php' && $cur === 'order.php') ? 'active' : '' ?>" href="<?= e(base_url('admin/' . $file)) ?>">
        <?= e($label) ?><?php if ($file === 'orders.php' && $pending): ?><b class="pill"><?= fa_digits((string)$pending) ?></b><?php endif; ?>
      </a>
    <?php endforeach; ?>
    <a href="<?= e(base_url('')) ?>" target="_blank">مشاهده سایت ↗</a>
    <a href="<?= e(base_url('admin/logout.php')) ?>">خروج</a>
  </nav>
</aside>
<div class="admin-main">
  <header class="admin-top">
    <button class="menu-toggle" onclick="document.body.classList.toggle('side-open')" aria-label="منو"><span></span><span></span><span></span></button>
    <h1><?= e($title) ?></h1>
    <span class="gold-now">طلای ۱۸: <b><?= toman(gold_price()) ?></b></span>
  </header>
  <div class="admin-body">
  <?php foreach (take_flashes() as [$type, $msg]): ?><div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div><?php endforeach; ?>
<?php
}

function admin_footer(): void
{
    ?>
  </div>
</div>
<script src="<?= e(base_url('assets/admin.js')) ?>?v=1"></script>
</body>
</html>
<?php
}
