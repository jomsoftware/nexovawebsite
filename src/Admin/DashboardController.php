<?php
namespace App\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DashboardController extends Controller
{
    public function index(Request $req, Response $res): Response
    {
        $n = fn(string $sql) => (int) $this->db->query($sql)->fetchColumn();
        return $this->render($res, 'dashboard', [
            'counts' => [
                'products' => $n('SELECT COUNT(*) FROM products'), 'services' => $n('SELECT COUNT(*) FROM services'),
                'steps' => $n('SELECT COUNT(*) FROM process_steps'), 'unread' => $n('SELECT COUNT(*) FROM messages WHERE read = 0'),
            ],
            'recent' => $this->db->query('SELECT * FROM messages ORDER BY id DESC LIMIT 5')->fetchAll(),
        ]);
    }
}
