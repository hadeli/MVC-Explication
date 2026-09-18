# Étape 6 : charger les classes automatiquement

> **Objectif** : ne plus jamais écrire `require` pour une classe. PHP trouve le fichier tout seul, à partir
> du nom de la classe, grâce à une convention.

## Le problème au départ

Chaque page commence par une pile de `require`. Ajouter une classe, c'est penser à l'inclure dans chaque page
qui l'utilise ; l'oublier ne se voit qu'à l'exécution, quand PHP tente d'utiliser la classe. Et l'ordre compte :
si `UserRepository` référençait `User` dans une signature, inclure `UserRepository.php` avant `User.php`
poserait problème dans certains cas.

## Ce qui change

```
06-autoloader/
├── autoload.php                  NOUVEAU : la règle de correspondance nom de classe -> fichier
├── src/                          NOUVEAU : remplace models/
│   └── Model/
│       ├── User.php              namespace App\Model;
│       └── UserRepository.php    namespace App\Model;
├── includes/  (db.php, env.php, render.php : des fonctions, pas des classes)
├── views/
├── index.php
├── login.php                     un seul require : autoload.php
└── register.php
```

```php
// login.php, en tête
require __DIR__ . '/autoload.php';

use App\Model\UserRepository;
```

## Comment ça marche

### 1. `spl_autoload_register`

Quand PHP rencontre une classe qu'il ne connaît pas (`new UserRepository(...)`), au lieu d'échouer
immédiatement, il appelle chaque fonction enregistrée avec `spl_autoload_register()` en lui passant le nom
complet de la classe. Si, après ces appels, la classe existe, l'exécution continue. Sinon, erreur.

```php
spl_autoload_register(function (string $classe): void {
    $prefixe = 'App\\';
    if (!str_starts_with($classe, $prefixe)) {
        return;                                   // pas à nous : on laisse la main
    }
    $relatif = substr($classe, strlen($prefixe)); // Model\UserRepository
    $fichier = __DIR__ . '/src/' . str_replace('\\', '/', $relatif) . '.php';
    if (is_file($fichier)) {
        require $fichier;
    }
});
```

Le chargement est **paresseux** : un fichier n'est lu que si la classe est réellement utilisée pendant la
requête. `index.php` n'utilise aucune classe, donc aucun fichier de `src/` n'est chargé pour l'accueil.

### 2. Les namespaces

Un namespace est un préfixe qui évite les collisions de noms (deux bibliothèques peuvent avoir chacune une
classe `User`). Ici il sert surtout à encoder **l'emplacement** de la classe :

```
App\Model\UserRepository
 │    │         └── fichier    UserRepository.php
 │    └──────────── dossier    src/Model/
 └───────────────── racine     src/
```

C'est la convention PSR-4, utilisée par tout l'écosystème PHP : le préfixe correspond à un dossier racine,
chaque segment de namespace à un sous-dossier, le nom de classe au fichier. Notre autoloader fait exactement
cette transformation en trois lignes.

Conséquence dans le code : chaque fichier de classe commence par `namespace App\Model;`, et tout ce qui vient
de l'extérieur du namespace doit être importé (`use PDO;` dans `UserRepository.php`) ou préfixé (`\PDO`).

### 3. Deux détails qui comptent

- Le `return` quand le préfixe ne correspond pas : notre autoloader ne doit pas gêner d'autres autoloaders
  qu'on pourrait enregistrer plus tard.
- Le test `is_file()` avant `require` : si le fichier n'existe pas, on ne fait rien et PHP produit
  `Class "App\Model\Test" not found`, qui dit exactement quelle classe manque. Un `require` sur un fichier
  absent produirait une erreur moins lisible sur le chemin.

## Expérience à faire

Ajoutez `echo "chargement de $classe<br>";` en tête de la fonction. Rechargez `/register.php` : deux lignes.
Rechargez `/index.php` : aucune. Supprimez ensuite `src/Model/User.php` et rechargez `/login.php` : lisez le message.

## Ce que ça change concrètement

Ajouter une classe = créer un fichier au bon endroit avec le bon namespace. Rien d'autre. Ce fichier
`autoload.php` ne sera plus modifié jusqu'à la fin du parcours : il chargera les contrôleurs de l'étape 8 et
le noyau de l'étape 9 sans le savoir.

## Pièges

- **Nom de fichier différent du nom de classe**, ou casse différente (`userRepository.php`). Fonctionne sur
  Windows et macOS, échoue sur Linux. Une classe, un fichier, même nom exactement.
- **Oublier `use PDO;`.** Dans un namespace, `PDO` désigne `App\Model\PDO`, qui n'existe pas. L'erreur ne se
  voit qu'à l'exécution.
- **Mettre des fonctions dans `src/`.** L'autoloader ne charge que des classes : PHP ne le déclenche pas pour
  une fonction inconnue. C'est pourquoi `render()`, `chargerEnv()` et `db.php` restent dans `includes/`.

## Ce qui reste imparfait

Toujours une URL par fichier, toujours tout le projet accessible par le navigateur, toujours `session_start()`
et les `require` d'`includes/` répétés dans chaque page. Étape 7.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000
./verifier.sh 06
```

**Questions du plan** : `PLAN.md`, étape 6.
