# Étape 9 : la requête et la réponse deviennent des objets

> **Objectif** : un contrôleur ne lit plus aucune superglobale et n'envoie plus rien lui-même. Il **reçoit**
> une `Request` et **retourne** une `Response`. Il devient une méthode ordinaire, appelable depuis un test.

## Le problème au départ

Les contrôleurs de l'étape 8 sont des classes, mais ils se comportent encore comme des scripts :

1. Ils lisent `$_POST` et `$_SESSION` directement. Pour tester `AuthController::login()`, il faut remplir
   une superglobale à la main avant l'appel.
2. Ils appellent `header('Location: ...')` puis `exit`. Un test qui appelle `login()` s'arrête net avec le
   script : impossible de vérifier quoi que ce soit après.
3. `render()` affiche le HTML immédiatement. Une fois qu'un octet est parti vers le navigateur, on ne peut
   plus envoyer d'en-tête : c'est l'erreur « headers already sent ». Personne ne décide, à un seul endroit,
   de ce qui est envoyé.

## Ce qui change

```
09-requete-reponse/
├── public/index.php              Request::fromGlobals(), boucle sur les routes, $response->send()
├── config/routes.php             inchangé
├── includes/
│   ├── render.php                MODIFIÉ : capture le HTML et retourne une Response
│   ├── db.php                    inchangé
│   └── env.php                   inchangé
├── src/
│   ├── Core/                     NOUVEAU
│   │   ├── Request.php           enveloppe $_GET, $_POST, $_SERVER, $_SESSION
│   │   └── Response.php          statut, en-têtes, corps ; send()
│   ├── Controller/               reçoivent une Request, RETOURNENT une Response
│   └── Model/
├── autoload.php                  inchangé : App\Core\Request → src/Core/Request.php
└── views/                        inchangées, partials compris
```

## Comment ça marche

### `Request` : la requête devient un objet

```php
$request = Request::fromGlobals();
$request->method;                 // "POST"
$request->path;                   // "/login"
$request->post('email', '');      // au lieu de $_POST['email'] ?? ''
$request->session('utilisateur'); // au lieu de $_SESSION['utilisateur'] ?? null
```

`fromGlobals()` lit `$_SERVER`, `$_GET`, `$_POST` et `$_SESSION` **une fois**, dans `index.php`, et range tout
dans un objet. Les contrôleurs ne touchent plus aux superglobales : ils interrogent l'objet. `setSession()`
écrit dans l'objet et dans `$_SESSION` en même temps, pour que la valeur survive à la requête.

`public static function fromGlobals(): self` s'appelle sur la classe, sans objet : `Request::fromGlobals()`.
`self` comme type de retour signifie « une instance de cette classe ». Le type `mixed` de `session()` accepte
n'importe quelle valeur : le contenu de la session n'est pas connu d'avance.

Pour un test, on construit la requête à la main, sans aucune superglobale :
`new Request('POST', '/login', [], ['email' => 'a@b.fr', 'password' => 'x'], [])`.

### `Response` : rien n'est envoyé avant la fin

```php
return Response::redirect('/');                                   // 302 + Location, pas encore envoyé
return render('login', ['erreur' => null, 'email' => '']);        // 200 + HTML, pas encore envoyé
```

Un contrôleur ne fait plus `header()` ni `echo` ni `exit` : il **retourne** une description de la réponse,
un objet avec un statut, des en-têtes et un corps. C'est `public/index.php` qui appelle `send()`, une seule
fois, tout à la fin :

```php
public function send(): void
{
    http_response_code($this->statut);
    foreach ($this->entetes as $nom => $valeur) {
        header("$nom: $valeur");
    }
    echo $this->corps;
}
```

Conséquences : plus d'erreur « headers already sent », puisque les en-têtes partent toujours avant le corps ;
un contrôleur testable, puisqu'on inspecte `$response->statut` et `$response->corps` ; et un endroit unique
pour ajouter plus tard un en-tête commun à toutes les réponses.

### `render()` capture au lieu d'afficher

Un `require` envoie son HTML directement au navigateur. Pour le mettre dans une `Response`, il faut le
récupérer dans une variable : c'est le rôle du tampon de sortie.

```php
function render(string $vue, array $donnees = [], int $statut = 200): Response
{
    $donnees += ['utilisateurConnecte' => $_SESSION['utilisateur'] ?? null];
    extract($donnees, EXTR_SKIP);

    ob_start();                                          // à partir d'ici, rien ne part vers le navigateur
    require dirname(__DIR__) . '/views/' . $vue . '.php';
    $html = ob_get_clean();                              // récupère ce qui a été affiché, vide le tampon

    return new Response($statut, $html);
}
```

Tout ce qui est affiché entre `ob_start()` et `ob_get_clean()` est mis de côté au lieu d'être envoyé. La vue
n'a pas changé d'une ligne : elle continue d'afficher avec `<?= ?>`, c'est `render()` qui décide où va le
résultat. Le paramètre `$statut` sert à la 404 : `render('404', [...], 404)`.

