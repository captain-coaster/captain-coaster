<?php

declare(strict_types=1);

// Fails when an admin route escapes /team (and so security.yaml's access_control) or the admin_ names, or when a
// route outside the admin lands there. Run in CI: php scripts/check-admin-routes.php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';
new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', false);
$kernel->boot();

$errors = [];
foreach ($kernel->getContainer()->get('router')->getRouteCollection() as $name => $route) {
    $controller = (string) $route->getDefault('_controller');
    $isAdmin = str_starts_with($controller, 'App\\Admin\\');
    $underTeam = '/team' === $route->getPath() || str_starts_with($route->getPath(), '/team/');
    $adminName = 'admin' === $name || str_starts_with($name, 'admin_');

    if ($isAdmin && !($underTeam && $adminName)) {
        $errors[] = "$name ({$route->getPath()}): an admin route must live under /team and be named admin_*";
    } elseif (!$isAdmin && ($underTeam || $adminName)) {
        $errors[] = "$name ({$route->getPath()}): only src/Admin may use /team or an admin_* name";
    }
}

foreach ($errors as $error) {
    fwrite(\STDERR, "$error\n");
}
echo $errors ? '' : "Admin routes OK\n";

exit($errors ? 1 : 0);
