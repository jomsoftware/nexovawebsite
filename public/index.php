<?php
use App\Admin;
use App\Support\Db;
use DI\Container;
use Slim\Csrf\Guard;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) && !str_ends_with($_SERVER['REQUEST_URI'], '/')) {
    return false; // let the built-in server serve static assets
}

require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
$_ENV += getenv(); // real env vars (Railway) even when variables_order omits E

// Sessions live on the data volume so logins survive deploys.
$sessDir = Db::dataDir() . '/sessions';
is_dir($sessDir) || mkdir($sessDir, 0770, true);
session_save_path($sessDir);
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ($_SERVER['HTTPS'] ?? '')) === 'https' || ($_SERVER['HTTPS'] ?? '') === 'on',
]);
session_start();

$container = new Container();
$container->set(PDO::class, fn() => Db::connect());
$container->set(Twig::class, fn() => Twig::create(__DIR__ . '/../templates', ['cache' => false, 'autoescape' => 'html']));
AppFactory::setContainer($container);
$app = AppFactory::create();

$responseFactory = $app->getResponseFactory();
$guard = new Guard($responseFactory, 'csrf', persistentTokenMode: true);
$guard->setFailureHandler(function ($req, $handler) use ($responseFactory) {
    $res = $responseFactory->createResponse(400)->withHeader('Content-Type', 'application/json');
    $res->getBody()->write(json_encode(['ok' => false, 'message' => 'Session expired. Please refresh the page and try again.']));
    return $res;
});

$app->add(TwigMiddleware::createFromContainer($app, Twig::class));
// Expose CSRF token pair to templates (needs to run after the guard, so add guard last).
$app->add(function ($req, $handler) use ($container, $guard) {
    $container->get(Twig::class)->getEnvironment()->addGlobal('csrf', [
        'keys' => ['name' => $guard->getTokenNameKey(), 'value' => $guard->getTokenValueKey()],
        'name' => $req->getAttribute($guard->getTokenNameKey()),
        'value' => $req->getAttribute($guard->getTokenValueKey()),
    ]);
    return $handler->handle($req);
});
$app->add($guard);
$app->addErrorMiddleware(($_ENV['APP_ENV'] ?? 'production') === 'local', true, true);

$app->get('/', [App\Controllers\SiteController::class, 'home']);
$app->get('/uploads/{file}', [App\Controllers\SiteController::class, 'upload']);
$app->post('/contact', [App\Controllers\ContactController::class, 'submit']);

$app->get('/admin/login', [Admin\AuthController::class, 'form']);
$app->post('/admin/login', [Admin\AuthController::class, 'login']);

$app->group('/admin', function ($g) {
    $g->get('', [Admin\DashboardController::class, 'index']);
    $g->post('/logout', [Admin\AuthController::class, 'logout']);

    $g->get('/products', [Admin\ProductController::class, 'index']);
    $g->get('/products/new', [Admin\ProductController::class, 'form']);
    $g->post('/products', [Admin\ProductController::class, 'save']);
    $g->get('/products/{id:[0-9]+}', [Admin\ProductController::class, 'form']);
    $g->post('/products/{id:[0-9]+}', [Admin\ProductController::class, 'save']);
    $g->post('/products/{id:[0-9]+}/delete', [Admin\ProductController::class, 'delete']);
    $g->post('/products/{id:[0-9]+}/move/{dir:up|down}', [Admin\ProductController::class, 'move']);

    $k = '{kind:services|steps}';
    $g->get("/$k", [Admin\ListController::class, 'index']);
    $g->get("/$k/new", [Admin\ListController::class, 'form']);
    $g->post("/$k", [Admin\ListController::class, 'save']);
    $g->get("/$k/{id:[0-9]+}", [Admin\ListController::class, 'form']);
    $g->post("/$k/{id:[0-9]+}", [Admin\ListController::class, 'save']);
    $g->post("/$k/{id:[0-9]+}/delete", [Admin\ListController::class, 'delete']);
    $g->post("/$k/{id:[0-9]+}/move/{dir:up|down}", [Admin\ListController::class, 'move']);

    $g->get('/settings', [Admin\SettingsController::class, 'form']);
    $g->post('/settings', [Admin\SettingsController::class, 'save']);

    $g->get('/messages', [Admin\MessageController::class, 'index']);
    $g->post('/messages/{id:[0-9]+}/toggle', [Admin\MessageController::class, 'toggle']);
    $g->post('/messages/{id:[0-9]+}/delete', [Admin\MessageController::class, 'delete']);
})->add(function ($req, $handler) use ($container) {
    $n = $container->get(PDO::class)->query('SELECT COUNT(*) FROM messages WHERE read = 0')->fetchColumn();
    $container->get(Twig::class)->getEnvironment()->addGlobal('unread_count', (int) $n);
    return $handler->handle($req);
})->add(new Admin\AuthMiddleware());

$app->run();
