<?php
namespace App\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class MessageController extends Controller
{
    public function index(Request $req, Response $res): Response
    {
        $rows = $this->db->query('SELECT * FROM messages ORDER BY id DESC LIMIT 200')->fetchAll();
        return $this->render($res, 'messages', ['rows' => $rows]);
    }

    public function toggle(Request $req, Response $res, array $a): Response
    {
        $this->db->prepare('UPDATE messages SET read = 1 - read WHERE id = ?')->execute([$a['id']]);
        return $this->redirect($res, '/admin/messages');
    }

    public function delete(Request $req, Response $res, array $a): Response
    {
        $this->db->prepare('DELETE FROM messages WHERE id = ?')->execute([$a['id']]);
        return $this->redirect($res, '/admin/messages', 'Message deleted.');
    }
}