### Les contrôleurs : une entrée, une sortie

```php
public function login(Request $request): Response
{
    $email = trim($request->post('email', ''));
    $password = $request->post('password', '');

    $utilisateur = $this->repository->findByEmail($email);

    if ($utilisateur !== null && $utilisateur->verifierMotDePasse($password)) {
        $request->setSession('utilisateur', ['id' => $utilisateur->id, 'email' => $utilisateur->email]);

        return Response::redirect('/');
    }

    return render('login', ['erreur' => 'Email ou mot de passe incorrect.', 'email' => $email]);
}
```

Chaque méthode a la même signature : `(Request): Response`. Le `exit` a disparu, remplacé par un `return`.
La méthode privée `redirect()` de l'étape 8 aussi : `Response::redirect()` la remplace pour tout le monde.

### `public/index.php` : garder la réponse, l'envoyer en dernier

```php
$request = Request::fromGlobals();
$response = null;

foreach (require $racine . '/config/routes.php' as [$routeMethode, $routeChemin, [$classe, $action]]) {
    if ($routeMethode === $request->method && $routeChemin === $request->path) {
        $controleur = $controleurs[$classe]();
        $response = $controleur->$action($request);
        break;
    }
}

if ($response === null) {
    $response = render('404', ['chemin' => $request->path], 404);
}

$response->send();
```

Le `exit` dans la boucle devient un `break`, le `http_response_code(404)` devient un statut passé à `render()`,
et la dernière ligne est le **seul** endroit du projet qui envoie quelque chose au navigateur.

## Cheminement d'une requête

```
POST /login
  → public/index.php : autoload, includes, session, $repository
  → Request::fromGlobals() : method = POST, path = /login, post = [email, password]
  → boucle sur les routes : ['POST', '/login'] trouvé → [AuthController, 'login']
  → new AuthController($repository)
  → ->login($request) : $request->post(), $repository->findByEmail(), $request->setSession()
  → return Response::redirect('/')          ← rien n'est encore parti
  → index.php : $response->send()           ← 302 + Location: /
```

## Tester un contrôleur sans serveur web

Dans un fichier `test.php` à la racine de l'étape, lancé avec `php test.php` :

```php
<?php
require __DIR__ . '/autoload.php';
require __DIR__ . '/includes/render.php';

use App\Controller\AuthController;
use App\Core\Request;
use App\Model\UserRepository;

$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, password TEXT)');

$controleur = new AuthController(new UserRepository($pdo));
$request = new Request('POST', '/login', [], ['email' => 'a@b.fr', 'password' => 'x'], []);

$response = $controleur->login($request);
echo $response->statut;                                                  // 200 : formulaire réaffiché
echo str_contains($response->corps, 'incorrect') ? ' avec erreur' : ''; // avec erreur
```

Aucun navigateur, aucune superglobale, aucun `exit`. C'est le bénéfice principal de l'étape : à l'étape 8,
ce script était impossible à écrire.

## Pièges

- **Oublier le `return` devant `render(...)`.** La méthode retourne `null`, et PHP refuse :
  `Return value must be of type Response, null returned`. L'erreur est immédiate, c'est voulu : le type de
  retour `Response` est un contrat.
- **Garder un `exit` ou un `echo` dans un contrôleur.** Un `echo` avant `send()` place du contenu avant les
  en-têtes. Avec `php -S`, qui garde toute la sortie en mémoire jusqu'à la fin du script, vous ne verrez
  rien : le `x` part dans le corps de la redirection et tout semble marcher. Sous Apache ou en production,
  sans ce tampon, le premier caractère envoie les en-têtes et la redirection échoue avec « headers already
  sent ». Que ça marche chez vous n'est pas une preuve : tout passe par la `Response`.
- **Appeler `send()` ailleurs qu'à la fin de `index.php`.** Une réponse envoyée deux fois, ou envoyée avant
  qu'une redirection soit décidée.
- **Lire `$_SESSION` dans un contrôleur « juste pour cette fois ».** Le test ne peut plus simuler la session.

## Ce qui reste imparfait

`render()` est une fonction globale posée par un `require`, et elle lit encore `$_SESSION` pour la navigation.
Les partials `header.php` et `footer.php` ouvrent des balises dans un fichier et les ferment dans un autre.
La boucle sur les routes et la 404 sont toujours dans `index.php`, et `includes/db.php`, `includes/env.php`
sont du code procédural à côté de classes. Rien de tout cela ne parle d'utilisateurs : c'est du code qu'on
recopierait pour n'importe quel site. Étape 10.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000 -t public
./verifier.sh 09               # depuis la racine du dépôt
```

**Questions du plan** : `PLAN.md`, étape 9.
