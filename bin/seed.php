<?php
// Usage: php bin/seed.php [--force]   Seeds from database/seed/content.json (skips if products exist).
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

use App\Support\Db;

$pdo = Db::connect();
$force = in_array('--force', $argv, true);
if (!$force && $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() > 0) {
    echo "Already seeded (use --force to wipe and reseed).\n";
    exit(0);
}
$c = json_decode(file_get_contents(__DIR__ . '/../database/seed/content.json'), true);
$pdo->beginTransaction();
foreach (['product_features', 'products', 'services', 'process_steps'] as $t) {
    $pdo->exec("DELETE FROM $t");
}
$up = Db::dataDir() . '/uploads';
$p = $pdo->prepare('INSERT INTO products (slug,name,name_html,category,audience,description,url,mark,mark_bg,mark_class,logo,sort) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
$f = $pdo->prepare('INSERT INTO product_features (product_id,text,sort) VALUES (?,?,?)');
foreach ($c['products'] as $x) {
    if ($x['logo']) {
        copy(__DIR__ . '/../database/seed/logos/' . $x['logo'], "$up/{$x['logo']}");
    }
    $p->execute([$x['slug'], $x['name'], $x['name_html'], $x['category'], $x['audience'], $x['description'], $x['url'],
        $x['mark'], $x['mark_bg'], $x['mark_class'], $x['logo'], $x['sort']]);
    $id = (int) $pdo->lastInsertId();
    foreach ($x['features'] as $i => $t) {
        $f->execute([$id, $t, $i]);
    }
}
$s = $pdo->prepare('INSERT INTO services (title,body,icon,sort) VALUES (?,?,?,?)');
foreach ($c['services'] as $x) { $s->execute([$x['title'], $x['body'], $x['icon'], $x['sort']]); }
$s = $pdo->prepare('INSERT INTO process_steps (title,body,deliverable,sort) VALUES (?,?,?,?)');
foreach ($c['steps'] as $x) { $s->execute([$x['title'], $x['body'], $x['deliverable'], $x['sort']]); }
$st = $pdo->prepare('INSERT INTO settings (key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');
foreach (json_decode(file_get_contents(__DIR__ . '/../database/seed/settings.json'), true) as $k => $v) { $st->execute([$k, $v]); }
$pdo->commit();
echo "Seeded: " . count($c['products']) . " products, " . count($c['services']) . " services, " . count($c['steps']) . " steps.\n";
