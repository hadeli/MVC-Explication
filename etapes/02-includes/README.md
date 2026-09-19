# Étape 2 : factoriser ce qui se répète

> **Objectif** : qu'une information n'existe qu'à un seul endroit. Changer le nom de la base ou un lien du
> menu doit se faire dans un seul fichier.

## Le problème au départ

Dans l'étape 1, trois familles de code sont copiées d'une page à l'autre :

| Famille | Présente dans | Lignes environ |
|---|---|---|
| Connexion PDO + `CREATE TABLE` | `login.php`, `register.php` | 7 |
| Début du HTML : doctype, `<head>`, `<nav>` | les trois pages | 15 |
| Fin du HTML | les trois pages | 2 |

Soit une vingtaine de lignes dupliquées par page, sur des fichiers de 40 à 100 lignes.

## Ce qui change

```
02-includes/
├── includes/
│   ├── db.php          connexion PDO + création de la table, expose $pdo
│   ├── header.php      doctype, <head>, <nav> ; attend $titre
│   └── footer.php      </body></html>
├── index.php
├── login.php
└── register.php
```

Chaque page remplace le code dupliqué par un `require` :

```php
session_start();
require __DIR__ . '/includes/db.php';     // $pdo est maintenant disponible
// ... traitement ...
$titre = 'Connexion';
require __DIR__ . '/includes/header.php';
// ... HTML propre à la page ...
require __DIR__ . '/includes/footer.php';
```

## Comment ça marche

`require` insère le contenu du fichier à l'endroit de l'appel, comme si on l'avait copié-collé. Les variables
définies dans le fichier inclus (`$pdo`) sont visibles dans la page, et réciproquement (`$titre`, défini
dans la page, est lu par `header.php`). C'est simple, et c'est aussi la limite : rien n'indique en lisant
`login.php` que `$pdo` vient de `db.php`, ni en lisant `header.php` que `$titre` doit exister.

Quatre instructions existent : `include`, `require`, `include_once`, `require_once`.

- `require` s'arrête avec une erreur fatale si le fichier manque, `include` continue avec un avertissement.
  Pour un fichier indispensable comme la connexion, on veut l'erreur.
- `_once` n'inclut pas une seconde fois un fichier déjà inclus. Indispensable pour un fichier qui définit
  des fonctions ou des classes (les redéfinir est une erreur fatale), inutile pour du HTML.

On utilise `__DIR__` plutôt qu'un chemin relatif : pour un chemin relatif, PHP cherche d'abord dans
l'`include_path` et le dossier courant du processus, et seulement ensuite à côté du fichier en cours. Le
fichier trouvé dépend donc de l'endroit d'où le serveur est lancé et d'éventuels homonymes. `__DIR__`
désigne toujours le même fichier.

## Où mettre `session_start()` ?

La tentation est de le placer dans `header.php` puisqu'il est commun. Mais `login.php` a besoin de la session
**avant** d'afficher quoi que ce soit : il lit `$_SESSION['utilisateur']` pour rediriger, et il doit pouvoir
envoyer un `header('Location: ...')`, ce qui est impossible une fois le premier caractère HTML émis. Il reste
donc en tête de chaque page. Il disparaîtra des pages à l'étape 7, quand un seul fichier recevra toutes les requêtes.

## Pièges

- **Un `<?php` ou `?>` qui déborde.** Une ligne vide ou un espace après le `?>` final de `db.php` serait envoyé
  au navigateur avant le HTML et empêcherait les redirections (PHP n'avale qu'un seul retour à la ligne après `?>`). Ici `db.php` n'a pas de balise fermante, c'est voulu.
- **Ouvrir `includes/header.php` dans le navigateur** affiche un début de page cassé : il est accessible par URL
  comme tout le reste. Le problème « tout est public » n'est pas résolu, il l'est à l'étape 7.
- **Une variable oubliée.** Si une page oublie `$titre`, `header.php` produit un avertissement, visible dans le
  terminal où tourne `php -S` (dans la page seulement si `display_errors` est activé). Le contrat entre la page
  et l'include n'est écrit nulle part.

## Ce qui reste imparfait

Chaque page mélange toujours traitement du formulaire et HTML. Le chemin de la base est écrit en dur dans
`db.php`, ce qui devient un problème dès qu'on partage le code : étape 3.

## Lancer et vérifier

```bash
php -S localhost:8000          # puis http://localhost:8000/index.php
./verifier.sh 02               # depuis la racine du dépôt
```

Test manuel utile : changer le nom du fichier SQLite dans `db.php`, recharger. Un seul fichier modifié.

**Questions du plan** : `PLAN.md`, étape 2.
