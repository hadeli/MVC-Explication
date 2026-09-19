# Étape 7 : un seul point d'entrée

> **Objectif** : que toutes les requêtes passent par un seul fichier, qui décide quoi exécuter. Le reste du
> projet sort du dossier public et devient inaccessible par URL.

## Le problème au départ

Le serveur web fait correspondre l'URL au disque. Trois conséquences :

1. `http://localhost:8000/database.sqlite` télécharge la base. `/views/login.php` affiche une vue cassée.
   `/includes/db.php` s'exécute. Tout est exposé.
2. Chaque page répète `session_start()` et ses `require` (`autoload.php`, `db.php`, `render.php` selon les besoins).
3. Les URLs sont des noms de fichiers : `/login.php`. Changer la structure du projet change les URLs.

## Ce qui change

```
07-front-controller/
├── public/                       SEUL dossier servi par le serveur web
│   ├── index.php                 le front controller
│   └── .htaccess                 facultatif : pour Apache, inutile avec php -S
├── actions/                      anciennes pages, sans leur préambule
│   ├── home.php
│   ├── login.php
│   ├── register.php
│   └── logout.php                NOUVEAU : la déconnexion devient une URL
├── config/
│   └── routes.php                NOUVEAU : chemin => action
├── autoload.php
├── includes/  (db.php, env.php, render.php)
├── src/Model/
└── views/
    ├── 404.php                   NOUVEAU
    └── ...                       liens et actions de formulaire en URLs propres
```

Les URLs deviennent `/`, `/login`, `/register`, `/logout`. Le serveur se lance avec `-t public`.

## Comment ça marche

### 1. Toute URL arrive sur `public/index.php`

Avec le serveur intégré de PHP, une URL qui ne correspond à aucun fichier du dossier `-t` est servie par
`index.php`. Avec Apache, le `.htaccess` fait la même chose :

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

`/database.sqlite` ne correspond plus à un fichier de `public/` : la requête arrive sur `index.php`, qui ne
connaît pas ce chemin et répond 404. Le `.htaccess` n'est pas demandé par le parcours : il est là pour qui
déploierait sur Apache.

### 2. Le front controller

```php
$racine = dirname(__DIR__);                    // on est dans public/, le projet est un cran au-dessus

require $racine . '/autoload.php';
require $racine . '/includes/render.php';
require $racine . '/includes/db.php';
session_start();                               // une seule fois, pour tout le site

$routes = require $racine . '/config/routes.php';

$chemin = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';   // "/login?x=1" -> "/login"
$chemin = rtrim($chemin, '/') ?: '/';                                // "/login/"    -> "/login"

if (!isset($routes[$chemin])) {
    http_response_code(404);
    render('404', ['chemin' => $chemin]);
    exit;
}

require $racine . '/actions/' . $routes[$chemin] . '.php';
```

`$_SERVER['REQUEST_URI']` contient le chemin **et** la query string. On ne garde que le chemin pour comparer
avec la table des routes. `?:` est le « ou sinon » de PHP : `$a ?: '/'` vaut `$a` si `$a` est vrai (non vide),
sinon `'/'`. Il rattrape le cas où `parse_url()` ou `rtrim()` renvoient une chaîne vide, par exemple pour l'URL `/`.

### 3. La table des routes

```php
return [
    '/'         => 'home',
    '/login'    => 'login',
    '/register' => 'register',
    '/logout'   => 'logout',
];
```

Un tableau, pas un mécanisme compliqué. Toute URL absente de ce tableau est un 404, quel que soit ce qui existe
sur le disque. C'est l'inverse de l'étape précédente : avant, tout était accessible sauf ce qu'on cachait ;
maintenant, rien n'est accessible sauf ce qu'on déclare.

### 4. Les actions

`actions/login.php` est l'ancien `login.php` débarrassé de son préambule : plus de `session_start()`, plus de
`require`. Il commence directement par la logique et hérite de `$pdo` et de `render()` grâce au `require`
depuis le front controller. La déconnexion, qui était un `?action=logout` traité dans `index.php`, devient
une action à part entière.

## Cheminement d'une requête

```
GET /login
  → serveur web : pas de fichier public/login → public/index.php
  → autoload, render, db, session_start
  → $chemin = "/login" → routes["/login"] = "login"
  → require actions/login.php
  → render('login', [...]) → views/login.php → HTML
```

## Ce que ça change concrètement

- Ajouter une page « à propos » : une ligne dans `routes.php`, un fichier dans `actions/`, une vue. Comparez
  avec la vingtaine de lignes à copier à l'étape 1.
- Le code source, la base et la configuration ne sont plus joignables par URL.
- Il y a un seul endroit pour ajouter demain une gestion d'erreurs globale, un journal, une vérification
  d'authentification.

## Pièges

- **Oublier `-t public`.** Le serveur sert alors la racine du projet, et tout redevient accessible. Le
  script `verifier.sh` demande explicitement `/database.sqlite` et `/.env` et attend un 404 pour les deux.
- **Un deuxième fichier PHP dans `public/`.** Le serveur le sert directement, sans passer par `index.php` : ni
  session, ni routes, ni 404. `public/` ne contient que `index.php` et, pour Apache, `.htaccess`.
- **Chemins relatifs dans les vues.** `href="login.php"` ne marche plus. Tous les liens deviennent absolus
  (`/login`) : ils ne dépendent plus de la page qui les affiche.
- **Une action qui oublie `exit` après une redirection.** Le front controller ne fait rien après le `require`,
  mais l'action continuerait à s'exécuter et à produire du HTML après le `header('Location')`.

## Ce qui reste imparfait

Les actions sont des scripts procéduraux qui reçoivent `$pdo` par magie et distinguent GET et POST à la main.
Deux actions ne peuvent pas partager de code sans un nouvel include. Étape 8.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000 -t public         # puis http://localhost:8000/
./verifier.sh 07                        # vérifie aussi le 404 et l'inaccessibilité de la base et du .env
```

**Questions du plan** : `PLAN.md`, étape 7.
