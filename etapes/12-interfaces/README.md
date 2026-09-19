# Étape 12 : une interface pour les contrôleurs

> **Objectif** : remplacer la table de routes statique par un **contrat** : chaque contrôleur déclare lui-même
> la requête qu'il prend en charge, et le routeur ne connaît plus que ce contrat.

> **Bonus.** Les étapes 12 à 14 ne sont pas nécessaires pour structurer le site de votre SAÉ : l'étape 11
> suffit. Elles préparent les unités d'enseignement suivantes, où ces mécanismes réapparaissent à l'intérieur
> d'un framework. Lisez ce README **pendant** que vous codez, pas après : ici, comprendre le mécanisme compte
> plus que le retrouver seul.

## Le problème au départ

À l'étape 11, ajouter une fonctionnalité demande de toucher **trois** endroits : écrire la méthode dans un
contrôleur, ajouter sa ligne dans `config/routes.php`, vérifier la fabrique dans `public/index.php`. La table
des routes sait tout des contrôleurs (classe, nom de méthode), mais rien ne garantit que la méthode existe :
une faute de frappe dans `'loginFrom'` n'est détectée qu'au moment où quelqu'un visite `/login`.

Et le routeur appelle `$controleur->$action($request)` sur un objet dont il ne sait rien. Il *espère* que
`$action` existe et renvoie une `Response`. PHP ne peut rien vérifier avant l'appel.

Enfin `AuthController` grossit : cinq actions, deux dépendances, et `logout()` n'a besoin ni du modèle ni
de la vue qu'on lui injecte quand même.

## Ce qui change

```
12-interfaces/
├── public/index.php              une fabrique par contrôleur, plus de table de routes
├── config/                       DISPARAÎT : plus de routes.php
├── src/
│   ├── Core/
│   │   ├── ControllerInterface.php   NOUVEAU : support() statique + handle()
│   │   ├── AbstractController.php    NOUVEAU : implémente support() à partir de verb() et path()
│   │   ├── Router.php                réécrit : interroge les classes, n'instancie que l'élue
│   │   └── ... (Request, Response, View, Env, Database inchangés)
│   └── Controller/                   UN CONTRÔLEUR PAR COUPLE verbe / chemin
│       ├── FormTrait.php                 NOUVEAU : lireEmail(), rendreFormulaire()
│       ├── HomeController.php            GET  /
│       ├── LoginFormController.php       GET  /login          use FormTrait
│       ├── LoginController.php           POST /login          use FormTrait
│       ├── RegisterFormController.php    GET  /register       use FormTrait
│       ├── RegisterController.php        POST /register       use FormTrait
│       └── LogoutController.php          GET  /logout
└── views/, src/View/, autoload.php   inchangés : les vues et les composants de l'étape 11 ne bougent pas
```

`AuthController` disparaît : chacune de ses méthodes devient une classe. Le corps de chaque `handle()` est
l'ancienne méthode, à deux lignes près : la lecture de l'email et le rendu du formulaire passent par
`FormTrait` (voir plus bas).

## Comment ça marche

### L'interface : deux méthodes, rien d'autre

```php
interface ControllerInterface
{
    public static function support(Request $request): bool;   // « sais-tu répondre à ça ? »
    public function handle(Request $request): Response;       // « alors réponds »
}
```

Une interface ne contient aucun code : uniquement des signatures. C'est une **promesse** qu'une classe fait
en écrivant `implements ControllerInterface`. Si elle oublie une méthode, ou change un type de retour, PHP
refuse de charger la classe. L'erreur arrive au chargement, pas à la première visite de l'URL.

`support()` est **statique** : on la pose à la classe, pas à un objet. Décider si `POST /login` concerne
`LoginController` ne demande ni le `UserRepository` ni la `View` : ce serait du gaspillage de les construire
pour rien. Une interface peut déclarer des méthodes statiques comme des méthodes d'instance.

### Le routeur : une boucle qui pose une question à des classes

