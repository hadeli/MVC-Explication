# Étape 3 : sortir les secrets du code

> **Objectif** : séparer le **code**, identique pour tout le monde et versionné, de la **configuration**,
> propre à chaque machine et jamais versionnée.

## Le problème au départ

`includes/db.php` contient `'sqlite:' . dirname(__DIR__) . '/database.sqlite'`. Avec SQLite c'est anodin.
Imaginez la même ligne pour MySQL :

```php
$pdo = new PDO('mysql:host=db.prod.example.com;dbname=boutique', 'admin', 'S3cret!');
```

Ce fichier est dans Git. Toute personne qui a accès au dépôt, aujourd'hui ou dans un an, connaît le mot de passe
de production. Le supprimer dans un commit suivant ne change rien : il reste dans l'historique.

Second problème, plus quotidien : un camarade sur Windows avec MySQL et vous sur Linux avec SQLite ne pouvez pas
partager `db.php`. Chacun le modifie, et chaque `git pull` produit un conflit.

## Ce qui change

```
03-env/
├── .env                 NON versionné : les vraies valeurs de cette machine
├── .env.example         versionné : les clés attendues, avec des valeurs d'exemple
├── includes/
│   ├── env.php          chargerEnv() et env()
│   ├── db.php           ne contient plus aucune valeur
│   ├── header.php
│   └── footer.php
├── index.php
├── login.php
└── register.php
```

Le `.gitignore` à la racine du dépôt contient `.env` : le fichier n'est jamais ajouté, quel que soit le dossier.

## Le fichier `.env`

Une ligne par variable, au format `CLE=valeur` :

```ini
# Un commentaire
DB_DSN=sqlite:database.sqlite
DB_USER=
DB_PASSWORD=
```

Règles retenues par notre lecteur : lignes vides et lignes commençant par `#` ignorées ; tout ce qui suit le
premier `=` est la valeur ; les guillemets simples ou doubles qui entourent une valeur sont retirés ;
une variable déjà définie par le système (`getenv()`) n'est pas écrasée, ce qui permet à un serveur de
production d'imposer ses valeurs sans fichier `.env`.

## Comment ça marche

```php
// includes/env.php
function chargerEnv(string $chemin): void
{
    if (!is_file($chemin)) {
        throw new RuntimeException("Fichier de configuration introuvable : $chemin\n"
            . "Copiez .env.example vers .env puis adaptez les valeurs.");
    }
    foreach (file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
        // ignorer commentaires, découper sur le premier "=", retirer les guillemets...
        $_ENV[$cle] = $valeur;
        putenv("$cle=$valeur");
    }
}

function env(string $cle, ?string $defaut = null): ?string
{
    return $_ENV[$cle] ?? $defaut;
}
```

```php
// includes/db.php
require_once __DIR__ . '/env.php';
chargerEnv(dirname(__DIR__) . '/.env');

$dsn = env('DB_DSN') ?? throw new RuntimeException('DB_DSN manquant dans .env');
$pdo = new PDO($dsn, env('DB_USER') ?: null, env('DB_PASSWORD') ?: null);
```

Un chemin SQLite relatif est résolu depuis la racine du projet, pas depuis le dossier courant : sans cela,
la base serait créée à un endroit différent selon l'endroit d'où l'on lance le serveur.

## Pourquoi s'arrêter si `.env` manque ?

L'alternative serait de continuer avec des valeurs par défaut. C'est la pire option : le site tourne, mais
sur une base qui n'est pas la bonne, et on ne s'en rend compte que plus tard. Un message qui dit exactement
quoi faire vaut mieux qu'un comportement silencieux.

## Pièges

- **Committer `.env` « juste une fois ».** Une fois dans l'historique, la valeur est compromise. Vérifiez avec
  `git status` que le fichier n'apparaît jamais.
- **Oublier `.env.example`.** Sans lui, la personne qui clone le projet ne sait pas quelles clés créer.
  Il doit lister toutes les clés, avec des valeurs fausses mais plausibles.
- **`require` au lieu de `require_once` pour `env.php`.** Le fichier définit des fonctions : l'inclure deux fois
  est une erreur fatale.

## Ce qui reste imparfait

Rien de nouveau : la configuration est propre, mais les pages mélangent toujours logique et HTML. Étape 4.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000
./verifier.sh 03               # depuis la racine ; le script crée .env s'il manque
```

Tests manuels utiles : supprimer `.env` et recharger, lire le message. Changer `DB_DSN` vers un autre fichier,
recharger, constater que la nouvelle base est créée sans qu'aucun fichier PHP n'ait bougé.

**Questions du plan** : `PLAN.md`, étape 3. Le principe « configuration dans l'environnement » est le
troisième des douze facteurs (https://12factor.net/fr/config).
