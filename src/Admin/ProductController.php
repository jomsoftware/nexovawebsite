<?php
namespace App\Admin;

use App\Support\Db;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ProductController extends Controller
{
    private const MAX_LOGO = 2 * 1024 * 1024;
    private const LOGO_TYPES = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];

    public function index(Request $req, Response $res): Response
    {
        $rows = $this->db->query('SELECT id,name,category,logo,url,active FROM products ORDER BY sort, id')->fetchAll();
        return $this->render($res, 'products', ['rows' => $rows]);
    }

    public function form(Request $req, Response $res, array $a): Response
    {
        $p = ['id' => null, 'name' => '', 'name_html' => '', 'category' => '', 'audience' => '', 'description' => '', 'url' => '',
            'mark' => '', 'mark_bg' => '', 'logo' => null, 'active' => 1, 'features' => []];
        if (isset($a['id'])) {
            $st = $this->db->prepare('SELECT * FROM products WHERE id = ?');
            $st->execute([$a['id']]);
            $p = $st->fetch() ?: null;
            if (!$p) {
                return $res->withStatus(404);
            }
            $f = $this->db->prepare('SELECT text FROM product_features WHERE product_id = ? ORDER BY sort, id');
            $f->execute([$p['id']]);
            $p['features'] = $f->fetchAll(PDO::FETCH_COLUMN);
        }
        return $this->render($res, 'product_form', ['p' => $p, 'error' => null]);
    }

    public function save(Request $req, Response $res, array $a): Response
    {
        $b = $this->body($req);
        $id = isset($a['id']) ? (int) $a['id'] : null;
        $name = trim((string) ($b['name'] ?? ''));
        $url = trim((string) ($b['url'] ?? ''));
        $features = array_values(array_filter(array_map('trim', explode("\n", (string) ($b['features'] ?? '')))));

        $existing = null;
        if ($id) {
            $st = $this->db->prepare('SELECT * FROM products WHERE id = ?');
            $st->execute([$id]);
            $existing = $st->fetch();
            if (!$existing) {
                return $res->withStatus(404);
            }
        }

        $error = null;
        if ($name === '') {
            $error = 'Name is required.';
        } elseif ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $error = 'Website must start with http:// or https://.';
        }

        $logo = $existing['logo'] ?? null;
        $newLogo = null;
        if (!$error) {
            $file = $req->getUploadedFiles()['logo'] ?? null;
            if ($file && $file->getError() === UPLOAD_ERR_OK) {
                [$newLogo, $error] = $this->storeLogo($file);
            } elseif ($file && !in_array($file->getError(), [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) {
                $error = 'Logo upload failed (file too large?).';
            }
        }

        $row = [
            'name' => $name, 'name_html' => trim((string) ($b['name_html'] ?? '')) ?: null,
            'category' => trim((string) ($b['category'] ?? '')), 'audience' => trim((string) ($b['audience'] ?? '')),
            'description' => trim((string) ($b['description'] ?? '')), 'url' => $url ?: null,
            'mark' => trim((string) ($b['mark'] ?? '')) ?: null,
            'mark_bg' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($b['mark_bg'] ?? '')) ? $b['mark_bg'] : null,
            'active' => empty($b['active']) ? 0 : 1,
        ];

        if ($error) {
            $row += ['id' => $id, 'logo' => $logo, 'features' => $features];
            return $this->render($res->withStatus(422), 'product_form', ['p' => $row, 'error' => $error]);
        }
        if (!empty($b['remove_logo']) && !$newLogo) {
            $newLogo = '';
        }

        $this->db->beginTransaction();
        if ($id) {
            $logoSql = $newLogo !== null ? ', logo = :logo' : '';
            $st = $this->db->prepare("UPDATE products SET name=:name,name_html=:name_html,category=:category,audience=:audience,description=:description,url=:url,mark=:mark,mark_bg=:mark_bg,active=:active$logoSql WHERE id=:id");
            $params = $row + ['id' => $id];
            if ($newLogo !== null) {
                $params['logo'] = $newLogo ?: null;
            }
            $st->execute($params);
        } else {
            $st = $this->db->prepare('INSERT INTO products (slug,name,name_html,category,audience,description,url,mark,mark_bg,logo,active,sort) VALUES (:slug,:name,:name_html,:category,:audience,:description,:url,:mark,:mark_bg,:logo,:active,(SELECT COALESCE(MAX(sort),-1)+1 FROM products))');
            $st->execute($row + ['slug' => $this->slug($name), 'logo' => $newLogo ?: null]);
            $id = (int) $this->db->lastInsertId();
        }
        $this->db->prepare('DELETE FROM product_features WHERE product_id = ?')->execute([$id]);
        $ins = $this->db->prepare('INSERT INTO product_features (product_id,text,sort) VALUES (?,?,?)');
        foreach ($features as $i => $t) {
            $ins->execute([$id, $t, $i]);
        }
        $this->db->commit();

        if ($newLogo !== null && ($existing['logo'] ?? null)) {
            $this->deleteLogo($existing['logo']);
        }
        return $this->redirect($res, '/admin/products', 'Product saved.');
    }

    public function delete(Request $req, Response $res, array $a): Response
    {
        $st = $this->db->prepare('SELECT logo FROM products WHERE id = ?');
        $st->execute([$a['id']]);
        $logo = $st->fetchColumn();
        $this->db->prepare('DELETE FROM products WHERE id = ?')->execute([$a['id']]);
        if ($logo) {
            $this->deleteLogo($logo);
        }
        return $this->redirect($res, '/admin/products', 'Product deleted.');
    }

    public function move(Request $req, Response $res, array $a): Response
    {
        $this->reorder('products', (int) $a['id'], $a['dir'] === 'up' ? 'up' : 'down');
        return $this->redirect($res, '/admin/products');
    }

    /** @return array{0:?string,1:?string} [filename, error] */
    private function storeLogo($file): array
    {
        if ($file->getSize() > self::MAX_LOGO) {
            return [null, 'Logo must be 2 MB or smaller.'];
        }
        $data = (string) $file->getStream();
        $info = @getimagesizeFromString($data);
        $ext = $info ? (self::LOGO_TYPES[$info[2]] ?? null) : null;
        if (!$ext) {
            return [null, 'Logo must be a PNG, JPG or WebP image.'];
        }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        file_put_contents(Db::dataDir() . '/uploads/' . $name, $data);
        return [$name, null];
    }

    private function deleteLogo(string $name): void
    {
        $path = Db::dataDir() . '/uploads/' . basename($name);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function slug(string $name): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'product';
        $slug = $base;
        $chk = $this->db->prepare('SELECT 1 FROM products WHERE slug = ?');
        for ($i = 2; ; $i++) {
            $chk->execute([$slug]);
            if (!$chk->fetchColumn()) {
                return $slug;
            }
            $slug = "$base-$i";
        }
    }
}
