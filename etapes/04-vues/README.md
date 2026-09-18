# Étape 4 : séparer ce qu'on calcule de ce qu'on affiche

> **Objectif** : qu'un fichier calcule et qu'un autre affiche. Celui qui affiche ne connaît ni la base, ni le
> formulaire, ni la session : seulement les variables qu'on lui donne.

## Le problème au départ

Dans `register.php`, le PHP et le HTML alternent : traitement en haut, puis HTML, avec du PHP dans le HTML
(`<?php if ($succes): ?>`, `<?php foreach ($erreurs as $erreur): ?>`). Pour modifier l'apparence du formulaire,
il faut ouvrir un fichier qui contient des requêtes SQL et des `$_POST`. Rien n'empêche d'écrire `$_POST['email']`
directement au milieu du HTML, et c'est ce qui finit toujours par arriver.

## Ce qui change

```
04-vues/
├── includes/
│   ├── db.php
│   ├── env.php
│   └── render.php           NOUVEAU : render($vue, $donnees)
├── views/                   NOUVEAU : uniquement de l'affichage
│   ├── partials/
│   │   ├── header.php       ancien includes/header.php
│   │   └── footer.php       ancien includes/footer.php
│   ├── home.php
│   ├── login.php
│   └── register.php
├── index.php                plus une seule balise HTML
├── login.php
└── register.php
```

Les pages à la racine ne contiennent plus que du PHP et se terminent par un appel à `render()` :

```php
// register.php (fin du fichier)
render('register', [
    'erreurs' => $erreurs,
    'succes'  => $succes,
    'email'   => $email,
]);
```

## Comment ça marche

```php
// includes/render.php
function render(string $vue, array $donnees = []): void
{
    $donnees += ['utilisateurConnecte' => $_SESSION['utilisateur'] ?? null];
    extract($donnees, EXTR_SKIP);
    require dirname(__DIR__) . '/views/' . $vue . '.php';
}
```

Trois idées :

1. **`extract()`** transforme chaque clé du tableau en variable locale : `$donnees['email']` devient `$email`.
   La vue peut donc écrire `<?= htmlspecialchars($email) ?>` sans savoir d'où vient la valeur.
2. **La portée de la fonction** est la protection. Le `require` a lieu à l'intérieur de `render()`, donc la vue
   ne voit que les variables locales de `render()` : celles issues d'`extract()`, et rien d'autre. `$pdo`,
   défini dans la page, n'existe pas pour elle.
3. **`utilisateurConnecte`** est ajouté à toutes les vues par `render()` : la navigation en a besoin partout,
   et c'est la seule lecture de `$_SESSION` autorisée hors des pages.

`EXTR_SKIP` empêche une clé du tableau d'écraser une variable existante. Ici la seule variable en jeu est
`$donnees` elle-même, mais la précaution devient importante quand les données viennent de l'utilisateur.

## La règle à afficher au mur

> Une vue ne lit jamais `$_POST`, `$_GET`, `$_SESSION` ni `$pdo`. Elle n'exécute pas de requête et ne prend
> pas de décision métier. Elle contient uniquement : `<?= ?>`, `if`/`else`, `foreach`, et `require` d'un partial.

Vérification en une commande : `grep -rn '\$_' views/` ne doit rien renvoyer.

## Ce que ça change concrètement

- Un intégrateur peut modifier `views/login.php` sans jamais voir de SQL.
- On peut afficher la même vue depuis plusieurs endroits : le formulaire de connexion vide (GET) et le
  formulaire avec erreur (POST) passent par le même `views/login.php` avec des données différentes.
- On pourrait remplacer `views/login.php` par une version JSON sans toucher à `login.php` : la vue est devenue
  un détail d'affichage.

## Pièges

- **Oublier une clé.** Si `login.php` n'envoie pas `erreur`, la vue produit un avertissement « variable non
  définie ». Le contrat vue/page n'est toujours pas écrit ; il le sera avec les contrôleurs.
- **Glisser de la logique dans la vue.** `<?php if (strlen($password) < 8) ?>` dans une vue, c'est une
  validation au mauvais endroit. Si une vue a besoin d'un calcul, la page doit le faire et passer le résultat.
- **`header('Location')` après `render()`.** La vue a déjà émis du HTML, la redirection échoue. Toujours
  rediriger avant de rendre.

## Ce qui reste imparfait

Les pages contiennent encore le SQL. `login.php` sait qu'il existe une table `users` avec une colonne
`password`. Étape 5.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000
./verifier.sh 04
```

**Questions du plan** : `PLAN.md`, étape 4.
