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
        $products = Content::productsForJs($this->content->products());
        $services = $this->content->services();
        $s = $this->settings->all();
        $flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP;
        return $this->view->render($res, 'site.twig', [
            's' => $s,
            'services' => $services,
            'steps' => $this->content->steps(),
            'industries' => array_values(array_filter(array_map('trim', explode("\n", $s['industries_list'] ?? '')))),
            'products_json' => json_encode($products, $flags),
            'service_options_json' => json_encode(array_column($services, 'title'), $flags),
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
