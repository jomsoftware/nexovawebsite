<?php
namespace App\Support;

use PDO;

/** Reads public-site content from the DB. */
final class Content
{
    public function __construct(private PDO $db) {}

    public function products(bool $onlyActive = true): array
    {
        $rows = $this->db->query('SELECT * FROM products' . ($onlyActive ? ' WHERE active=1' : '') . ' ORDER BY sort, id')->fetchAll();
        $feat = $this->db->prepare('SELECT text FROM product_features WHERE product_id=? ORDER BY sort, id');
        foreach ($rows as &$r) {
            $feat->execute([$r['id']]);
            $r['features'] = $feat->fetchAll(PDO::FETCH_COLUMN);
        }
        return $rows;
    }

    public function services(): array
    {
        return $this->db->query('SELECT * FROM services ORDER BY sort, id')->fetchAll();
    }

    public function steps(): array
    {
        return $this->db->query('SELECT * FROM process_steps ORDER BY sort, id')->fetchAll();
    }

    /** Shape the products table into the objects the page's JS expects. */
    public static function productsForJs(array $products): array
    {
        return array_map(fn($p) => array_filter([
            'id' => $p['slug'],
            'url' => $p['url'] ?: null,
            'mark' => $p['mark'] ?: strtoupper(substr($p['name'], 0, 2)),
            'markBg' => $p['mark_bg'] ?: null,
            'markClass' => $p['mark_class'] ?: null,
            'logo' => $p['logo'] ? '/uploads/' . rawurlencode($p['logo']) : null,
            'nameHtml' => $p['name_html'] ?: null,
            'name' => $p['name'],
            'cat' => $p['category'],
            'desc' => $p['description'],
            'f' => $p['features'],
            'aud' => $p['audience'],
        ], fn($v) => $v !== null), $products);
    }
}
