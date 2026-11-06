<?php
namespace App\Controllers;

use App\Support\Content;
use App\Support\Db;
use App\Support\Settings;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

final class SiteController
{
    public function __construct(private Twig $view, private Content $content, private Settings $settings) {}

    private function baseUrl(): string
    {
        return rtrim($_ENV['SITE_URL'] ?? 'https://nexovaph.com', '/');
    }

    private static function clip(string $text, int $max = 158): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, mb_strrpos(mb_substr($text, 0, $max - 1), ' ')), " ,.;:") . '…';
    }

    private static function ld(array $data): string
    {
        return json_encode($data + ['@context' => 'https://schema.org'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /** Variables every public page needs. */
    private function common(array $seo): array
    {
        $s = $this->settings->all();
        $base = $this->baseUrl();
        return [
            's' => $s,
            'industries' => $this->content->industries(),
            'seo' => $seo + ['og_image' => $base . '/assets/og.jpg', 'og_type' => 'website', 'jsonld' => []],
            'site_url' => $base,
        ];
    }

    private function org(array $s): array
    {
        $base = $this->baseUrl();
        return [
            '@type' => 'ProfessionalService', '@id' => $base . '/#org', 'name' => 'Nexova', 'url' => $base . '/',
            'logo' => $base . '/assets/logo.png', 'image' => $base . '/assets/og.jpg',
            'email' => $s['contact_email'] ?? null, 'telephone' => $s['contact_phone'] ?? null,
            'description' => $s['seo_description'] ?? '', 'areaServed' => ['@type' => 'Country', 'name' => 'Philippines'],
        ];
    }

    public function home(Request $req, Response $res): Response
    {
        $base = $this->baseUrl();
        $s = $this->settings->all();
        $desc = $s['seo_description'] ?? $s['hero_lede'] ?? '';
        $data = $this->common([
            'title' => $s['seo_title'] ?? 'Nexova',
            'description' => $desc,
            'canonical' => $base . '/',
            'jsonld' => [
                self::ld($this->org($s)),
                self::ld(['@type' => 'WebSite', 'name' => 'Nexova', 'url' => $base . '/']),
            ],
        ]);
        return $this->view->render($res, 'site.twig', $data + ['steps' => $this->content->steps(), 'ai' => $this->content->aiServices()]);
    }

    public function industry(Request $req, Response $res, array $args): Response
    {
        $match = null;
        foreach ($this->content->industries() as $i) {
            if ($i['slug'] === $args['slug']) {
                $match = $i;
            }
        }
        if (!$match) {
            throw new HttpNotFoundException($req);
        }
        $base = $this->baseUrl();
        $url = $base . '/industries/' . $match['slug'];
        $data = $this->common([
            'title' => $match['title'] . ' applications in the Philippines | Nexova',
            'description' => self::clip($match['solution']),
            'canonical' => $url,
            'jsonld' => [
                self::ld([
                    '@type' => 'Service', 'name' => $match['title'] . ' applications', 'serviceType' => $match['title'],
                    'description' => $match['solution'], 'url' => $url, 'areaServed' => ['@type' => 'Country', 'name' => 'Philippines'],
                    'provider' => ['@id' => $base . '/#org'],
                ]),
                self::ld([
                    '@type' => 'BreadcrumbList', 'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base . '/'],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Industries', 'item' => $base . '/#industries'],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $match['title'], 'item' => $url],
                    ],
                ]),
            ],
        ]);
        return $this->view->render($res, 'industry.twig', $data + ['i' => $match]);
    }

    /** Static legal pages: privacy, cookies, terms. */
    public function legal(Request $req, Response $res, array $args): Response
    {
        $titles = ['privacy' => 'Privacy Policy', 'cookies' => 'Cookie Policy', 'terms' => 'Terms of Use'];
        $page = $args['page'];
        $data = $this->common([
            'title' => $titles[$page] . ' | Nexova',
            'description' => $titles[$page] . ' for the Nexova website.',
            'canonical' => $this->baseUrl() . '/' . $page,
        ]);
        return $this->view->render($res, 'legal.twig', $data + ['contact_href' => '/#contact', 'page' => $page, 'doc' => ['title' => $titles[$page]], 'updated' => '16 October 2026']);
    }

    public function sitemap(Request $req, Response $res): Response
    {
        $base = $this->baseUrl();
        $paths = ['/'];
        foreach ($this->content->industries() as $i) {
            $paths[] = '/industries/' . $i['slug'];
        }
        array_push($paths, '/privacy', '/cookies', '/terms');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($paths as $p) {
            $xml .= '  <url><loc>' . htmlspecialchars($base . $p, ENT_XML1) . '</loc></url>' . "\n";
        }
        $res->getBody()->write($xml . '</urlset>' . "\n");
        return $res->withHeader('Content-Type', 'application/xml; charset=utf-8')->withHeader('Cache-Control', 'public, max-age=3600');
    }

    public function robots(Request $req, Response $res): Response
    {
        $res->getBody()->write("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /uploads/\n\nSitemap: " . $this->baseUrl() . "/sitemap.xml\n");
        return $res->withHeader('Content-Type', 'text/plain; charset=utf-8')->withHeader('Cache-Control', 'public, max-age=3600');
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