```php
public function __construct(private readonly array $fabriques, private readonly View $view)
{
    foreach ($fabriques as $classe => $fabrique) {
        if (!is_a($classe, ControllerInterface::class, true)) {
            throw new InvalidArgumentException("$classe n'implémente pas ControllerInterface");
        }
    }
}

public function dispatch(Request $request): Response
{
    foreach ($this->fabriques as $classe => $fabrique) {
        if ($classe::support($request)) {          // question posée à la classe
            return $fabrique()->handle($request);   // un seul objet construit : celui-ci
        }
    }
    return $this->view->render('404', ['chemin' => $request->path], 404);
}
```

Le routeur ne compare plus de chemins. Il ne connaît ni `LoginController`, ni `/login`. Il parcourt des noms
de classes dont il sait une seule chose, vérifiée au câblage par `is_a(..., true)` : elles respectent
`ControllerInterface`. Le troisième paramètre `true` autorise à passer un **nom** de classe plutôt qu'un
objet ; pour répondre, PHP charge la classe via l'autoloader de l'étape 6. C'est donc au câblage que chaque
classe est chargée, sans être instanciée. Puis `$classe::support($request)` : PHP appelle la méthode statique
d'une classe dont le nom est dans une variable.

Détail qui coûte du temps : `InvalidArgumentException` est une classe globale de PHP. Dans un fichier sous
`namespace App\Core`, il faut `use InvalidArgumentException;` en tête, sinon PHP cherche
`App\Core\InvalidArgumentException` et ne la trouve pas. `Database` fait déjà la même chose avec `use PDO;`.

C'est ce qui rend le routeur **dynamique** : la décision « qui répond ? » n'est plus dans une table lue par
le routeur, elle est prise à l'exécution par les contrôleurs eux-mêmes. Un contrôleur peut répondre oui pour
une raison qui n'est pas un chemin exact : un préfixe `/admin/`, un en-tête `Accept: application/json`,
un identifiant numérique dans l'URL.

### `AbstractController` : `support()` écrit une seule fois

Six contrôleurs, six fois la même ligne `return $request->method === 'X' && $request->path === '/y'`.
Une classe abstraite la factorise : elle implémente l'interface, écrit `support()`, et laisse deux trous.

```php
abstract class AbstractController implements ControllerInterface
{
    abstract protected static function verb(): string;
    abstract protected static function path(): string;

    public static function support(Request $request): bool
    {
        return $request->method === static::verb() && $request->path === static::path();
    }
}
```

Trois détails comptent :

- `verb()` et `path()` sont **statiques**, comme `support()` qui les appelle : toujours aucun objet construit.
- `static::verb()` et non `self::verb()`. `self::` désignerait `AbstractController`, où la méthode est
  abstraite. `static::` désigne la classe sur laquelle on a appelé `support()`, donc `LoginController` : c'est
  la *liaison statique tardive* (late static binding).
- `handle()` n'est pas écrite ici : elle reste abstraite, héritée de l'interface. Une classe abstraite peut
  implémenter une partie d'une interface et laisser le reste à ses filles.

### Un contrôleur : une seule responsabilité

```php
// POST /login : vérifie les identifiants, ouvre la session.
final class LoginController extends AbstractController
{
    use FormTrait;

    public function __construct(private readonly UserRepository $repository, private readonly View $view) {}

    protected static function verb(): string { return 'POST'; }
    protected static function path(): string { return '/login'; }

    public function handle(Request $request): Response
    {
        $email = $this->lireEmail($request);
        /* ... l'ancien AuthController::login(), via lireEmail() et rendreFormulaire() ... */
        return $this->rendreFormulaire('login', 'Connexion', $email, ['erreur' => 'Email ou mot de passe incorrect.']);
    }
}
```

Chaque classe reçoit **uniquement** ce dont elle a besoin : `LogoutController` n'a pas de constructeur,
`LoginFormController` ne reçoit que la vue. À l'étape 11, `AuthController` recevait tout pour tout le monde.

### `FormTrait` : le code commun aux contrôleurs de formulaire

Les quatre contrôleurs de `/login` et `/register` affichent un formulaire avec un titre et un email
(vide au premier affichage, ressaisi après une erreur), et les deux en POST lisent cet email
(`trim($request->post('email', ''))`). Ils héritent déjà d'`AbstractController`, et ce code n'a rien à faire
dans le noyau : il parle d'email et de formulaire. D'où un **trait**.

