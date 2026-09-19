# Étape 8 : les contrôleurs

> **Objectif** : remplacer les scripts d'action par des classes dont chaque méthode traite une requête précise,
> et leur fournir ce dont elles ont besoin plutôt que les laisser se servir.

## Le problème au départ

Les fichiers de `actions/` ont trois défauts :

1. Ils distinguent GET et POST à la main (`if ($_SERVER['REQUEST_METHOD'] === 'POST')`), ce qui mélange dans un
   même fichier « afficher le formulaire » et « traiter le formulaire ».
2. Ils utilisent `$pdo` sans le déclarer : la variable tombe du ciel via le `require` du front controller. En
   lisant `actions/login.php` seul, rien ne dit d'où elle vient.
3. Ils n'ont pas de nom. On ne peut pas les appeler depuis ailleurs, ni les tester.

## Ce qui change

```
08-controleurs/
├── public/index.php              construit les dépendances, dispatche
├── config/routes.php             [méthode HTTP, chemin, [classe, méthode]]
├── src/
│   ├── Controller/               NOUVEAU
│   │   ├── AuthController.php    loginForm, login, registerForm, register, logout
│   │   └── HomeController.php    index
│   └── Model/
├── autoload.php                  INCHANGÉ : il charge App\Controller\* sans modification
├── includes/  (db.php, env.php, render.php)
└── views/
```

Le dossier `actions/` disparaît.

## Comment ça marche

### 1. Une méthode par couple (méthode HTTP, chemin)

```php
return [
    ['GET',  '/',         [HomeController::class, 'index']],
    ['GET',  '/login',    [AuthController::class, 'loginForm']],
    ['POST', '/login',    [AuthController::class, 'login']],
    ['GET',  '/register', [AuthController::class, 'registerForm']],
    ['POST', '/register', [AuthController::class, 'register']],
    ['GET',  '/logout',   [AuthController::class, 'logout']],
];
```

`HomeController::class` est le nom complet de la classe sous forme de chaîne, `'App\Controller\HomeController'`,
résolu d'après les `use` en tête du fichier : `config/routes.php` commence donc par `use App\Controller\AuthController;`
et `use App\Controller\HomeController;`. Sans eux, `AuthController::class` vaut `'AuthController'` sans aucune
erreur, et le front controller échoue plus loin sur une clé inconnue.

`GET /login` et `POST /login` sont deux routes, deux méthodes. Le `if REQUEST_METHOD` a disparu des
contrôleurs : c'est le routeur qui distingue. `AuthController::loginForm()` fait quatre lignes,
`AuthController::login()` ne s'occupe que du traitement.

### 2. L'injection par le constructeur

```php
final class AuthController
{
    public function __construct(private readonly UserRepository $repository) {}

    public function login(): void
    {
        $utilisateur = $this->repository->findByEmail($email);
        // ...
    }
}
```

Le contrôleur **reçoit** son repository. Il ne sait pas comment on l'a construit, ni qu'il y a un `$pdo`
derrière. Trois manières de faire étaient possibles :

| Option | Problème |
|---|---|
| `new UserRepository($pdo)` dans chaque méthode | Duplication, et `$pdo` doit être accessible partout |
| `new UserRepository(...)` dans le constructeur | Le contrôleur décide de son repository : impossible de lui en donner un autre |
| Recevoir le repository en paramètre du constructeur | Celui qui crée le contrôleur choisit. On peut passer un faux repository pour tester |

La troisième est l'injection de dépendances. Elle ne demande aucune bibliothèque : c'est juste un paramètre.

### 3. Qui construit les contrôleurs ?

Le front controller. C'est le seul endroit qui connaît tout le monde :

```php
$repository = new UserRepository($pdo);
$controleurs = [
    HomeController::class => fn() => new HomeController(),
    AuthController::class => fn() => new AuthController($repository),
];

$routes = require $racine . '/config/routes.php';
foreach ($routes as [$routeMethode, $routeChemin, [$classe, $action]]) {
    if ($routeMethode === $methode && $routeChemin === $chemin) {
        $controleur = $controleurs[$classe]();     // la closure construit le contrôleur
        $controleur->$action();                    // appel d'une méthode dont le nom est dans une variable
        exit;
    }
}

http_response_code(404);            // aucune route n'a correspondu
render('404', ['chemin' => $chemin]);
```

`fn() => new AuthController($repository)` est une fonction fléchée : une fonction sans nom, réduite à une
expression, qui capture toute seule les variables du dehors (`$repository`). Rien n'est construit tant qu'on
ne l'appelle pas avec `()`. Les closures évitent donc de construire tous les contrôleurs à chaque requête :
seul celui de la route trouvée est instancié.

`foreach ($routes as [$routeMethode, $routeChemin, [$classe, $action]])` déstructure chaque route à la volée :
les trois cases du tableau vont dans trois variables, et la troisième case, elle-même un tableau, dans deux
autres. `$controleur->$action()` appelle la méthode dont le nom est dans `$action` : c'est ce qui permet à
une table de données de piloter du code.

## Cheminement d'une requête

```
POST /login
  → public/index.php : autoload, includes, session, $repository
  → boucle sur les routes : ['POST', '/login'] trouvé → [AuthController, 'login']
  → new AuthController($repository)
  → ->login() : lit $_POST, appelle $repository->findByEmail(), vérifie, remplit la session
  → header('Location: /') ; exit
```

## Ce que ça change concrètement

- Ajouter une page : une ligne de route, une méthode. Le contrôleur existant a déjà ses dépendances.
- Deux méthodes d'un même contrôleur partagent du code par une méthode privée (`redirect()`), sans include.
- `AuthController` peut être instancié depuis un test avec un repository sur une base en mémoire.
- L'autoloader de l'étape 6 n'a pas été touché : `App\Controller\AuthController` → `src/Controller/AuthController.php`.

## Pièges

- **Un contrôleur qui fait `new UserRepository()` lui-même.** On perd l'injection et la testabilité.
- **Du SQL ou du HTML dans un contrôleur.** Il lit la requête, appelle le modèle, choisit la vue. Il ne fait
  rien lui-même. Si une méthode dépasse trente lignes, une partie appartient probablement au modèle.
- **Oublier une route** pour un formulaire : `POST /register` sans route donne un 404 déroutant. Un formulaire
  a presque toujours deux routes.

## Ce qui reste imparfait

Les contrôleurs lisent encore `$_POST` et `$_SESSION` directement, appellent `header()` et `exit`, et
dépendent de la fonction globale `render()`. Un contrôleur ne peut donc pas être exécuté hors d'une requête
HTTP réelle : `exit` arrêterait le test. Étape 9 pour la requête et la réponse, étape 10 pour `render()`.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000 -t public
./verifier.sh 08               # depuis la racine du dépôt
```

**Questions du plan** : `PLAN.md`, étape 8.
