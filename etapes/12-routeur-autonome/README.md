# Étape 12 : un routeur autonome

> **Objectif** : ne plus jamais écrire le nom d'un contrôleur dans `public/index.php`. Déposer une classe dans
> `src/Controller/` doit suffire pour qu'elle réponde à sa route.

## Le problème au départ

À l'étape 11, `public/index.php` contient six lignes de la forme
`LoginController::class => fn() => new LoginController($repository, $view)`. Chacune répète deux fois le nom
de la classe et recopie ce que son constructeur dit déjà : « j'ai besoin d'un `UserRepository` et d'une
`View` ». Oublier la ligne, c'est une classe correcte qui ne répond jamais, sans erreur.

Deux savoirs sont encore dans le front controller alors qu'ils sont déjà écrits ailleurs :

- **quelles classes existent** : les fichiers de `src/Controller/` le savent ;
- **comment les construire** : leur constructeur le sait.

## Ce qui change

```
12-routeur-autonome/
├── public/index.php              ne nomme plus AUCUN contrôleur
├── src/
│   ├── Core/
│   │   ├── ControllerFinder.php  NOUVEAU : liste les classes de src/Controller/ qui respectent le contrat
│   │   ├── Container.php         NOUVEAU : construit un objet en lisant les types de son constructeur
│   │   ├── Router.php            reçoit des noms de classes + le conteneur, n'écrit plus `new`
│   │   └── ... (ControllerInterface, AbstractController, Request, Response, View, Template inchangés)
│   └── Controller/               INCHANGÉ : six contrôleurs + FormTrait
└── views/, autoload.php          inchangés
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
Le finder fait le chemin inverse : un nom de fichier devient un nom de classe. Puis `is_a(..., true)` déclenche
l'autoload et vérifie le contrat ; `isInstantiable()` écarte ce qui n'est pas un vrai contrôleur.
`FormTrait.php` est dans le dossier : un trait n'implémente rien, il est ignoré sans bruit.

`glob()` renvoie les fichiers triés : l'ordre des contrôleurs, qui compte en cas de conflit (étape 11),
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

```php
$container = new Container([View::class => $view, UserRepository::class => new UserRepository(new PDO('sqlite::memory:'))]);

$controleur = $container->creer(LoginController::class);   // construit avec ses deux dépendances
$logout = $container->creer(LogoutController::class);      // sans constructeur : new LogoutController()

final class AvecTexte { public function __construct(string $nom) {} }
$container->creer(AvecTexte::class);   // LogicException : impossible de deviner $nom
```

## Ce qui a été gagné, ce qui a été perdu

| Gagné | Perdu |
|---|---|
| Une fonctionnalité = un fichier, zéro câblage | Le dossier `src/Controller/` **est** la configuration : un fichier de test oublié là devient une route |
| Le constructeur est la seule vérité sur les dépendances | La réflexion : du code qui lit du code, plus difficile à suivre dans un débogueur |
| Une dépendance manquante est une erreur explicite, à la construction | Le dossier est balayé à chaque requête ; un vrai framework le fait une fois et met le résultat en cache |
| L'ordre des contrôleurs ne dépend plus d'une liste écrite à la main | ... mais de l'ordre alphabétique des fichiers, qu'on ne choisit plus |

C'est ce que Symfony appelle *autowiring* et *service discovery*, Laravel *automatic injection*. Le même
mécanisme (lire les types du constructeur) construit chez eux les contrôleurs, les services, les commandes.
La différence : ils compilent le résultat du balayage et de la réflexion en PHP mis en cache, comme les
templates de l'étape 10, pour ne payer la découverte qu'une fois.

## Pièges

- **Un paramètre scalaire dans un constructeur de contrôleur.** `string $cheminCache` : le conteneur ne peut
  pas le deviner. Soit l'objet qui a besoin de ce scalaire est construit à la main et enregistré comme service
  (c'est le cas de `View`), soit le contrôleur reçoit un objet qui porte cette valeur.
- **Une classe dans `src/Controller/` qui n'est pas un contrôleur.** Si elle implémente l'interface par
  accident, elle répond. Si elle a un constructeur qui échoue, rien ne se passe tant que `support()` dit non.
- **Deux fichiers pour la même route.** `LoginController.php` et `Login2Controller.php` : c'est l'ordre
  alphabétique qui tranche, silencieusement.
- **Une dépendance circulaire.** `A` a besoin de `B`, `B` a besoin de `A` : `creer()` s'appelle sans fin.
  Le conteneur ne s'en protège pas ; les vrais conteneurs détectent le cycle et lèvent une exception.

## Ce qui reste imparfait

Le dossier est balayé, chaque classe chargée et vérifiée, et le constructeur de l'élue lu par réflexion, à
**chaque** requête, pour un résultat qui ne change que quand un fichier change. L'étape 10 a déjà résolu ce
problème pour les templates. Étape 13.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000 -t public
./verifier.sh 12
```

**Questions du plan** : `PLAN.md`, étape 12.
