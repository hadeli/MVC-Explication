<?php
// Front controller : toutes les requêtes passent par ici, quel que soit le chemin demandé.
declare(strict_types=1);

$racine = dirname(__DIR__);

require $racine . '/autoload.php';
require $racine . '/includes/render.php';
require $racine . '/includes/db.php';

session_start();

$routes = require $racine . '/config/routes.php';

// Chemin demandé, sans la query string ni le slash final : "/login?x=1" -> "/login"
$chemin = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$chemin = rtrim($chemin, '/') ?: '/';

if (!isset($routes[$chemin])) {
    http_response_code(404);
    render('404', ['chemin' => $chemin]);
    exit;
}

require $racine . '/actions/' . $routes[$chemin] . '.php';
