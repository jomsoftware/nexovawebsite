<?php
namespace App\Controllers;

use App\Support\Content;
use App\Support\Db;
use App\Support\Settings;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class SiteController
{
    public function __construct(private Twig $view, private Content $content, private Settings $settings) {}

    public function home(Request $req, Response $res): Response
    {
        $s = $this->settings->all();
        $lines = fn(string $k) => array_values(array_filter(array_map('trim', explode("\n", $s[$k] ?? ''))));
        return $this->view->render($res, 'site.twig', [
            's' => $s,
            'industries' => $this->content->industries(),
            'steps' => $this->content->steps(),
            'pains' => $lines('hero_pains'),
        ]);
    }

    /** Static legal pages: privacy, cookies, terms. */
    public function legal(Request $req, Response $res, array $args): Response
    {
        $titles = ['privacy' => 'Privacy Policy', 'cookies' => 'Cookie Policy', 'terms' => 'Terms of Use'];
        $page = $args['page'];
        return $this->view->render($res, 'legal.twig', [
            's' => $this->settings->all(),
            'page' => $page,
            'doc' => ['title' => $titles[$page]],
            'updated' => '16 October 2026',
        ]);
    }

    /** Serve admin-uploaded files from the data volume. */
    public function upload(Request $req, Response $res, array $args): Response
    {
        $file = basename($args['file']);
        $path = Db::dataDir() . '/uploads/' . $file;
        if (!is_file($path)) {
            return $res->withStatus(404);
        }
        $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];
        $type = $types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;
        if (!$type) {
            return $res->withStatus(404);
        }
        $res->getBody()->write(file_get_contents($path));
        return $res->withHeader('Content-Type', $type)->withHeader('Cache-Control', 'public, max-age=86400')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    }
}
