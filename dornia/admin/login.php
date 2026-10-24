<?php
require __DIR__ . '/_lib.php';

$hasPassword = setting('admin_password', '') !== '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pass = (string)($_POST['password'] ?? '');
    if (!$hasPassword) {
        // اولین ورود: تعیین رمز عبور
        if (mb_strlen($pass) < 8) {
            $error = 'رمز عبور باید حداقل ۸ حرف باشد.';
        } elseif ($pass !== (string)($_POST['password2'] ?? '')) {
            $error = 'تکرار رمز عبور یکسان نیست.';
        } else {
            save_setting('admin_password', password_hash($pass, PASSWORD_DEFAULT));
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['admin_ip'] = $ip;
            flash('رمز عبور ثبت شد. به پنل مدیریت خوش آمدید!');
            redirect('admin/');
        }
    } else {
        db()->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([time() - 900]);
        $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
        $st->execute([$ip]);
        if ((int)$st->fetchColumn() >= 5) {
            $error = 'تعداد تلاش‌های ناموفق زیاد بود. ۱۵ دقیقه دیگر دوباره امتحان کنید.';
        } elseif (password_verify($pass, (string)setting('admin_password'))) {
            db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['admin_ip'] = $ip;
            redirect('admin/');
        } else {
            db()->prepare('INSERT INTO login_attempts(ip, at) VALUES(?, ?)')->execute([$ip, time()]);
            $error = 'رمز عبور اشتباه است.';
        }
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>ورود به پنل مدیریت درنیا</title>
<link rel="icon" href="<?= e(base_url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(base_url('assets/style.css')) ?>?v=1">
<link rel="stylesheet" href="<?= e(base_url('assets/admin.css')) ?>?v=1">
</head>
<body class="login-page">
  <form class="login-box" method="post">
    <img src="<?= e(base_url('assets/img/logo.svg')) ?>" alt="درنیا">
    <h1><?= $hasPassword ? 'ورود به پنل مدیریت' : 'تعیین رمز عبور مدیر' ?></h1>
    <?php if (!$hasPassword): ?><p class="muted">این اولین ورود شماست. یک رمز عبور قوی (حداقل ۸ حرف) انتخاب کنید و آن را جایی امن یادداشت کنید.</p><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>رمز عبور<input type="password" name="password" required autofocus autocomplete="<?= $hasPassword ? 'current-password' : 'new-password' ?>"></label>
    <?php if (!$hasPassword): ?><label>تکرار رمز عبور<input type="password" name="password2" required autocomplete="new-password"></label><?php endif; ?>
    <button class="btn btn-gold btn-block" type="submit"><?= $hasPassword ? 'ورود' : 'ثبت رمز و ورود' ?></button>
  </form>
</body>
</html>
