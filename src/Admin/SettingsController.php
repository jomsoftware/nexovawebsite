<?php
namespace App\Admin;

use App\Support\Settings;
use App\Support\SettingsSchema;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class SettingsController extends Controller
{
    public function __construct(Twig $view, PDO $db, private Settings $settings)
    {
        parent::__construct($view, $db);
    }

    public function form(Request $req, Response $res): Response
    {
        return $this->render($res, 'settings', ['groups' => SettingsSchema::groups(), 'values' => $this->settings->all()]);
    }

    public function save(Request $req, Response $res): Response
    {
        $b = $this->body($req);
        $out = [];
        foreach (SettingsSchema::keys() as $k) {
            if (array_key_exists($k, $b)) { // never blank a setting the form didn't submit
                $out[$k] = trim((string) $b[$k]);
            }
        }
        $this->settings->save($out);
        return $this->redirect($res, '/admin/settings', 'Settings saved.');
    }
}
