# Le MVC expliqué en diagrammes

Ce document décrit l'architecture du projet à l'étape 10 (dossier `etapes/10-noyau/`, construite aux étapes 9
et 10), juste avant les composants de vue de l'étape 11, et suit une requête réelle de bout en bout. Les
« temps » numérotés de la section 4 sont les phases d'une requête, pas les étapes du parcours.
Les diagrammes sont en Mermaid (rendus par GitHub et PhpStorm) avec une version texte en dessous.

---

## 1. Avant : une URL, un fichier

En PHP classique, le serveur web fait correspondre directement l'URL à un fichier. Chaque fichier fait tout.

```mermaid
flowchart LR
    B[Navigateur] -->|GET /login.php| S[Serveur web]
    S --> L[login.php<br/>session + PDO + SQL<br/>+ validation + HTML]
    S -.->|GET /register.php| R[register.php<br/>session + PDO + SQL<br/>+ validation + HTML]
    S -.->|GET /index.php| I[index.php<br/>session + HTML]
    L -->|HTML| B
```

```
Navigateur ──GET /login.php──► Serveur web ──► login.php ──► HTML
                                              (session, PDO, SQL, validation, HTML : tout dedans)
```

Conséquences : code dupliqué, HTML et logique mélangés, tous les fichiers du projet sont accessibles par URL.

---

## 2. Les trois rôles du MVC

```mermaid
flowchart TB
    U([Utilisateur]) -->|1. requête HTTP| C
    C[Contrôleur<br/>reçoit la requête, décide quoi faire] -->|2. demande des données| M
    M[Modèle<br/>règles métier + accès aux données] -->|3. renvoie des objets| C
    C -->|4. transmet les données| V
    V[Vue<br/>transforme les données en HTML] -->|5. HTML| C
    C -->|6. réponse HTTP| U
    M <-->|SQL| DB[(Base de données)]
```

```
              1. requête               2. demande
Utilisateur ───────────► Contrôleur ───────────► Modèle ◄──SQL──► Base
     ▲                       │   ▲                  │
     │                       │   └──── 3. objets ───┘
     │                       │ 4. données
     │                       ▼
     └──── 6. réponse ─── Vue (HTML) ── 5. HTML ──► Contrôleur
```

