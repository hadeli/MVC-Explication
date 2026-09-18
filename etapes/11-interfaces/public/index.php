<?php
// Front controller : prépare les dépendances, transforme la requête en objet, délègue au routeur, envoie la réponse.
declare(strict_types=1);

use App\Controller\HomeController;
use App\Controller\LoginController;
use App\Controller\LoginFormController;
use App\Controller\LogoutController;
use App\Controller\RegisterController;
use App\Controller\RegisterFormController;
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

// Plus de config/routes.php : un contrôleur par couple verbe/chemin, et chaque classe dit elle-même
// (statiquement, sans être construite) si elle prend la requête. Seule celle qui répond oui est instanciée.
// Ajouter une fonctionnalité = une classe qui implémente ControllerInterface + une ligne ici.
$router = new Router(
    [
        HomeController::class         => fn() => new HomeController($view),
        LoginFormController::class    => fn() => new LoginFormController($view),
        LoginController::class        => fn() => new LoginController($repository, $view),
        RegisterFormController::class => fn() => new RegisterFormController($view),
        RegisterController::class     => fn() => new RegisterController($repository, $view),
        LogoutController::class       => fn() => new LogoutController(),
    ],
    $view,
);

$router->dispatch(Request::fromGlobals())->send();
