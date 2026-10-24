<?php
// قیمت‌گذاری طلا و دریافت خودکار قیمت روز
declare(strict_types=1);

/**
 * قیمت هر گرم طلای ۱۸ عیار به تومان.
 * در حالت خودکار هر چند دقیقه یک بار از سرویس قیمت گرفته و ذخیره می‌شود؛
 * اگر سرویس در دسترس نباشد، آخرین قیمت معتبر استفاده می‌شود.
 */
function gold_price(): int
{
    if (setting('gold_mode') !== 'auto') {
        return (int)num_input(setting('gold_manual_price', '0'));
    }
    $last = (int)setting('gold_last_price', '0');
    $updated = (int)setting('gold_last_update', '0');
    $ttl = max(1, (int)setting('gold_cache_minutes', '10')) * 60;
    if (time() - $updated < $ttl && $last > 0) {
        return $last;
    }
    // در صورت خطای سرویس، حداکثر هر یک دقیقه یک بار دوباره تلاش می‌شود
    if (time() - (int)setting('gold_last_try', '0') < 60 && $last > 0) {
        return $last;
    }
    save_setting('gold_last_try', (string)time());
    $res = gold_fetch_remote();
    if ($res !== null) {
        save_setting('gold_last_price', (string)$res['price']);
        save_setting('gold_extra', json_encode($res['extra'], JSON_UNESCAPED_UNICODE));
        save_setting('gold_last_update', (string)time());
        return $res['price'];
    }
    return $last > 0 ? $last : (int)num_input(setting('gold_manual_price', '0'));
}

/** زمان آخرین به‌روزرسانی قیمت */
function gold_updated_at(): int
{
    return setting('gold_mode') === 'auto' ? (int)setting('gold_last_update', '0') : 0;
}

/** سایر قیمت‌ها (سکه، طلای ۲۴، مثقال، ...) برای نوار قیمت بالای سایت */
function gold_extra(): array
{
    if (setting('gold_mode') !== 'auto') {
        return [];
    }
    $x = json_decode(setting('gold_extra', '[]') ?? '[]', true);
    return is_array($x) ? $x : [];
}

function http_get_json(string $url, int $timeout = 6): ?array
{
    $body = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (DorniaGold)',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) {
            $body = null;
        }
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'header' => "User-Agent: Mozilla/5.0 (DorniaGold)\r\n"]]);
        $body = @file_get_contents($url, false, $ctx) ?: null;
    }
    if (!is_string($body)) {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

/** @return array{price:int, extra:array}|null */
function gold_fetch_remote(): ?array
{
    $provider = setting('gold_provider', 'brsapi');
    try {
        return $provider === 'custom' ? gold_fetch_custom() : gold_fetch_brsapi();
    } catch (Throwable) {
        return null;
    }
}

/**
 * سرویس BrsApi.ir (کلید رایگان از سایت brsapi.ir).
 * خروجی: {"gold":[{"symbol":"IR_GOLD_18K","name":"طلای 18 عیار","price":..., "unit":"تومان"}, ...], ...}
 */
function gold_fetch_brsapi(): ?array
{
    $key = trim((string)setting('gold_api_key', ''));
    if ($key === '') {
        return null;
    }
    $data = http_get_json('https://BrsApi.ir/Api/Market/Gold_Currency.php?key=' . rawurlencode($key));
    if (!$data || !isset($data['gold']) || !is_array($data['gold'])) {
        return null;
    }
    $price = 0;
    $extra = [];
    $wanted = [
        'IR_GOLD_18K' => 'طلای ۱۸ عیار',
        'IR_GOLD_24K' => 'طلای ۲۴ عیار',
        'IR_GOLD_MELTED' => 'آبشده (مثقال)',
        'IR_COIN_EMAMI' => 'سکه امامی',
        'IR_COIN_BAHAR' => 'سکه بهار آزادی',
        'IR_COIN_HALF' => 'نیم سکه',
        'IR_COIN_QUARTER' => 'ربع سکه',
        'XAUUSD' => 'انس جهانی',
    ];
    foreach ($data['gold'] as $row) {
        $sym = $row['symbol'] ?? '';
        if (!isset($wanted[$sym])) {
            continue;
        }
        $p = (float)num_input($row['price'] ?? 0);
        $unit = (string)($row['unit'] ?? 'تومان');
        if (str_contains($unit, 'ریال')) {
            $p /= 10;
        }
        if ($sym === 'IR_GOLD_18K') {
            $price = (int)round($p);
        }
        $extra[] = [
            'label' => $wanted[$sym],
            'price' => $p,
            'unit' => str_contains($unit, 'دلار') ? 'دلار' : 'تومان',
            'change' => (float)($row['change_percent'] ?? 0),
        ];
    }
    return $price > 0 ? ['price' => $price, 'extra' => $extra] : null;
}

/**
 * هر سرویس JSON دیگری: آدرس + مسیر فیلد قیمت (مثل data.gold18.price)
 */
function gold_fetch_custom(): ?array
{
    $url = trim((string)setting('gold_custom_url', ''));
    $path = trim((string)setting('gold_custom_path', ''));
    if ($url === '' || $path === '' || !preg_match('#^https?://#i', $url)) {
        return null;
    }
    $data = http_get_json($url);
    if (!$data) {
        return null;
    }
    $cur = $data;
    foreach (explode('.', $path) as $seg) {
        if (is_array($cur) && array_key_exists($seg, $cur)) {
            $cur = $cur[$seg];
        } else {
            return null;
        }
    }
    $p = num_input(is_scalar($cur) ? $cur : 0);
    if (setting('gold_custom_unit') === 'rial') {
        $p /= 10;
    }
    return $p > 0 ? ['price' => (int)round($p), 'extra' => []] : null;
}

/**
 * محاسبه‌ی قیمت طبق فرمول رایج بازار:
 *   ارزش طلا = وزن × قیمت هر گرم (به نسبت عیار)
 *   اجرت    = ارزش طلا × درصد اجرت
 *   سود     = (ارزش طلا + اجرت) × درصد سود
 *   مالیات  = (اجرت + سود) × درصد مالیات
 */
function price_breakdown(float $weight, float $ojratPercent, int $karat = 18, ?int $goldPrice = null, int $fixedPrice = 0): array
{
    $gp = $goldPrice ?? gold_price();
    if ($fixedPrice > 0) {
        return ['gold' => 0, 'ojrat' => 0, 'profit' => 0, 'tax' => 0, 'total' => $fixedPrice, 'gram' => $gp, 'fixed' => true];
    }
    $gram = $gp * ($karat / 18);
    $gold = $weight * $gram;
    $ojrat = $gold * $ojratPercent / 100;
    $profit = ($gold + $ojrat) * num_input(setting('profit_percent', '7')) / 100;
    $tax = ($ojrat + $profit) * num_input(setting('tax_percent', '10')) / 100;
    $total = $gold + $ojrat + $profit + $tax;
    $round = max(1, (int)setting('round_to', '1000'));
    $total = (int)(ceil($total / $round) * $round);
    return [
        'gold' => (int)round($gold),
        'ojrat' => (int)round($ojrat),
        'profit' => (int)round($profit),
        'tax' => (int)round($tax),
        'total' => $total,
        'gram' => (int)round($gram),
        'fixed' => false,
    ];
}

function product_price(array $p, ?float $weight = null, ?int $goldPrice = null): array
{
    return price_breakdown($weight ?? (float)$p['weight'], (float)$p['ojrat_percent'], (int)$p['karat'], $goldPrice, (int)$p['fixed_price']);
}
