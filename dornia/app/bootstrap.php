<?php
// هسته‌ی برنامه: اتصال پایگاه داده، تنظیمات، توابع کمکی
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
const DATA_DIR = APP_ROOT . '/data';
const UPLOAD_DIR = APP_ROOT . '/uploads';
const RECEIPT_DIR = DATA_DIR . '/receipts';

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Tehran');

if (session_status() === PHP_SESSION_NONE) {
    session_name('dornia_sid');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/pricing.php';

/** پایه‌ی آدرس سایت (برای وقتی که سایت داخل یک پوشه نصب شده باشد) */
function base_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir = rtrim(dirname($script), '/');
        if (str_ends_with($dir, '/admin')) {
            $dir = substr($dir, 0, -6);
        }
        $base = $dir;
    }
    return $base . '/' . ltrim($path, '/');
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fa_digits(string $s): string
{
    return strtr($s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

/** تبدیل ارقام فارسی/عربی ورودی کاربر به لاتین */
function en_digits(string $s): string
{
    return strtr($s, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '٫' => '.', '،' => ',',
    ]);
}

function num_input(mixed $v): float
{
    $s = str_replace([',', ' '], '', en_digits(trim((string)$v)));
    return is_numeric($s) ? (float)$s : 0.0;
}

function toman(float|int $amount): string
{
    return fa_digits(number_format((float)$amount)) . ' تومان';
}

function fa_num(float|int $n, int $decimals = 0): string
{
    $s = number_format((float)$n, $decimals);
    if ($decimals > 0) {
        $s = rtrim(rtrim($s, '0'), '.');
    }
    return fa_digits($s);
}

/** تاریخ شمسی */
function jdate_str(int $ts, bool $withTime = true): string
{
    [$gy, $gm, $gd] = array_map('intval', explode('-', date('Y-m-d', $ts)));
    [$jy, $jm, $jd] = gregorian_to_jalali($gy, $gm, $gd);
    $s = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    if ($withTime) {
        $s .= ' - ' . date('H:i', $ts);
    }
    return fa_digits($s);
}

function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : base_url($path)));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(400);
        exit('درخواست نامعتبر است. لطفاً صفحه را دوباره باز کنید.');
    }
}

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function &settings_cache(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT key, value FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache;
}

function setting(string $key, ?string $default = null): ?string
{
    $cache = &settings_cache();
    return $cache[$key] ?? $default;
}

function save_setting(string $key, string $value): void
{
    $st = db()->prepare('INSERT INTO settings(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $st->execute([$key, $value]);
    $cache = &settings_cache();
    $cache[$key] = $value;
}

function slugify(string $s): string
{
    $s = trim(mb_strtolower($s));
    $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $s) ?? '';
    $s = trim($s, '-');
    return $s !== '' ? $s : 'item-' . bin2hex(random_bytes(3));
}

function product_images(array $p): array
{
    $imgs = json_decode($p['images'] ?? '[]', true);
    return is_array($imgs) ? $imgs : [];
}

function product_image_url(array $p, int $i = 0): string
{
    $imgs = product_images($p);
    if (isset($imgs[$i])) {
        return base_url('uploads/' . rawurlencode($imgs[$i]));
    }
    // تا وقتی عکس واقعی ندارد، تصویر طرح‌دار متناسب با دسته نمایش داده می‌شود
    $byCat = ['ring' => 0, 'necklace' => 1, 'pendant' => 1, 'bracelet' => 2, 'chain' => 2, 'earring' => 3];
    $slug = $p['cat_slug'] ?? category_slug((int)($p['category_id'] ?? 0));
    $n = $byCat[$slug] ?? (($p['id'] ?? 0) % 4);
    return base_url('assets/img/placeholder-' . $n . '.svg');
}

function category_slug(int $id): string
{
    static $map = null;
    if ($map === null) {
        $map = db()->query('SELECT id, slug FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return (string)($map[$id] ?? '');
}

function product_variants(array $p): array
{
    $v = json_decode($p['variants'] ?? '[]', true);
    return is_array($v) ? $v : [];
}

/**
 * ذخیره‌ی امن عکس آپلود شده؛ فقط jpg/png/webp پذیرفته می‌شود و با نام تصادفی ذخیره می‌شود.
 * @return string|null نام فایل ذخیره شده
 */
function save_uploaded_image(array $file, string $dir = UPLOAD_DIR, int $maxBytes = 6_000_000): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    if ($file['size'] > $maxBytes) {
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    $ext = match ($info[2] ?? 0) {
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        default => null,
    };
    if ($ext === null) {
        return null;
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return null;
    }
    @chmod($dir . '/' . $name, 0644);
    return $name;
}

/** آرایه‌ی فایل‌های چندتایی را به لیست تبدیل می‌کند */
function normalize_files(array $f): array
{
    if (!is_array($f['name'] ?? null)) {
        return [$f];
    }
    $out = [];
    foreach ($f['name'] as $i => $_) {
        $out[] = [
            'name' => $f['name'][$i],
            'type' => $f['type'][$i],
            'tmp_name' => $f['tmp_name'][$i],
            'error' => $f['error'][$i],
            'size' => $f['size'][$i],
        ];
    }
    return $out;
}

const ORDER_STATUSES = [
    'pending' => 'در انتظار بررسی',
    'awaiting_payment' => 'در انتظار پرداخت',
    'paid' => 'پرداخت تأیید شد',
    'preparing' => 'در حال آماده‌سازی',
    'shipped' => 'ارسال شد',
    'delivered' => 'تحویل داده شد',
    'cancelled' => 'لغو شد',
];

function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int
{
    return array_sum(array_column(cart(), 'qty'));
}
