<?php
// Front controller : prépare les dépendances, transforme la requête en objet, délègue au routeur, envoie la réponse.
declare(strict_types=1);

use App\Core\Container;
use App\Core\ControllerCache;
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

// Balayage du dossier et lecture des constructeurs : faits une fois, relus depuis cache/controleurs.php ensuite.
$plans = (new ControllerCache($racine . '/src/Controller', 'App\\Controller', $racine . '/cache/controleurs.php'))->charger();

$container = new Container([View::class => $view, UserRepository::class => $repository], $plans);

$router = new Router(array_keys($plans), $container, $view);

$router->dispatch(Request::fromGlobals())->send();
