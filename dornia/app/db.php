<?php
// پایگاه داده‌ی SQLite؛ در اولین اجرا خودکار ساخته و با داده‌ی نمونه پر می‌شود
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    $file = DATA_DIR . '/dornia.sqlite';
    $fresh = !file_exists($file);
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    if ($fresh) {
        db_install($pdo);
    }
    return $pdo;
}

function db_install(PDO $pdo): void
{
    $pdo->exec(<<<SQL
    CREATE TABLE settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL
    );
    CREATE TABLE categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        sort INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        code TEXT NOT NULL DEFAULT '',
        karat INTEGER NOT NULL DEFAULT 18,
        weight REAL NOT NULL DEFAULT 0,
        ojrat_percent REAL NOT NULL DEFAULT 0,
        fixed_price INTEGER NOT NULL DEFAULT 0,
        variants TEXT NOT NULL DEFAULT '[]',
        description TEXT NOT NULL DEFAULT '',
        images TEXT NOT NULL DEFAULT '[]',
        stock INTEGER NOT NULL DEFAULT 1,
        featured INTEGER NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1,
        gender TEXT NOT NULL DEFAULT 'unisex',
        created_at INTEGER NOT NULL
    );
    CREATE TABLE orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        customer_name TEXT NOT NULL,
        phone TEXT NOT NULL,
        province TEXT NOT NULL DEFAULT '',
        city TEXT NOT NULL DEFAULT '',
        address TEXT NOT NULL DEFAULT '',
        postal_code TEXT NOT NULL DEFAULT '',
        note TEXT NOT NULL DEFAULT '',
        payment_method TEXT NOT NULL,
        receipt TEXT NOT NULL DEFAULT '',
        gold_price INTEGER NOT NULL,
        total INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        admin_note TEXT NOT NULL DEFAULT '',
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL
    );
    CREATE TABLE order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
        product_id INTEGER,
        title TEXT NOT NULL,
        variant TEXT NOT NULL DEFAULT '',
        weight REAL NOT NULL,
        karat INTEGER NOT NULL,
        ojrat_percent REAL NOT NULL,
        unit_price INTEGER NOT NULL,
        qty INTEGER NOT NULL
    );
    CREATE TABLE login_attempts (
        ip TEXT NOT NULL,
        at INTEGER NOT NULL
    );
    CREATE INDEX idx_products_cat ON products(category_id);
    CREATE INDEX idx_orders_phone ON orders(phone);
    SQL);

    $defaults = [
        'site_name' => 'گالری طلای درنیا',
        'site_tagline' => 'زیبایی ماندگار، با عیار اصیل',
        'phone' => '۰۲۱-۰۰۰۰۰۰۰۰',
        'mobile' => '۰۹۱۲۰۰۰۰۰۰۰',
        'whatsapp' => '989120000000',
        'instagram' => 'dornia.gold',
        'telegram' => '',
        'address' => 'تهران، ...',
        'work_hours' => 'شنبه تا پنجشنبه، ۱۰ صبح تا ۹ شب',
        // قیمت‌گذاری
        'profit_percent' => '7',
        'tax_percent' => '10',
        'default_ojrat' => '18',
        'round_to' => '1000',
        // قیمت طلا
        'gold_mode' => 'auto',          // auto | manual
        'gold_provider' => 'brsapi',    // brsapi | custom
        'gold_api_key' => '',
        'gold_custom_url' => '',
        'gold_custom_path' => '',
        'gold_custom_unit' => 'toman',  // toman | rial
        'gold_manual_price' => '6500000',
        'gold_cache_minutes' => '10',
        'gold_last_price' => '6500000',
        'gold_last_update' => '0',
        'gold_extra' => '[]',
        // پرداخت
        'pay_card_enabled' => '1',
        'card_number' => '6037-0000-0000-0000',
        'card_owner' => 'به نام ...',
        'pay_call_enabled' => '1',
        'order_price_note' => 'با توجه به نوسان قیمت طلا، قیمت سفارش تا ۳۰ دقیقه پس از ثبت معتبر است. پس از واریز، رسید را بارگذاری کنید.',
        'shipping_note' => 'ارسال بیمه‌شده با پست پیشتاز به سراسر کشور — رایگان',
        'admin_password' => '',
    ];
    $st = $pdo->prepare('INSERT INTO settings(key, value) VALUES(?, ?)');
    foreach ($defaults as $k => $v) {
        $st->execute([$k, $v]);
    }

    $cats = [
        ['گردنبند', 'necklace'],
        ['دستبند', 'bracelet'],
        ['انگشتر', 'ring'],
        ['گوشواره', 'earring'],
        ['آویز', 'pendant'],
        ['زنجیر', 'chain'],
    ];
    $st = $pdo->prepare('INSERT INTO categories(name, slug, sort) VALUES(?, ?, ?)');
    foreach ($cats as $i => [$n, $s]) {
        $st->execute([$n, $s, $i]);
    }

    // محصولات نمونه (بعداً از پنل مدیریت ویرایش یا حذف کنید)
    $now = time();
    $samples = [
        [6, 'زنجیر مردانه طرح بالچین متوسط', 'DN-101', 8.4, 14, 'men', 1, '[{"label":"۵۰ سانتی‌متر","weight":8.4},{"label":"۵۵ سانتی‌متر","weight":9.2},{"label":"۶۰ سانتی‌متر","weight":10.1}]', 'زنجیر مردانه با گوی‌های براق و قفل ایمن، مناسب استفاده‌ی روزمره و مجلسی. ساخته شده از طلای ۱۸ عیار با گواهی عیارسنجی.'],
        [1, 'گردنبند زنانه طرح قطره درنیا', 'DN-102', 3.2, 22, 'women', 1, '[]', 'گردنبند ظریف با آویز قطره‌ای، الهام گرفته از نام درنیا (مروارید دریا). مناسب هدیه.'],
        [2, 'دستبند کارتیه ظریف', 'DN-103', 4.6, 20, 'women', 1, '[{"label":"۱۷ سانتی‌متر","weight":4.6},{"label":"۱۹ سانتی‌متر","weight":5.1}]', 'دستبند طرح کارتیه با بافت محکم و درخشش بالا.'],
        [3, 'انگشتر رینگ ساده', 'DN-104', 2.1, 12, 'unisex', 1, '[]', 'انگشتر رینگ کلاسیک، مناسب حلقه‌ی ازدواج و استفاده‌ی روزانه.'],
        [4, 'گوشواره حلقه‌ای میناکاری', 'DN-105', 2.8, 25, 'women', 0, '[]', 'گوشواره حلقه‌ای با جزئیات میناکاری الهام گرفته از هنر ایرانی.'],
        [5, 'آویز اسم سفارشی', 'DN-106', 1.9, 30, 'unisex', 0, '[]', 'آویز با نام دلخواه شما به خط فارسی یا انگلیسی. زمان ساخت: ۵ روز کاری.'],
        [2, 'دستبند مردانه طرح فیگارو', 'DN-107', 12.5, 13, 'men', 0, '[]', 'دستبند مردانه پرکار با بافت فیگارو.'],
        [1, 'گردنبند ستاره و ماه', 'DN-108', 2.6, 24, 'women', 0, '[]', 'گردنبند ظریف با آویز ستاره و ماه.'],
    ];
    $st = $pdo->prepare('INSERT INTO products(category_id, title, slug, code, karat, weight, ojrat_percent, variants, description, images, stock, featured, active, gender, created_at)
        VALUES(?, ?, ?, ?, 18, ?, ?, ?, ?, \'[]\', 3, ?, 1, ?, ?)');
    foreach ($samples as $i => [$cat, $title, $code, $w, $oj, $gender, $feat, $vars, $desc]) {
        $st->execute([$cat, $title, strtolower($code), $code, $w, $oj, $vars, $desc, $feat, $gender, $now - $i * 3600]);
    }
}
