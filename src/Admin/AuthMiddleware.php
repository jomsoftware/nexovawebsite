<?php
namespace App\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

final class AuthMiddleware
{
    public function __invoke(Request $req, Handler $handler): Response
    {
        if (empty($_SESSION['admin'])) {
            return (new SlimResponse())->withHeader('Location', '/admin/login')->withStatus(302);
        }
        $res = $handler->handle($req);
        return $res->withHeader('Cache-Control', 'no-store')->withHeader('X-Robots-Tag', 'noindex');
    }
}
