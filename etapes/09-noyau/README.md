# Étape 9 : extraire le noyau

**Changement par rapport à l'étape 8** : tout ce qui ne parle pas d'utilisateurs devient une classe réutilisable
dans `src/Core/`. Le dossier `includes/` disparaît.

| Classe | Rôle |
|---|---|
| `Core\Env` | Ancien `includes/env.php`, en classe |
| `Core\Database` | Ancien `includes/db.php` : lit `.env`, une connexion PDO par requête |
| `Core\Request` | Enveloppe `$_GET`, `$_POST`, `$_SERVER`, `$_SESSION` |
| `Core\Response` | Statut, en-têtes, corps ; `redirect()` ; envoyée une seule fois par `send()` |
| `Core\View` | Rend une vue dans `views/layout.php` via un tampon de sortie, renvoie une `Response` |
| `Core\Router` | Associe méthode HTTP et chemin à un contrôleur, renvoie une `Response` 404 sinon |

Les contrôleurs reçoivent une `Request` et **retournent** une `Response`. Ils n'appellent plus `header()`,
`exit`, ni `render()`. `views/partials/` est remplacé par `views/layout.php`, qui reçoit la vue dans `$contenu`.

Le chemin d'une requête `POST /login` est décrit pas à pas dans `MVC.md` à la racine du projet.

Un contrôleur peut désormais être testé sans serveur web, avec une base en mémoire :

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

```bash
cp .env.example .env
php -S localhost:8000 -t public
```
