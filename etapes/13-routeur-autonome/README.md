# Étape 13 : un routeur autonome

> **Objectif** : ne plus jamais écrire le nom d'un contrôleur dans `public/index.php`. Déposer une classe dans
> `src/Controller/` doit suffire pour qu'elle réponde à sa route.

> **Bonus** (voir l'étape 12) : étape facultative, qui prépare les unités d'enseignement suivantes. README
> ouvert pendant que vous codez.

## Le problème au départ

À l'étape 12, `public/index.php` contient six lignes de la forme
`LoginController::class => fn() => new LoginController($repository, $view)`. Chacune répète deux fois le nom
de la classe et recopie ce que son constructeur dit déjà : « j'ai besoin d'un `UserRepository` et d'une
`View` ». Oublier la ligne, c'est une classe correcte qui ne répond jamais, sans erreur.

Deux savoirs sont encore dans le front controller alors qu'ils sont déjà écrits ailleurs :

- **quelles classes existent** : les fichiers de `src/Controller/` le savent ;
- **comment les construire** : leur constructeur le sait.

## Ce qui change

```
13-routeur-autonome/
├── public/index.php              ne nomme plus AUCUN contrôleur
├── src/
│   ├── Core/
│   │   ├── ControllerFinder.php  NOUVEAU : liste les classes de src/Controller/ qui respectent le contrat
│   │   ├── Container.php         NOUVEAU : construit un objet en lisant les types de son constructeur
│   │   ├── Router.php            reçoit des noms de classes + le conteneur, n'écrit plus `new`
│   │   └── ... (ControllerInterface, AbstractController, Request, Response, View inchangés)
│   └── Controller/               INCHANGÉ : six contrôleurs + FormTrait
└── views/, src/View/, autoload.php   inchangés
```

Aucun contrôleur ne change. Tout se passe dans le noyau et dans le câblage.

## Comment ça marche

### `ControllerFinder` : la convention d'autoload lue à l'envers

```php
foreach (glob($dossier . '/*.php') as $fichier) {
    $classe = $namespace . '\\' . basename($fichier, '.php');     // LoginController.php → App\Controller\LoginController

    if (is_a($classe, ControllerInterface::class, true) && (new ReflectionClass($classe))->isInstantiable()) {
        $classes[] = $classe;
    }
}
```

`autoload.php` (étape 6) transforme `App\Controller\LoginController` en `src/Controller/LoginController.php`.
Le finder fait le chemin inverse : un nom de fichier devient un nom de classe. Puis `is_a(..., true)` accepte
ce nom en chaîne, ce qui charge la classe par l'autoloader, et vérifie le contrat ; `isInstantiable()` écarte
ce qui n'est pas un vrai contrôleur. `FormTrait.php` est dans le dossier : un trait n'implémente rien, il est
ignoré sans bruit.

`ReflectionClass`, comme `ReflectionNamedType` et `LogicException` plus bas, est une classe globale de PHP :
dans un fichier sous `namespace App\Core`, elle s'importe avec `use` en tête, sinon PHP la cherche dans
`App\Core` (règle vue à l'étape 12 avec `InvalidArgumentException`).

`glob()` renvoie les fichiers triés : l'ordre des contrôleurs, qui compte en cas de conflit (étape 12),
est maintenant l'ordre alphabétique des fichiers. Il est prévisible, mais il n'est plus choisi.

### `Container` : lire le constructeur au lieu de le recopier

```php
public function creer(string $classe): object
{
    if (isset($this->services[$classe])) {
        return $this->services[$classe];                              // View, UserRepository : déjà construits
    }

    $constructeur = (new ReflectionClass($classe))->getConstructor();
    $arguments = [];
    foreach ($constructeur?->getParameters() ?? [] as $parametre) {
        $type = $parametre->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            throw new LogicException("Impossible de deviner le paramètre \$" . $parametre->getName() . " de $classe");
        }
        $arguments[] = $this->creer($type->getName());              // récursif
    }

    return new $classe(...$arguments);
}
```

La **réflexion** (`ReflectionClass`, `ReflectionNamedType`) permet à du code PHP de lire du code PHP : ici,
la liste des paramètres d'un constructeur et leur type. Pour `LoginController`, elle lit
`UserRepository $repository, View $view`, demande ces deux classes au conteneur, et appelle
`new LoginController($repository, $view)`. Exactement la ligne qu'on écrivait à la main, mais déduite.

Trois écritures à connaître dans ce code :

- `$constructeur?->getParameters() ?? []` : si `$constructeur` vaut `null` (classe sans constructeur), `?->`
  renvoie `null` au lieu de planter, et `?? []` transforme ce `null` en tableau vide ;
- `getType()` renvoie un `ReflectionNamedType` pour `View $view` ou `string $nom`, `null` pour un paramètre
  sans type, et un `ReflectionUnionType` pour `A|B`. D'où le test : on ne garde que les types nommés, et
  parmi eux on refuse les types natifs (`isBuiltin()` : `string`, `int`, `array`...) ;
- `new $classe(...$arguments)` : `...` déplie le tableau en arguments séparés, comme si on les avait écrits
  un par un.

Le conteneur ne « devine » que des objets. `View` et `UserRepository` ont besoin de chemins et de valeurs
lues dans `.env` : ce sont les deux seuls objets qu'`index.php` construit encore lui-même et **enregistre**
comme services. Tout ce qui ne dépend que d'autres objets est construit à la demande, récursivement.
`LogoutController` n'a pas de constructeur : `getConstructor()` renvoie `null`, la boucle ne tourne pas.

Un service enregistré est **partagé** (toujours la même `View`) ; un contrôleur est construit à chaque
`creer()`. C'est la distinction singleton / instance neuve que tous les conteneurs font.

### `Router` : plus un seul `new`

```php
foreach ($this->controleurs as $classe) {
    if ($classe::support($request)) {
        return $this->container->creer($classe)->handle($request);
    }
}
```

Le routeur reçoit des noms de classes, pose la question statique, et délègue la construction. Il ne connaît
ni les fichiers, ni les constructeurs.

### `public/index.php` : le câblage tient en deux objets

```php
$container = new Container([
    View::class => $view,
    UserRepository::class => $repository,
]);

$router = new Router(
    ControllerFinder::trouver($racine . '/src/Controller', 'App\\Controller'),
    $container,
    $view,
);
```

Ajouter une page : créer `src/Controller/ProfilController.php`, qui étend `AbstractController`, avec
`verb()`, `path()`, `handle()` et un constructeur qui déclare ce dont il a besoin. Rien d'autre à toucher.
Aucun fichier n'est modifié : la page **existe** dès que le fichier existe.

## Cheminement d'une requête

```
POST /login → index.php
  → ControllerFinder::trouver()        → [Home, Login, LoginForm, Logout, Register, RegisterForm]Controller
  → Router::dispatch()
      → HomeController::support()      → false
      → LoginController::support()     → true
      → Container::creer(LoginController::class)
            → lit le constructeur : UserRepository, View
            → services enregistrés → new LoginController($repository, $view)
      → ->handle($request) → ... → Response::redirect('/')
  → send()
```

## Tester le conteneur seul

Dans un fichier `test.php` à la racine de l'étape, lancé avec `php test.php` :

```php
require __DIR__ . '/autoload.php';

use App\Controller\{LoginController, LogoutController};
use App\Core\{Container, View};
use App\Model\UserRepository;

$view = new View(__DIR__ . '/views', ['utilisateurConnecte' => null]);
$container = new Container([View::class => $view, UserRepository::class => new UserRepository(new PDO('sqlite::memory:'))]);

$controleur = $container->creer(LoginController::class);   // construit avec ses deux dépendances
$logout = $container->creer(LogoutController::class);      // sans constructeur : new LogoutController()
echo get_class($controleur), ' ', get_class($logout), "\n";

// Les deux appels suivants DOIVENT échouer : on attrape l'exception pour lire son message et continuer.
final class AvecTexte { public function __construct(string $nom) {} }
try {
    $container->creer(AvecTexte::class);
} catch (LogicException $e) {
    echo $e->getMessage(), "\n";   // Impossible de deviner le paramètre $nom de AvecTexte : enregistrez-le comme service.
}

$sansRepository = new Container([View::class => $view]);
try {
    $sansRepository->creer(LoginController::class);
} catch (LogicException $e) {
    echo $e->getMessage(), "\n";   // Impossible de deviner le paramètre $dsn de PDO : enregistrez-le comme service.
}
```

Le dernier appel montre pourquoi `index.php` enregistre `UserRepository` : sans lui, le conteneur tente de le
construire, lit son constructeur (`PDO $pdo`), tente de construire `PDO`, et bute sur `string $dsn`. La
descente récursive s'arrête au premier scalaire, avec un message qui dit lequel.

## Ce qui a été gagné, ce qui a été perdu

| Gagné | Perdu |
|---|---|
| Une fonctionnalité = un fichier, zéro câblage | Le dossier `src/Controller/` **est** la configuration : un fichier de test oublié là devient une route |
| Le constructeur est la seule vérité sur les dépendances | La réflexion : du code qui lit du code, plus difficile à suivre dans un débogueur |
| Une dépendance manquante est une erreur explicite, à la construction | Le dossier est balayé à chaque requête ; un vrai framework le fait une fois et met le résultat en cache |
| L'ordre des contrôleurs ne dépend plus d'une liste écrite à la main | ... mais de l'ordre alphabétique des fichiers, qu'on ne choisit plus |

C'est ce que Symfony appelle *autowiring* et *service discovery*, Laravel *automatic injection*. Le même
mécanisme (lire les types du constructeur) construit chez eux les contrôleurs, les services, les commandes.
La différence : ils écrivent le résultat du balayage et de la réflexion dans un fichier PHP mis en cache,
pour ne payer la découverte qu'une fois.

## Pièges

- **Un paramètre scalaire dans un constructeur de contrôleur.** `string $cheminCache` : le conteneur ne peut
  pas le deviner. Soit l'objet qui a besoin de ce scalaire est construit à la main et enregistré comme service
  (c'est le cas de `View`), soit le contrôleur reçoit un objet qui porte cette valeur.
- **Une classe dans `src/Controller/` qui n'est pas un contrôleur.** Si elle implémente l'interface par
  accident, elle répond. Si elle a un constructeur qui échoue, rien ne se passe tant que `support()` dit non.
- **Un fichier dont le nom n'est pas celui de la classe.** `Profil.php` contenant `class ProfilController` :
  le finder cherche `App\Controller\Profil`, l'autoloader inclut le fichier, la classe `Profil` n'existe pas,
  `is_a()` répond non. Aucune erreur, et la page n'existe pas. La convention d'autoload est la seule chose qui
  relie le fichier à la classe : elle se respecte dans les deux sens.
- **Deux fichiers pour la même route.** `LoginController.php` et `Login2Controller.php` : c'est l'ordre
  alphabétique qui tranche, silencieusement.
- **Une dépendance circulaire.** `A` a besoin de `B`, `B` a besoin de `A` : `creer()` s'appelle sans fin.
  Le conteneur ne s'en protège pas ; les vrais conteneurs détectent le cycle et lèvent une exception.

## Ce qui reste imparfait

Le dossier est balayé, chaque classe chargée et vérifiée, et le constructeur de l'élue lu par réflexion, à
**chaque** requête, pour un résultat qui ne change que quand un fichier change. Un calcul dont l'entrée ne
bouge pas devrait être fait une fois, puis relu. Étape 14.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000 -t public
./verifier.sh 13               # depuis la racine du dépôt
```

**Questions du plan** : `PLAN.md`, étape 13.
