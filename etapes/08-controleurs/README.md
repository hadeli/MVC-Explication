# Étape 8 : les contrôleurs

**Changement par rapport à l'étape 7** : les scripts de `actions/` deviennent des méthodes de classes.

| Fichier | Rôle |
|---|---|
| `src/Controller/AuthController.php` | `loginForm()`, `login()`, `registerForm()`, `register()`, `logout()` |
| `src/Controller/HomeController.php` | `index()` |
| `config/routes.php` | Table `[méthode HTTP, chemin, [classe, méthode]]` : GET et POST sont désormais distingués |
| `public/index.php` | Construit `UserRepository` une fois, l'injecte dans `AuthController`, dispatche |

Le repository arrive par le constructeur : on peut instancier `AuthController` avec un faux repository
sans base de données. L'autoloader de l'étape 6 charge `App\Controller\...` sans avoir été modifié.

Ce qui reste imparfait, et que l'étape 9 corrige : les contrôleurs lisent `$_POST` et `$_SESSION`
directement, appellent `header()` et `exit`, et dépendent de la fonction globale `render()`.