```php
trait FormTrait
{
    private function lireEmail(Request $request): string
    {
        return trim($request->post('email', ''));
    }

    private function rendreFormulaire(string $vue, string $titre, string $email, array $donnees = []): Response
    {
        return $this->view->render($vue, ['titre' => $titre, 'email' => $email] + $donnees);
    }
}
```

Un trait n'est ni une classe ni une interface : c'est un morceau de code que PHP **copie** dans chaque classe
qui écrit `use FormTrait;`. Il n'apparaît pas dans la hiérarchie (`instanceof` ne le voit pas), il ne peut pas
être instancié, et il compte sur la classe hôte : `$this->view` doit exister. C'est l'outil pour partager du
code entre des classes qui n'ont pas de parent commun approprié, ou qui en ont déjà un. `HomeController` et
`LogoutController`, qui n'ont pas de formulaire, ne l'utilisent pas.

### `public/index.php` : une fabrique par contrôleur

```php
$router = new Router(
    [
        HomeController::class      => fn() => new HomeController($view),
        LoginFormController::class => fn() => new LoginFormController($view),
        LoginController::class     => fn() => new LoginController($repository, $view),
        // ...
        LogoutController::class    => fn() => new LogoutController(),
    ],
    $view,
);
```

Les fabriques (fermetures `fn() => new ...`) sont les mêmes qu'aux étapes 8 à 10 : le routeur sait *comment*
construire chaque contrôleur sans le faire tout de suite. Ajouter une fonctionnalité = une classe qui
implémente l'interface, plus une ligne ici. Deux endroits au lieu de trois, et le second est vérifié par PHP.

## Cheminement d'une requête

```
POST /login → index.php → Request::fromGlobals() → Router::dispatch()
  → HomeController::support()       → false      (classe chargée, aucun objet construit)
  → LoginFormController::support()  → false
  → LoginController::support()      → true
  → fabrique() → new LoginController($repository, $view)    (le SEUL objet construit)
  → ->handle($request) → UserRepository::findByEmail() → ... → Response::redirect('/')
  → remonte au Router → à index.php → send()
```

## Interface, classe abstraite, trait : trois outils, trois rôles

| | Contient | Sert à | Ici |
|---|---|---|---|
| **Interface** | des signatures, zéro code | promettre un **contrat** à qui ne veut rien savoir de vous | `ControllerInterface`, la seule chose que le routeur connaît |
| **Classe abstraite** | du code + des trous à remplir | partager une **implémentation** entre classes de même famille | `AbstractController`, un `support()` pour tous |
| **Trait** | du code copié dans la classe hôte | partager du code entre classes **sans lien de parenté** utile | `FormTrait`, quatre contrôleurs sur six |

Pourquoi le routeur dépend-il de l'interface et pas d'`AbstractController` ? Parce qu'un contrôleur pourrait
vouloir un `support()` différent (un préfixe `/admin/`, un en-tête) et ne pas hériter de l'abstraite. Le
routeur l'accepterait sans changer une ligne : il ne demande que le contrat. Une classe ne peut hériter que
d'**une** classe, mais implémenter **plusieurs** interfaces ; réserver la dépendance du noyau à l'interface
laisse cette liberté.

Pourquoi un trait et pas des méthodes dans `AbstractController` ? Parce que `rendreFormulaire()` ne concerne
que quatre contrôleurs sur six, et parle d'email et de formulaire : ce n'est pas du noyau. Le mettre dans l'abstraite l'imposerait
à `LogoutController`, qui n'a pas de formulaire.

## Tester le routeur sans aucun vrai contrôleur

C'est le bénéfice concret d'une interface : on peut fabriquer un faux contrôleur en cinq lignes. Dans un
fichier `test.php` à la racine de l'étape, lancé avec `php test.php` :

