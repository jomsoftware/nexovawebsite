<?php
namespace App\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController extends Controller
{
    private const MAX_FAILS = 5; // per IP per 15 minutes

    public function form(Request $req, Response $res): Response
    {
        if (!empty($_SESSION['admin'])) {
            return $this->redirect($res, '/admin');
        }
        return $this->render($res, 'login', ['error' => null]);
    }

    public function login(Request $req, Response $res): Response
    {
        $ip = $this->ip($req);
        $fails = $this->db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > datetime('now','-15 minutes')");
        $fails->execute([$ip]);
        if ($fails->fetchColumn() >= self::MAX_FAILS) {
            return $this->render($res->withStatus(429), 'login', ['error' => 'Too many attempts. Try again in 15 minutes.']);
        }

        $b = $this->body($req);
        $hash = $_ENV['ADMIN_PASSWORD_HASH'] ?? '';
        $emailOk = hash_equals(strtolower($_ENV['ADMIN_EMAIL'] ?? ''), strtolower(trim((string) ($b['email'] ?? ''))));
        // Always verify against a hash so timing doesn't reveal which field was wrong.
        $passOk = password_verify((string) ($b['password'] ?? ''), $hash ?: password_hash('x', PASSWORD_DEFAULT));

        if ($hash === '' || !$emailOk || !$passOk) {
            $this->db->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
            return $this->render($res->withStatus(401), 'login', ['error' => 'Invalid email or password.']);
        }
        $this->db->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        return $this->redirect($res, '/admin');
    }

    public function logout(Request $req, Response $res): Response
    {
        $_SESSION = [];
        session_regenerate_id(true);
        return $this->redirect($res, '/admin/login');
    }

    private function ip(Request $req): string
    {
        // Railway terminates TLS at a proxy; the client IP is the first X-Forwarded-For entry.
        $xff = $req->getHeaderLine('X-Forwarded-For');
        return $xff !== '' ? trim(explode(',', $xff)[0]) : ($req->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
    }
}
