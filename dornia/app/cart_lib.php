<?php
// سبد خرید
declare(strict_types=1);

/** اقلام سبد را با اطلاعات به‌روز محصول و قیمت لحظه‌ای برمی‌گرداند */
function cart_lines(int $gp): array
{
    $lines = [];
    foreach (cart() as $key => $item) {
        $st = db()->prepare('SELECT * FROM products WHERE id = ? AND active = 1');
        $st->execute([$item['pid']]);
        $p = $st->fetch();
        if (!$p) {
            unset($_SESSION['cart'][$key]);
            continue;
        }
        $variants = product_variants($p);
        $vi = (int)$item['variant'];
        $label = '';
        $weight = (float)$p['weight'];
        if ($vi >= 0 && isset($variants[$vi])) {
            $label = (string)$variants[$vi]['label'];
            $weight = (float)$variants[$vi]['weight'];
        }
        $qty = max(1, min((int)$item['qty'], max(1, (int)$p['stock'])));
        $price = product_price($p, $weight, $gp);
        $lines[$key] = ['key' => $key, 'product' => $p, 'variant' => $label, 'weight' => $weight, 'qty' => $qty, 'unit' => $price['total'], 'sum' => $price['total'] * $qty, 'in_stock' => (int)$p['stock'] > 0];
    }
    return $lines;
}
