# Étape 9 : extraire le noyau

> **Objectif** : séparer ce qui est propre à **cette** application (utilisateurs, connexion) de ce qui servirait
> à **n'importe quelle** application (lire une requête, envoyer une réponse, router, rendre une vue).

## Le problème au départ

Dans `public/index.php` et les contrôleurs de l'étape 8, une partie du code ne parle pas d'utilisateurs :
`parse_url($_SERVER['REQUEST_URI'])`, la boucle sur les routes, `header('Location: ...')`, `exit`,
`http_response_code(404)`, `extract()` + `require` dans `render()`. Si vous démarriez un blog demain, vous
copieriez ce code tel quel. Il mérite donc d'être isolé, nommé et réutilisable.

Plus concret : les contrôleurs appellent `exit`. Un test qui appelle `AuthController::login()` s'arrête net
avec le script. Et ils lisent `$_POST` : pour les tester il faut remplir une superglobale à la main.

## Ce qui change

```
09-noyau/
├── public/index.php              assemble, délègue, envoie
├── config/routes.php
├── src/
│   ├── Core/                     NOUVEAU : le mini framework
│   │   ├── Env.php               ancien includes/env.php
│   │   ├── Database.php          ancien includes/db.php
│   │   ├── Request.php           enveloppe $_GET, $_POST, $_SERVER, $_SESSION
│   │   ├── Response.php          statut, en-têtes, corps ; send()
│   │   ├── View.php              rend une vue dans le layout, renvoie une Response
│   │   └── Router.php            trouve le contrôleur, renvoie sa Response
│   ├── Controller/               reçoivent une Request, RETOURNENT une Response
│   └── Model/
├── autoload.php                  toujours inchangé
└── views/
    ├── layout.php                NOUVEAU : remplace partials/header.php + footer.php
    ├── home.php, login.php, register.php, 404.php
```

Le dossier `includes/` disparaît : tout est devenu classe, donc autoloadé.

## Comment ça marche

### `Request` : la requête devient un objet

```php
$request = Request::fromGlobals();
$request->method;                 // "POST"
$request->path;                   // "/login"
$request->post('email', '');      // au lieu de $_POST['email'] ?? ''
$request->session('utilisateur'); // au lieu de $_SESSION['utilisateur'] ?? null
```

`fromGlobals()` est le **seul** endroit du projet qui lit les superglobales. Partout ailleurs on manipule
l'objet. Pour un test, on construit une `Request` à la main : `new Request('POST', '/login', [], ['email' => ...], [])`.

### `Response` : rien n'est envoyé avant la fin

```php
return Response::redirect('/');                   // 302 + Location, pas encore envoyé
return $this->view->render('login', [...]);       // 200 + HTML, pas encore envoyé
```

Un contrôleur ne fait plus `header()` ni `echo` ni `exit` : il **retourne** une description de la réponse.
C'est `public/index.php` qui appelle `send()`, une seule fois, tout à la fin. Conséquences : plus d'erreur
« headers already sent », un contrôleur testable (on inspecte `$response->statut` et `$response->corps`),
et un endroit unique pour ajouter plus tard un en-tête commun à toutes les réponses.

### `View` : une vue dans un layout

```php
public function render(string $vue, array $donnees = [], int $statut = 200): Response
{
    $donnees += $this->partage;                                   // ex. utilisateurConnecte
    $contenu = $this->capturer($vue . '.php', $donnees);          // 1. la vue, dans un tampon
    $html = $this->capturer('layout.php', $donnees + ['contenu' => $contenu]);   // 2. le layout
    return new Response($statut, $html);
}
```

`ob_start()` / `ob_get_clean()` capturent la sortie d'un `require` dans une chaîne au lieu de l'envoyer.
La vue est exécutée **d'abord**, son HTML est mis dans `$contenu`, puis le layout est exécuté et affiche
`<?= $contenu ?>` à l'endroit voulu. Les partials `header.php` et `footer.php` fusionnent en un seul
`layout.php` : plus de balise ouverte dans un fichier et fermée dans un autre.

### `Router` : trouver et appeler

```php
public function dispatch(Request $request): Response
{
    foreach ($this->routes as [$methode, $chemin, [$classe, $action]]) {
        if ($methode === $request->method && $chemin === $request->path) {
            $controleur = ($this->fabriques[$classe])();
            return $controleur->$action($request);
        }
    }
    return $this->view->render('404', ['chemin' => $request->path], 404);
}
```

Même logique qu'à l'étape 8, mais dans une classe qui reçoit ses routes et ses fabriques, et qui **retourne**
la réponse au lieu de laisser le contrôleur l'envoyer.

### `public/index.php` : dix lignes utiles

```php
require $racine . '/autoload.php';
Env::charger($racine . '/.env');
session_start();

$repository = new UserRepository(Database::connexion($racine));
$view = new View($racine . '/views', ['utilisateurConnecte' => $_SESSION['utilisateur'] ?? null]);
$router = new Router(require $racine . '/config/routes.php', [/* fabriques */], $view);

$router->dispatch(Request::fromGlobals())->send();
```

Le front controller ne fait plus que **câbler** : il construit les objets, les relie, et lance.

## Cheminement d'une requête

Décrit pas à pas, avec le code de chaque classe, dans `MVC.md` à la racine du dépôt (section 4).

```
POST /login → index.php → Request::fromGlobals() → Router::dispatch()
  → AuthController::login($request) → UserRepository::findByEmail() → User::verifierMotDePasse()
  → $request->setSession(...) → Response::redirect('/') → remonte au Router → à index.php → send()
```

## Tester un contrôleur sans serveur web

```php
<?php
require __DIR__ . '/autoload.php';

use App\Controller\AuthController;
use App\Core\{Request, View};
use App\Model\UserRepository;

$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, password TEXT)');

$controleur = new AuthController(new UserRepository($pdo), new View(__DIR__ . '/views', ['utilisateurConnecte' => null]));
$request = new Request('POST', '/login', [], ['email' => 'a@b.fr', 'password' => 'x'], []);

$response = $controleur->login($request);
echo $response->statut;                                   // 200 : formulaire réaffiché
echo str_contains($response->corps, 'incorrect') ? ' avec erreur' : ''; // avec erreur
```

Aucun navigateur, aucune superglobale, aucun `exit`. C'est le bénéfice principal de l'étape.

## Le sens des dépendances

```
index.php → Router → Controller → Model → PDO
                         └──────→ View → Response
```

Le modèle ne connaît ni la vue ni la requête. La vue ne connaît que ses données. Le contrôleur connaît tout
le monde mais ne fait rien lui-même. Toute flèche dans l'autre sens est une erreur d'architecture.

## Pièges

- **Un contrôleur qui garde un `$_SESSION`.** Tout doit passer par `Request` ; sinon le test ne peut plus
  simuler la session.
- **Appeler `send()` ailleurs qu'à la fin de `index.php`.** Une réponse envoyée deux fois, ou envoyée avant
  qu'une redirection soit décidée.
- **Un layout qui lit `$_SESSION`** pour la navigation. L'utilisateur connecté est passé en donnée partagée
  au constructeur de `View`, précisément pour éviter cela.

## Ce qui reste imparfait

Les vues sont du PHP : `<?= htmlspecialchars($email) ?>` partout, et rien n'empêche d'oublier l'échappement
une fois. Le layout n'a qu'un emplacement, `$contenu`. Étape 10.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000 -t public
./verifier.sh 09
```

**Questions du plan** : `PLAN.md`, étape 9.
