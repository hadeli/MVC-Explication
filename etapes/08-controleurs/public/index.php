<?php
// Front controller : construit les dépendances, trouve le contrôleur, l'appelle.
declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\HomeController;
use App\Model\UserRepository;

$racine = dirname(__DIR__);

require $racine . '/autoload.php';
require $racine . '/includes/render.php';
require $racine . '/includes/db.php';

session_start();

// Les dépendances sont construites ici, une seule fois, et injectées dans les contrôleurs.
$repository = new UserRepository($pdo);
$controleurs = [
    HomeController::class => fn() => new HomeController(),
    AuthController::class => fn() => new AuthController($repository),
];

$methode = $_SERVER['REQUEST_METHOD'];
$chemin = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$chemin = rtrim($chemin, '/') ?: '/';

foreach (require $racine . '/config/routes.php' as [$routeMethode, $routeChemin, [$classe, $action]]) {
    if ($routeMethode === $methode && $routeChemin === $chemin) {
        $controleur = $controleurs[$classe]();
        $controleur->$action();
        exit;
    }
}

http_response_code(404);
render('404', ['chemin' => $chemin]);
