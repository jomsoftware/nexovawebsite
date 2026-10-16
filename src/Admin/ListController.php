<?php
namespace App\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use PDO;

/** Generic CRUD for the small ordered lists (services, process steps). */
final class ListController extends Controller
{
    public const CONFIG = [
        'services' => ['table' => 'services', 'title' => 'Services', 'single' => 'service', 'fields' => [
            'title' => ['Title', 'text'], 'body' => ['Description', 'textarea'], 'icon' => ['Icon (inline SVG, optional)', 'code'],
        ]],
        'steps' => ['table' => 'process_steps', 'title' => 'How we work steps', 'single' => 'step', 'fields' => [
            'title' => ['Title', 'text'], 'body' => ['Description', 'textarea'], 'deliverable' => ['Deliverable line', 'text'],
        ]],
    ];

    private const DEFAULT_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8"/></svg>';

    private function cfg(array $a): ?array
    {
        return self::CONFIG[$a['kind']] ?? null;
    }

    public function index(Request $req, Response $res, array $a): Response
    {
        if (!$c = $this->cfg($a)) {
            return $res->withStatus(404);
        }
        $rows = $this->db->query("SELECT * FROM {$c['table']} ORDER BY sort, id")->fetchAll();
        return $this->render($res, 'list', ['c' => $c, 'kind' => $a['kind'], 'rows' => $rows]);
    }

    public function form(Request $req, Response $res, array $a): Response
    {
        if (!$c = $this->cfg($a)) {
            return $res->withStatus(404);
        }
        $row = array_fill_keys(array_keys($c['fields']), '');
        $row['id'] = null;
        if (isset($a['id'])) {
            $st = $this->db->prepare("SELECT * FROM {$c['table']} WHERE id = ?");
            $st->execute([$a['id']]);
            if (!$row = $st->fetch()) {
                return $res->withStatus(404);
            }
        }
        return $this->render($res, 'list_form', ['c' => $c, 'kind' => $a['kind'], 'row' => $row, 'error' => null]);
    }

    public function save(Request $req, Response $res, array $a): Response
    {
        if (!$c = $this->cfg($a)) {
            return $res->withStatus(404);
        }
        $b = $this->body($req);
        $vals = [];
        foreach ($c['fields'] as $k => $_) {
            $vals[$k] = trim((string) ($b[$k] ?? ''));
        }
        if ($vals['title'] === '') {
            $row = $vals + ['id' => $a['id'] ?? null];
            return $this->render($res->withStatus(422), 'list_form', ['c' => $c, 'kind' => $a['kind'], 'row' => $row, 'error' => 'Title is required.']);
        }
        if (isset($vals['icon'])) {
            // Admin-authored markup is rendered raw on the public page, so only accept a single <svg> element.
            if ($vals['icon'] === '') {
                $vals['icon'] = self::DEFAULT_ICON;
            } elseif (!preg_match('#^<svg[\s>].*</svg>$#s', $vals['icon']) || preg_match('#<script|on\w+\s*=|javascript:#i', $vals['icon'])) {
                $row = $vals + ['id' => $a['id'] ?? null];
                return $this->render($res->withStatus(422), 'list_form', ['c' => $c, 'kind' => $a['kind'], 'row' => $row, 'error' => 'Icon must be a single clean <svg> element (no scripts or event handlers).']);
            }
        }
        $cols = array_keys($vals);
        if (isset($a['id'])) {
            $set = implode(',', array_map(fn($k) => "$k = :$k", $cols));
            $this->db->prepare("UPDATE {$c['table']} SET $set WHERE id = :id")->execute($vals + ['id' => $a['id']]);
        } else {
            $this->db->prepare("INSERT INTO {$c['table']} (" . implode(',', $cols) . ',sort) VALUES (' . implode(',', array_map(fn($k) => ":$k", $cols)) . ",(SELECT COALESCE(MAX(sort),-1)+1 FROM {$c['table']}))")->execute($vals);
        }
        return $this->redirect($res, "/admin/{$a['kind']}", ucfirst($c['single']) . ' saved.');
    }

    public function delete(Request $req, Response $res, array $a): Response
    {
        if (!$c = $this->cfg($a)) {
            return $res->withStatus(404);
        }
        $this->db->prepare("DELETE FROM {$c['table']} WHERE id = ?")->execute([$a['id']]);
        return $this->redirect($res, "/admin/{$a['kind']}", ucfirst($c['single']) . ' deleted.');
    }

    public function move(Request $req, Response $res, array $a): Response
    {
        if (!$c = $this->cfg($a)) {
            return $res->withStatus(404);
        }
        $this->reorder($c['table'], (int) $a['id'], $a['dir'] === 'up' ? 'up' : 'down');
        return $this->redirect($res, "/admin/{$a['kind']}");
    }
}
