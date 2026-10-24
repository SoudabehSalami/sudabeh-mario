<?php
// نمایش رسید واریز (فقط برای مدیر؛ رسیدها در پوشه‌ی محافظت‌شده نگه‌داری می‌شوند)
require __DIR__ . '/_lib.php';
require_admin();

$st = db()->prepare('SELECT receipt FROM orders WHERE id = ?');
$st->execute([(int)($_GET['id'] ?? 0)]);
$file = RECEIPT_DIR . '/' . basename((string)$st->fetchColumn());
if (!is_file($file)) {
    http_response_code(404);
    exit;
}
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=3600');
readfile($file);
