<?php
namespace App\Admin;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

abstract class Controller
{
    public function __construct(protected Twig $view, protected PDO $db) {}

    protected function render(Response $res, string $tpl, array $data = []): Response
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $this->view->render($res, "admin/$tpl.twig", $data + ['flash' => $flash]);
    }

    protected function redirect(Response $res, string $to, ?string $flash = null): Response
    {
        if ($flash) {
            $_SESSION['flash'] = $flash;
        }
        return $res->withHeader('Location', $to)->withStatus(302);
    }

    protected function body(\Psr\Http\Message\ServerRequestInterface $req): array
    {
        return (array) $req->getParsedBody();
    }

    /** Move a row up/down within a sortable table, renumbering sort 0..n. */
    protected function reorder(string $table, int $id, string $dir): void
    {
        if (!in_array($table, ['products', 'services', 'process_steps'], true)) {
            throw new \InvalidArgumentException($table);
        }
        $ids = $this->db->query("SELECT id FROM $table ORDER BY sort, id")->fetchAll(PDO::FETCH_COLUMN);
        $i = array_search($id, array_map('intval', $ids), true);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($i === false || !isset($ids[$j])) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $st = $this->db->prepare("UPDATE $table SET sort = ? WHERE id = ?");
        foreach ($ids as $n => $rid) {
            $st->execute([$n, $rid]);
        }
    }
}
