<?php
// Front controller : prépare les dépendances, transforme la requête en objet, délègue au routeur, envoie la réponse.
declare(strict_types=1);

use App\Core\Container;
use App\Core\ControllerFinder;
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

// Plus aucun contrôleur nommé ici. Ajouter une fonctionnalité = déposer une classe dans src/Controller/.
// Le conteneur ne connaît que les deux objets qu'on ne peut pas deviner (ils ont besoin de chemins et de .env).
$container = new Container([
    View::class => $view,
    UserRepository::class => $repository,
]);

$router = new Router(
    ControllerFinder::trouver($racine . '/src/Controller', 'App\\Controller'),
    $container,
    $view,
);

$router->dispatch(Request::fromGlobals())->send();
