<?php
// Usage: php bin/seed.php [--force]   Seeds from database/seed/content.json (skips if products exist).
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

use App\Support\Db;

$pdo = Db::connect();
$force = in_array('--force', $argv, true);
$c = json_decode(file_get_contents(__DIR__ . '/../database/seed/content.json'), true);
$settings = json_decode(file_get_contents(__DIR__ . '/../database/seed/settings.json'), true);
$put = $pdo->prepare('INSERT INTO settings (key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');
$fill = $pdo->prepare('INSERT OR IGNORE INTO settings (key,value) VALUES (?,?)');

// Copy revision: bump when the seeded wording is rewritten. Existing databases get the new copy once,
// industries and steps are reseeded too, and CMS edits made after that are never touched again.
const COPY_REV = '4';
$rev = $pdo->query("SELECT value FROM settings WHERE key='copy_rev'")->fetchColumn();
$empty = (int) $pdo->query('SELECT COUNT(*) FROM industries')->fetchColumn() === 0;

foreach ($settings as $k => $v) { $fill->execute([$k, $v]); } // new keys appear without touching edited ones
if ((int) $pdo->query('SELECT COUNT(*) FROM ai_services')->fetchColumn() === 0) {
    $ai = $pdo->prepare('INSERT INTO ai_services (title,body,outcome,sort) VALUES (?,?,?,?)');
    foreach ($c['ai_services'] as $x) { $ai->execute([$x['title'], $x['body'], $x['outcome'], $x['sort']]); }
    echo "Seeded " . count($c['ai_services']) . " AI services.\n";
}
if (!$force && $rev === COPY_REV && !$empty) {
    echo "Already seeded (use --force to wipe and reseed).\n";
    exit(0);
}
$pdo->beginTransaction();
if ($force || $empty || $rev !== COPY_REV) {
    $pdo->exec('DELETE FROM industries');
    $i = $pdo->prepare('INSERT INTO industries (title,body,solution,outcome,sort) VALUES (?,?,?,?,?)');
    foreach ($c['industries'] as $x) { $i->execute([$x['title'], $x['body'], $x['solution'], $x['outcome'], $x['sort']]); }
}
if ($force || $rev !== COPY_REV) {
    $pdo->exec('DELETE FROM process_steps');
    $s = $pdo->prepare('INSERT INTO process_steps (title,body,deliverable,sort) VALUES (?,?,?,?)');
    foreach ($c['steps'] as $x) { $s->execute([$x['title'], $x['body'], $x['deliverable'], $x['sort']]); }
    foreach ($settings as $k => $v) { $put->execute([$k, $v]); }
} else {
    foreach ($settings as $k => $v) { $fill->execute([$k, $v]); }
}
$put->execute(['copy_rev', COPY_REV]);
$pdo->commit();
echo "Seeded: " . count($c['industries']) . " industries, " . count($c['steps']) . " steps, copy rev " . COPY_REV . ".\n";
