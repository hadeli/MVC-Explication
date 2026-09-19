# Étape 10 : extraire le noyau

> **Objectif** : séparer ce qui est propre à **cette** application (utilisateurs, connexion) de ce qui servirait
> à **n'importe quelle** application (router, rendre une vue dans un layout, lire la configuration, ouvrir la base).

## Le problème au départ

Depuis l'étape 9, `Request` et `Response` sont des classes. Mais le reste du code « technique » est éparpillé :
la boucle sur les routes et la 404 dans `public/index.php`, `render()` dans une fonction globale posée par
un `require`, la connexion PDO et le chargeur `.env` dans `includes/`. Si vous démarriez un blog demain, vous
copieriez tout cela tel quel, morceau par morceau. Il mérite d'être isolé, nommé et réutilisable.

Deux détails montrent que ce code n'est pas fini :

1. `render()` lit `$_SESSION` pour la navigation. C'est la dernière superglobale hors de `Request`.
2. `views/partials/header.php` ouvre `<html>` et `<body>`, `footer.php` les ferme. Aucun fichier n'est un
   document complet, et chaque vue doit penser à inclure les deux.

## Ce qui change

```
10-noyau/
├── public/index.php              assemble, délègue, envoie
├── config/routes.php
├── src/
│   ├── Core/                     le mini framework
│   │   ├── Request.php           inchangé
│   │   ├── Response.php          inchangé
│   │   ├── View.php              NOUVEAU : rend une vue dans le layout, renvoie une Response
│   │   ├── Router.php            NOUVEAU : trouve le contrôleur, renvoie sa Response
│   │   ├── Env.php               NOUVEAU : ancien includes/env.php
│   │   └── Database.php          NOUVEAU : ancien includes/db.php
│   ├── Controller/               reçoivent la View dans leur constructeur
│   └── Model/
├── autoload.php                  toujours inchangé
└── views/
    ├── layout.php                NOUVEAU : remplace partials/header.php + footer.php
    └── home.php, login.php, register.php, 404.php
```

Le dossier `includes/` disparaît : tout est devenu classe, donc autoloadé.

## Comment ça marche

### `View` : une vue dans un layout

```php
public function render(string $vue, array $donnees = [], int $statut = 200): Response
{
    $donnees += $this->partage;                                   // ex. utilisateurConnecte
    $contenu = $this->capturer($vue . '.php', $donnees);          // 1. la vue, dans un tampon
    $html = $this->capturer('layout.php', $donnees + ['contenu' => $contenu]);   // 2. le layout
    return new Response($statut, $html);
}

private function capturer(string $fichier, array $donnees): string
{
    extract($donnees, EXTR_SKIP);
    ob_start();
    require $this->dossier . '/' . $fichier;

    return ob_get_clean();
}
```

`capturer()` est le `ob_start()` / `ob_get_clean()` de l'étape 9, utilisé **deux fois**. La vue est exécutée
**d'abord**, son HTML est mis dans `$contenu`, puis le layout est exécuté et affiche `<?= $contenu ?>` à
l'endroit voulu. Les partials `header.php` et `footer.php` fusionnent en un seul `layout.php` : plus de balise
ouverte dans un fichier et fermée dans un autre, et les vues ne contiennent plus que leur propre contenu.

L'utilisateur connecté n'est plus lu dans `$_SESSION` par `render()` : il est **donné** à `View` dans
`index.php`, comme donnée partagée par toutes les vues. `View` ne sait pas d'où il vient. Le titre de la page
suit le même chemin : c'est une donnée (`'titre' => 'Connexion'`) passée par le contrôleur, plus une variable
posée en tête de la vue.

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

Même boucle qu'à l'étape 9, mais dans une classe qui reçoit ses routes et ses fabriques dans son constructeur,
et qui **retourne** la réponse. Le `$response = null` et le `if ($response === null)` d'`index.php`
disparaissent : un `return` dans la boucle, un `return` après, c'est tout.

### `Env` et `Database` : les includes deviennent des classes

`Env::charger()` et `Env::get()` sont les fonctions `chargerEnv()` et `env()` de l'étape 3, déplacées dans une
classe et déclarées `static` : on les appelle sur la classe, `Env::get('DB_DSN')`, sans construire d'objet.
`Database::connexion($racine)` est le contenu de `includes/db.php` dans une méthode, qui retourne le `PDO` au
lieu de le laisser dans une variable globale `$pdo`. Le code ne change pas, seulement la façon d'y accéder :
plus de `require` à faire, l'autoloader les trouve dans `src/Core/`.

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

Le front controller ne fait plus que **câbler** : il construit les objets, les relie, et lance. Avec
`Request::fromGlobals()`, la ligne qui construit `View` est le seul endroit du projet qui lit `$_SESSION`.

## Cheminement d'une requête

Décrit pas à pas, avec le code de chaque classe, dans `MVC.md` à la racine du dépôt (section 4).

```
POST /login → index.php → Request::fromGlobals() → Router::dispatch()
  → AuthController::login($request) → UserRepository::findByEmail() → User::verifierMotDePasse()
  → $request->setSession(...) → Response::redirect('/') → remonte au Router → à index.php → send()
```

## Tester un contrôleur sans serveur web

Le script `test.php` de l'étape 9 fonctionne toujours, avec deux différences : plus de `require` pour
`render.php`, et une `View` à donner au contrôleur.

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

## Le sens des dépendances

```
index.php → Router → Controller → Model → PDO
                         └──────→ View → Response
```

Le modèle ne connaît ni la vue ni la requête. La vue ne connaît que ses données. Le contrôleur connaît tout
le monde mais ne fait rien lui-même. Toute flèche dans l'autre sens est une erreur d'architecture.

## Pièges

- **Exécuter le layout avant la vue.** Le layout a besoin de `$contenu`, qui n'existe qu'après l'exécution de
  la vue. L'ordre est imposé.
- **Un layout qui lit `$_SESSION`** pour la navigation. L'utilisateur connecté est passé en donnée partagée
  au constructeur de `View`, précisément pour éviter cela.
- **Un contrôleur qui construit sa `View` lui-même.** Il fixerait le dossier des vues : impossible de lui en
  donner un autre pour un test. Elle s'injecte comme le repository.

## Ce qui reste imparfait

Les vues sont du PHP : sept `htmlspecialchars()` répartis dans cinq fichiers, et rien n'empêche d'en oublier
un. Le bloc `<p><label>…<input …></p>` est recopié cinq fois entre `login.php` et `register.php`. Et le layout
reçoit `$contenu` sans que rien ne dise s'il est déjà du HTML ou du texte à échapper. Étape 11.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000 -t public
./verifier.sh 10               # depuis la racine du dépôt
```

**Questions du plan** : `PLAN.md`, étape 10.