```php
require __DIR__ . '/autoload.php';

use App\Core\{AbstractController, Request, Response, Router, View};

// La vue 404 attend la donnée partagée par index.php : on la fournit, même vide.
$view = new View(__DIR__ . '/views', ['utilisateurConnecte' => null]);

final class FauxController extends AbstractController {
    protected static function verb(): string { return 'GET'; }
    protected static function path(): string { return '/test'; }
    public function handle(Request $r): Response { return new Response(200, 'ok'); }
}

$router = new Router([FauxController::class => fn() => new FauxController()], $view);
echo $router->dispatch(new Request('GET', '/test', [], [], []))->corps;   // ok
echo $router->dispatch(new Request('GET', '/autre', [], [], []))->statut; // 404
```

Une classe anonyme (`new class extends ...`) ne convient plus : `support()` étant statique, le routeur
a besoin d'un **nom** de classe, et une classe anonyme n'en a qu'une fois instanciée.

## Pièges

- **Un `support()` qui fait du travail.** Il doit répondre oui ou non, sans lire la base ni modifier la session.
  Il est appelé pour toutes les requêtes, y compris celles que le contrôleur ne traitera jamais, et il est
  statique : il n'a de toute façon accès à aucune dépendance.
- **Deux contrôleurs qui répondent oui à la même requête.** Le premier de la liste gagne, silencieusement.
  L'ordre dans `index.php` devient significatif : c'est le prix d'un routeur dynamique.
- **Un `handle()` appelé sans `support()`.** Le contrat dit « `handle()` n'est appelée que si `support()` a dit
  oui » ; il n'est écrit que dans un commentaire, PHP ne le vérifie pas.
- **`implements` oublié.** La classe a bien `support()` et `handle()`, mais le routeur la refuse quand même :
  `is_a()` vérifie le nom de l'interface, pas la présence des méthodes.
- **`self::verb()` dans `AbstractController`.** Erreur fatale : `self` désigne la classe abstraite, dont
  `verb()` n'a pas de corps. Il faut `static::`.
- **Un trait qui suppose une propriété.** `FormTrait` utilise `$this->view`. Une classe qui l'utilise sans
  cette propriété échoue à l'exécution, pas à la compilation : le trait est copié tel quel, PHP ne vérifie
  rien. Un trait peut déclarer des méthodes abstraites pour exiger ce dont il a besoin ; c'est plus sûr.
- **Une classe qui n'est pas dans la liste.** Elle implémente l'interface, elle est dans `src/Controller/`,
  et personne ne l'appelle jamais : le routeur ne connaît que ce que `index.php` lui donne.

## Ce qui a été gagné, ce qui a été perdu

| Gagné | Perdu |
|---|---|
| Le routeur ne dépend plus d'aucune classe concrète | Plus de vue d'ensemble : les routes sont éparpillées dans six fichiers |
| Une erreur de contrat est détectée au câblage, pas à la visite | L'ordre de la liste compte |
| Un seul contrôleur construit par requête, avec ses seules dépendances | Neuf fichiers (six contrôleurs, une interface, une abstraite, un trait) au lieu de deux |
| Un contrôleur peut décider sur autre chose qu'un chemin exact | Trois mécanismes de partage à connaître (interface, héritage, trait) et à ne pas confondre |
| `support()` écrit une fois ; le code de formulaire écrit une fois | Un trait dépend implicitement de la classe hôte (`$this->view`) |
| Un faux contrôleur pour tester le routeur tient en cinq lignes | |

Les frameworks connus font les deux à la fois : une table de routes (attributs `#[Route]` sur les méthodes,
collectés automatiquement) **et** des interfaces pour les composants qui traitent la requête
(`RequestHandlerInterface` de la PSR-15 a exactement la forme de `handle()`).
Le découpage « une classe par action » existe aussi : Symfony l'appelle *invokable controller*, ADR
(Action-Domain-Responder) en fait un principe.

## Ce qui reste imparfait

`public/index.php` nomme encore chaque contrôleur, deux fois par ligne, et recopie ce que son constructeur
déclare déjà. Une classe correcte mais absente de la liste ne répond jamais, sans erreur. Étape 13.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000 -t public
./verifier.sh 12               # depuis la racine du dépôt
```

**Questions du plan** : `PLAN.md`, étape 12.
