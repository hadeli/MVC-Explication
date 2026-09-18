<?php
// Front controller : prépare les dépendances, transforme la requête en objet, délègue au routeur, envoie la réponse.
declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\HomeController;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Router;
use App\Core\View;
use App\Model\UserRepository;

$racine = dirname(__DIR__);

require $racine . '/autoload.php';

Env::charger($racine . '/.env');
session_start();

$repository = new UserRepository(Database::connexion($racine));
$view = new View($racine . '/views', $racine . '/cache', ['utilisateurConnecte' => $_SESSION['utilisateur'] ?? null]);

$router = new Router(
    require $racine . '/config/routes.php',
    [
        HomeController::class => fn() => new HomeController($view),
        AuthController::class => fn() => new AuthController($repository, $view),
    ],
    $view,
);

$router->dispatch(Request::fromGlobals())->send();
