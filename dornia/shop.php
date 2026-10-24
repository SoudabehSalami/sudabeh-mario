<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';

$gp = gold_price();
$str = fn(string $k) => isset($_GET[$k]) && is_string($_GET[$k]) ? trim($_GET[$k]) : '';
$catSlug = $str('cat');
$q = $str('q');
$gender = $str('gender');
$sort = $str('sort') ?: 'new';
$onlyFeatured = $str('featured') === '1';
$inStock = $str('stock') === '1';

$where = ['p.active = 1'];
$args = [];
$currentCat = null;
if ($catSlug !== '') {
    foreach (categories() as $c) {
        if ($c['slug'] === $catSlug) {
            $currentCat = $c;
        }
    }
    if ($currentCat) {
        $where[] = 'p.category_id = ?';
        $args[] = $currentCat['id'];
    }
}
if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.code LIKE ? OR p.description LIKE ?)';
    $like = '%' . $q . '%';
    array_push($args, $like, $like, $like);
}
if (in_array($gender, ['men', 'women'], true)) {
    $where[] = "(p.gender = ? OR p.gender = 'unisex')";
    $args[] = $gender;
}
if ($onlyFeatured) {
    $where[] = 'p.featured = 1';
}
if ($inStock) {
    $where[] = 'p.stock > 0';
}
$st = db()->prepare('SELECT p.* FROM products p WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC');
$st->execute($args);
$products = $st->fetchAll();

foreach ($products as &$p) {
    $p['_price'] = product_price($p, null, $gp)['total'];
}
unset($p);
usort($products, match ($sort) {
    'cheap' => fn($a, $b) => $a['_price'] <=> $b['_price'],
    'expensive' => fn($a, $b) => $b['_price'] <=> $a['_price'],
    'light' => fn($a, $b) => $a['weight'] <=> $b['weight'],
    default => fn($a, $b) => $b['created_at'] <=> $a['created_at'],
});
// ناموجودها در انتها
usort($products, fn($a, $b) => ((int)($b['stock'] > 0)) <=> ((int)($a['stock'] > 0)));

$perPage = 16;
$total = count($products);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, (int)$str('page')));
$products = array_slice($products, ($page - 1) * $perPage, $perPage);

function shop_url(array $override): string
{
    $params = array_filter(array_merge(array_intersect_key($_GET, array_flip(['cat', 'q', 'gender', 'sort', 'featured', 'stock'])), $override), fn($v) => $v !== '' && $v !== null);
    return base_url('shop.php' . ($params ? '?' . http_build_query($params) : ''));
}

$title = $currentCat ? $currentCat['name'] : ($q !== '' ? 'جستجو: ' . $q : 'همه محصولات');
render_header($title);
?>
<div class="container">
  <nav class="crumbs"><a href="<?= e(base_url('')) ?>">خانه</a> / <a href="<?= e(base_url('shop.php')) ?>">فروشگاه</a><?php if ($currentCat): ?> / <span><?= e($currentCat['name']) ?></span><?php endif; ?></nav>
  <div class="shop">
    <aside class="filters">
      <h3>دسته‌بندی</h3>
      <a class="<?= !$currentCat ? 'active' : '' ?>" href="<?= e(shop_url(['cat' => null, 'page' => null])) ?>">همه</a>
      <?php foreach (categories() as $c): ?>
        <a class="<?= $currentCat && $currentCat['id'] === $c['id'] ? 'active' : '' ?>" href="<?= e(shop_url(['cat' => $c['slug'], 'page' => null])) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <h3>مناسب برای</h3>
      <a class="<?= $gender === '' ? 'active' : '' ?>" href="<?= e(shop_url(['gender' => null])) ?>">همه</a>
      <a class="<?= $gender === 'women' ? 'active' : '' ?>" href="<?= e(shop_url(['gender' => 'women'])) ?>">زنانه</a>
      <a class="<?= $gender === 'men' ? 'active' : '' ?>" href="<?= e(shop_url(['gender' => 'men'])) ?>">مردانه</a>
      <h3>وضعیت</h3>
      <a class="<?= $inStock ? 'active' : '' ?>" href="<?= e(shop_url(['stock' => $inStock ? null : '1'])) ?>"><?= $inStock ? '✓ ' : '' ?>فقط کالاهای موجود</a>
    </aside>
    <section>
      <div class="shop-bar">
        <h1><?= e($title) ?> <small>(<?= fa_digits((string)$total) ?> محصول)</small></h1>
        <div class="sorts">
          <span>مرتب‌سازی:</span>
          <?php foreach (['new' => 'جدیدترین', 'cheap' => 'ارزان‌ترین', 'expensive' => 'گران‌ترین', 'light' => 'سبک‌ترین'] as $k => $label): ?>
            <a class="<?= $sort === $k ? 'active' : '' ?>" href="<?= e(shop_url(['sort' => $k, 'page' => null])) ?>"><?= $label ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if (!$products): ?>
        <div class="empty">محصولی با این مشخصات پیدا نشد.</div>
      <?php else: ?>
        <div class="grid"><?php foreach ($products as $p) echo product_card($p, $gp); ?></div>
      <?php endif; ?>
      <?php if ($pages > 1): ?>
        <div class="pager">
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= e(shop_url(['page' => $i])) ?>"><?= fa_digits((string)$i) ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php render_footer();