| Rôle | Responsabilité | Ne doit jamais | Dans ce projet |
|---|---|---|---|
| **Modèle** | Représenter les données et les règles métier, dialoguer avec la base | Produire du HTML, lire `$_POST` | `App\Model\User`, `App\Model\UserRepository` |
| **Vue** | Transformer des données en HTML | Faire du SQL, lire `$_POST` ou `$_SESSION`, décider | `views/*.php`, `App\Core\View` (et, dès l'étape 11, les composants `App\View\*`) |
| **Contrôleur** | Lire la requête, appeler le modèle, choisir la vue, construire la réponse | Contenir du SQL ou du HTML | `App\Controller\AuthController`, `HomeController` |

Ce que le schéma classique ne montre pas : quelqu'un doit recevoir la requête et choisir le contrôleur. C'est le rôle du **front controller** et du **routeur**.

---

## 3. L'architecture complète du projet

```mermaid
flowchart TB
    B([Navigateur]) -->|toute URL| WS[Serveur web<br/>php -S -t public]
    WS -->|réécriture| FC[public/index.php<br/>front controller]
    FC --> AL[autoload.php<br/>spl_autoload_register]
    FC --> RQ[Core\Request<br/>enveloppe $_GET $_POST $_SESSION $_SERVER]
    FC --> RT[Core\Router<br/>chemin + méthode → contrôleur]
    RT -->|instancie et appelle| CT[Controller\AuthController<br/>Controller\HomeController]
    CT -->|findByEmail, create| MD[Model\UserRepository]
    MD -->|hydrate| US[Model\User]
    MD <-->|PDO| DB[(database.sqlite)]
    CT -->|render vue + données| VW[Core\View<br/>layout + views/*.php]
    VW -->|HTML| RS[Core\Response<br/>statut, en-têtes, corps]
    CT -->|redirect| RS
    RS -->|send| B
```

```
Navigateur
   │  n'importe quelle URL
   ▼
Serveur web (php -S -t public) ── réécrit tout vers ──► public/index.php   ◄── FRONT CONTROLLER
                                                            │
                          ┌─────────────────────────────────┼─────────────────────┐
                          ▼                                 ▼                     ▼
                     autoload.php                     Core\Request           Core\Router
                  (charge les classes                (enveloppe les          (table des routes)
                   à la demande)                      superglobales)               │
                                                                                   │ dispatch
                                                                                   ▼
                                                               Controller\AuthController
                                                                  │                │
                                        Model\UserRepository ◄────┘                └────► Core\View
                                              │  hydrate                                    │ layout + vue
                                              ▼                                             ▼
                                         Model\User                                    Core\Response ──► Navigateur
                                              ▲
                                              │ PDO
                                        database.sqlite
```

Arborescence correspondante :

```
etapes/10-noyau/
├── public/
│   ├── index.php            ← seul fichier accessible par le web
│   └── .htaccess            ← pour Apache ; php -S se replie sur index.php tout seul
├── autoload.php
├── .env                     ← non versionné : DB_DSN=sqlite:database.sqlite
├── .env.example             ← versionné : mêmes clés, valeurs d'exemple
├── config/
│   └── routes.php           ← table des routes
├── src/
│   ├── Core/
│   │   ├── Router.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── View.php
│   │   ├── Database.php
│   │   └── Env.php
│   ├── Controller/
│   │   ├── AuthController.php
│   │   └── HomeController.php
│   └── Model/
│       ├── User.php
│       └── UserRepository.php
├── views/
│   ├── layout.php
│   ├── home.php
│   ├── login.php
│   ├── register.php
│   └── 404.php
└── database.sqlite          ← hors de public/, donc inaccessible par URL
```

---

## 4. Exemple complet : `POST /login`

L'utilisateur a saisi `test@example.com` et `motdepasse1` dans le formulaire, puis cliqué sur « Se connecter ».

### 4.1 Diagramme de séquence

```mermaid
sequenceDiagram
    autonumber
    actor B as Navigateur
    participant FC as public/index.php
    participant RQ as Core\Request
    participant RT as Core\Router
    participant AC as AuthController
    participant UR as UserRepository
    participant DB as SQLite
    participant RS as Core\Response

    B->>FC: POST /login (email, password)
    FC->>FC: require autoload.php, Env::charger(), session_start()
    FC->>RQ: Request::fromGlobals()
    RQ-->>FC: Request(method=POST, path=/login, post={...})
    FC->>RT: dispatch(request)
    RT->>RT: cherche ["POST", "/login"] dans la table
    RT->>AC: new AuthController(repository, view)
    RT->>AC: login(request)
    AC->>RQ: request->post("email"), request->post("password")
    AC->>UR: findByEmail("test@example.com")
    UR->>DB: SELECT id, email, password FROM users WHERE email = ?
    DB-->>UR: ligne {id: 1, email, password: "$2y$..."}
    UR-->>AC: User(id=1, email=..., passwordHash=...)
    AC->>AC: user->verifierMotDePasse("motdepasse1") → true
    AC->>RQ: setSession("utilisateur", {id: 1, email})
    AC->>RS: Response::redirect("/")
    RS-->>AC: Response(302, Location: /)
    AC-->>RT: Response
    RT-->>FC: Response
    FC->>RS: response->send()
    RS-->>B: HTTP 302, Location: /
    B->>FC: GET /  (nouvelle requête, voir 4.3)
```

### 4.2 La même requête, fichier par fichier

**Temps 1 : le serveur web reçoit la requête**

```
POST /login HTTP/1.1
Host: localhost:8000
Cookie: PHPSESSID=abc123
Content-Type: application/x-www-form-urlencoded

email=test%40example.com&password=motdepasse1
```

`/login` ne correspond à aucun fichier dans `public/`. Le serveur envoie donc la requête à `public/index.php` :
`php -S -t public` le fait tout seul pour tout chemin sans fichier, Apache grâce au `.htaccess`.

**Temps 2 : `public/index.php`, le front controller**

```php
<?php
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

require $racine . '/autoload.php';            // 1. les classes se chargeront toutes seules

Env::charger($racine . '/.env');              // 2. configuration hors du code
session_start();                              // 3. une seule fois, pour tout le site

$repository = new UserRepository(Database::connexion($racine));    // 4. dépendances partagées
$view       = new View($racine . '/views', ['utilisateurConnecte' => $_SESSION['utilisateur'] ?? null]);

$router = new Router(
    require $racine . '/config/routes.php',
    [   // qui sait construire quel contrôleur
        HomeController::class => fn() => new HomeController($view),
        AuthController::class => fn() => new AuthController($repository, $view),
    ],
    $view,
);

$request  = Request::fromGlobals();           // 5. la requête devient un objet
$response = $router->dispatch($request);      // 6. le routeur trouve et appelle le contrôleur

$response->send();                            // 7. un seul endroit qui écrit la sortie
```

Dans le fichier réel, les points 5 à 7 tiennent en une ligne : `$router->dispatch(Request::fromGlobals())->send();`.
Ils sont séparés ici pour être commentés.

**Temps 3 : `autoload.php`**

Aucune classe n'est chargée pour l'instant. À la première classe rencontrée (`Env::charger()`, puis `Database`,
`UserRepository`, `View`, `Router`, `Request`…), PHP appelle la fonction enregistrée, qui transforme le nom en chemin :

```
App\Core\Request  →  retire "App\"  →  Core\Request  →  remplace \ par /  →  src/Core/Request.php
```

```php
<?php
spl_autoload_register(function (string $classe): void {
    $prefixe = 'App\\';
    if (!str_starts_with($classe, $prefixe)) {
        return;
    }
    $fichier = __DIR__ . '/src/' . str_replace('\\', '/', substr($classe, strlen($prefixe))) . '.php';
    if (is_file($fichier)) {
        require $fichier;
    }
});
```

Chaque classe est chargée une seule fois, au moment de sa première utilisation. Le fichier réel est le même,
avec une variable intermédiaire et des commentaires.

**Temps 4 : `Core\Request` enveloppe les superglobales**

```php
final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $get,
        private readonly array $post,
        private array $session,
    ) {}

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', rtrim($path, '/') ?: '/', $_GET, $_POST, $_SESSION ?? []);
    }

    public function post(string $cle, ?string $defaut = null): ?string
    {
        return $this->post[$cle] ?? $defaut;
    }

    public function setSession(string $cle, mixed $valeur): void
    {
        $this->session[$cle] = $valeur;
        $_SESSION[$cle] = $valeur;          // la seule écriture de $_SESSION du projet
    }
}
```

État de l'objet : `method = "POST"`, `path = "/login"`, `post = ["email" => "test@example.com", "password" => "motdepasse1"]`,
`session = []` (pas encore connecté).

**Temps 5 : `Core\Router` trouve le contrôleur**

Table des routes (`config/routes.php`) :

```php
return [
    ['GET',  '/',         [HomeController::class, 'index']],
    ['GET',  '/login',    [AuthController::class, 'loginForm']],
    ['POST', '/login',    [AuthController::class, 'login']],      // ← celle-ci
    ['GET',  '/register', [AuthController::class, 'registerForm']],
    ['POST', '/register', [AuthController::class, 'register']],
    ['GET',  '/logout',   [AuthController::class, 'logout']],
];
```

```php
public function dispatch(Request $request): Response
{
    foreach ($this->routes as [$methode, $chemin, [$classe, $action]]) {
        if ($methode === $request->method && $chemin === $request->path) {
            $controleur = ($this->fabriques[$classe])();    // autoload.php charge AuthController ici
            return $controleur->$action($request);
        }
    }
    return $this->view->render('404', ['chemin' => $request->path], 404);
}
```

Le routeur compare `POST` et `/login` ligne par ligne, s'arrête à la troisième, instancie `AuthController`
et appelle sa méthode `login()`.

**Temps 6 : `Controller\AuthController::login()` orchestre**

```php
public function login(Request $request): Response
{
    $email    = trim($request->post('email', ''));
    $password = $request->post('password', '');

    $utilisateur = $this->repository->findByEmail($email);       // → Modèle

    if ($utilisateur !== null && $utilisateur->verifierMotDePasse($password)) {
        $request->setSession('utilisateur', ['id' => $utilisateur->id, 'email' => $utilisateur->email]);

        return Response::redirect('/');                          // → Réponse 302
    }

    return $this->view->render('login', [                        // → Vue, statut 200
        'titre'  => 'Connexion',
        'erreur' => 'Email ou mot de passe incorrect.',
        'email'  => $email,
    ]);
}
```

Le contrôleur ne contient ni SQL ni HTML. Il lit, délègue, décide, répond.

**Temps 7 : `Model\UserRepository::findByEmail()` interroge la base**

```php
public function findByEmail(string $email): ?User
{
    $stmt = $this->pdo->prepare('SELECT id, email, password FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

    return $ligne ? $this->hydrater($ligne) : null;   // → new User(id, email, passwordHash)
}
```

SQLite renvoie une ligne, le repository la transforme en objet `User`. Le contrôleur ne sait pas
qu'il s'agit de SQLite, ni même de SQL.

**Temps 8 : `Core\Response` termine**

```php
public static function redirect(string $url): self
{
    return new self(302, '', ['Location' => $url]);
}

public function send(): void
{
    http_response_code($this->statut);
    foreach ($this->entetes as $nom => $valeur) {
        header("$nom: $valeur");
    }
    echo $this->corps;
}
```

La réponse remonte jusqu'à `public/index.php`, qui appelle `send()`. C'est le seul endroit du projet où
`header()` et `echo` sont exécutés pour la sortie finale.

```
HTTP/1.1 302 Found
Location: /
```

### 4.3 Suite : le navigateur suit la redirection avec `GET /`

```mermaid
sequenceDiagram
    autonumber
    actor B as Navigateur
    participant FC as public/index.php
    participant RT as Core\Router
    participant HC as HomeController
    participant V as Core\View
    participant RS as Core\Response

    B->>FC: GET /
    FC->>RT: dispatch(request)
    RT->>HC: index(request)
    HC->>V: render("home", {titre})
    V->>V: ajoute les données partagées (utilisateurConnecte, lu dans index.php)
    V->>V: extract + require views/home.php → $contenu
    V->>V: require views/layout.php avec $contenu
    V-->>HC: Response(200, HTML)
    HC-->>RT: Response
    RT-->>FC: Response
    FC->>RS: send()
    RS-->>B: HTTP 200 + page « Bonjour test@example.com »
```

Le point intéressant est `Core\View` : elle exécute d'abord la vue dans un tampon de sortie, puis insère
le résultat dans le layout commun.

```php
public function render(string $vue, array $donnees = [], int $statut = 200): Response
{
    $donnees += $this->partage;                                   // ex. utilisateurConnecte
    $contenu = $this->capturer("$vue.php", $donnees);
    $html    = $this->capturer('layout.php', $donnees + ['contenu' => $contenu]);
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

```
views/home.php ──(HTML partiel)──► $contenu ──► views/layout.php ──(page complète)──► Response
```

---

## 5. Le chemin d'erreur : `POST /register` avec un email déjà pris

Même mécanique, autre issue. Ce diagramme montre que le contrôleur choisit **une vue** ou **une redirection**
selon ce que lui dit le modèle.

```mermaid
flowchart TD
    A[POST /register] --> B[Router → AuthController::register]
    B --> C{Validation<br/>email, longueur, confirmation}
    C -->|erreurs| V1[View::render register + erreurs]
    C -->|ok| D[UserRepository::emailExists]
    D -->|true| V2[View::render register<br/>« Un compte existe déjà »]
    D -->|false| E[UserRepository::create<br/>password_hash]
    E --> V3[View::render register + succès]
    V1 --> R[Response 200]
    V2 --> R
    V3 --> R
```

---

## 6. Qui connaît qui ?

Le sens des flèches est la règle la plus importante du MVC : les dépendances vont toujours dans le même sens.

```mermaid
flowchart LR
    FC[public/index.php] --> Router
    FC --> Request
    Router --> Controller
    Controller --> Model
    Controller --> View
    Controller --> Response
    View --> Response
    Model --> PDO[(PDO)]
    Model -.-x View
    View -.-x Model
    Model -.-x Request
```

```
index.php ──► Router ──► Controller ──► Model ──► PDO
                            │  └──────► View ──► Response
                            └─────────────────► Response

Interdits :   Model ✗──► View      View ✗──► Model      Model ✗──► Request / $_POST
```

- Le **modèle** ne connaît ni la vue, ni la requête HTTP. On peut l'utiliser depuis un script en ligne de commande.
- La **vue** ne connaît que les données qu'on lui donne. On peut la remplacer par du JSON sans toucher au reste.
- Le **contrôleur** connaît tout le monde, mais ne fait rien lui-même : il coordonne.

---

## 7. Résumé en une phrase par composant

| Composant | En une phrase |
|---|---|
| Serveur web | Envoie toute URL vers `public/index.php`. |
| Front controller | Prépare les dépendances, construit la `Request`, délègue au routeur, envoie la `Response`. |
| Autoloader | Transforme un nom de classe en chemin de fichier et le charge à la première utilisation. |
| Request | Rend la requête HTTP lisible et testable, sans superglobale. |
| Router | Associe méthode HTTP et chemin à une méthode de contrôleur. |
| Contrôleur | Lit la requête, appelle le modèle, choisit vue ou redirection. |
| Modèle | Porte les règles métier et parle à la base ; ignore le web. |
| Vue | Transforme des données en HTML ; ignore d'où elles viennent. |
| Response | Porte statut, en-têtes et corps ; envoyée une seule fois, à la fin. |

---

## 8. Variante de l'étape 11 : la vue assemble des composants

À l'étape 11, `views/layout.php` disparaît. Les vues ne contiennent plus de HTML : elles assemblent des objets
de `App\View\`, tous héritiers de la classe abstraite `Component` (`render(): string`, `__toString()`, `e()`
pour échapper, `renderAll()` pour les enfants). La règle est portée par les types : un enfant `string` est du
texte, échappé ; un enfant `Component` est du HTML, inséré tel quel. `Component::e()` est le seul
`htmlspecialchars()` du projet.

```php
// views/home.php
echo new Layout($titre, $utilisateurConnecte, [
    $utilisateurConnecte === null
        ? new Paragraph(['Vous n\'êtes pas connecté. ', new Link('/login', 'Connectez-vous'), ' ou ', new Link('/register', 'créez un compte'), '.'])
        : new Paragraph(['Bonjour ', new Strong($utilisateurConnecte['email']), ', vous êtes connecté.']),
]);
```

`View::render()` ne fait plus que capturer la vue et l'emballer dans une `Response` ; `Layout` produit le
document complet, navigation comprise. Dans la séquence 4.3, les deux lignes « `require views/home.php` » et
« `require views/layout.php` » deviennent une seule : `require views/home.php`, qui affiche un arbre de
composants. Les contrôleurs ne changent pas.

```
views/home.php ──(echo new Layout(...))──► HTML complet ──► Response
```

---

## 9. Variante de l'étape 12 : le routeur ne connaît plus que le contrat

À l'étape 12, la table `config/routes.php` disparaît et `AuthController` est découpé en un contrôleur par
couple verbe/chemin (`LoginFormController`, `LoginController`, `RegisterFormController`, `RegisterController`,
`LogoutController`, plus `HomeController`). Tous respectent `App\Core\ControllerInterface` :

```php
interface ControllerInterface
{
    public static function support(Request $request): bool;   // posée à la classe, sans objet
    public function handle(Request $request): Response;
}
```

Les six héritent d'`App\Core\AbstractController`, qui écrit `support()` une fois à partir de deux méthodes
abstraites statiques `verb()` et `path()` (`static::`, liaison statique tardive). Les quatre contrôleurs de
formulaire partagent en plus `App\Controller\FormTrait` (`lireEmail()`, `rendreFormulaire()`).
Le routeur, lui, ne connaît que l'interface.

Le routeur reçoit `[classe => fabrique]`, vérifie au câblage que chaque classe implémente l'interface
(`is_a($classe, ControllerInterface::class, true)`), puis :

```php
foreach ($this->fabriques as $classe => $fabrique) {
    if ($classe::support($request)) {
        return $fabrique()->handle($request);
    }
}
```

Le `POST /login` de la section 4 suit alors ce chemin : `HomeController::support()` et
`LoginFormController::support()` répondent non sans qu'aucun objet soit construit, `LoginController::support()`
répond oui, sa fabrique construit le seul contrôleur de la requête, `handle()` fait ce que faisait
`AuthController::login()`. Le reste (modèle, composants de vue de l'étape 11, `Response`, `send()`) est
identique. Seule la ligne « Router »
du tableau de la section 7 change : *Demande à chaque classe de contrôleur si elle prend en charge la requête ;
instancie la première qui dit oui et lui délègue.*

---

## 10. Variante de l'étape 13 : le routeur se sert lui-même

À l'étape 13, `public/index.php` ne nomme plus aucun contrôleur. Deux classes du noyau s'en chargent :

- `App\Core\ControllerFinder::trouver($dossier, $namespace)` balaye `src/Controller/*.php`, déduit un nom de
  classe de chaque fichier (la convention d'`autoload.php` lue à l'envers) et garde celles qui implémentent
  `ControllerInterface` et sont instanciables. `FormTrait` est ignoré.
- `App\Core\Container::creer($classe)` construit un objet en lisant, par réflexion, les types des paramètres
  de son constructeur, et en les demandant récursivement au conteneur. `View` et `UserRepository` sont
  enregistrés comme services partagés parce qu'ils ont besoin de valeurs qu'on ne peut pas deviner.

```php
$container = new Container([View::class => $view, UserRepository::class => $repository]);
$router = new Router(ControllerFinder::trouver($racine . '/src/Controller', 'App\\Controller'), $container, $view);
```

Le `POST /login` de la section 4 : le finder renvoie les six classes dans l'ordre alphabétique des fichiers,
`LoginController::support()` répond oui en deuxième position, le conteneur lit son constructeur
(`UserRepository`, `View`), fournit les deux services et appelle `new LoginController(...)`. `handle()` fait le
reste, inchangé depuis l'étape 12. La ligne « Router » du tableau de la section 7 devient : *Demande à chaque
classe trouvée si elle prend en charge la requête ; fait construire la première qui dit oui et lui délègue.*

---

## 11. Variante de l'étape 14 : le résultat de la découverte est mis en cache

À l'étape 14, `App\Core\ControllerCache::charger()` renvoie `[classe => types du constructeur]` : relu par
`require cache/controleurs.php` quand il est frais (date du cache strictement > date du dossier `src/Controller/`
et > date de chacun de ses fichiers, `filemtime()` étant à la seconde près), sinon reconstruit par
`ControllerFinder::trouver()` + `Container::analyser()` et
écrit avec `var_export()`. Le `Container` reçoit ces plans et ne fait de réflexion que pour une classe qui
n'y figure pas.

```php
$plans = (new ControllerCache($racine . '/src/Controller', 'App\\Controller', $racine . '/cache/controleurs.php'))->charger();
$container = new Container([View::class => $view, UserRepository::class => $repository], $plans);
$router = new Router(array_keys($plans), $container, $view);
```

Le `POST /login` de la section 4, cache chaud : neuf `stat` (le cache, le dossier, sept fichiers), un `require`, la boucle `support()`, puis
`new LoginController($repository, $view)` sans réflexion. Le routeur et les contrôleurs sont ceux des
étapes 12 et 13, inchangés. Ce que le cache ne supprime pas : chaque contrôleur est toujours chargé par
l'autoloader pour répondre à `support()`.
