# Étape 4 : séparer ce qu'on calcule de ce qu'on affiche

**Changement par rapport à l'étape 3** : plus aucune balise HTML dans les pages à la racine.

| Fichier | Rôle |
|---|---|
| `views/home.php`, `views/login.php`, `views/register.php` | HTML uniquement, avec `<?= ?>`, `if` et `foreach` |
| `views/partials/header.php`, `footer.php` | Anciens `includes/header.php` et `footer.php`, déplacés avec les vues |
| `includes/render.php` | `render($vue, $donnees)` : `extract()` puis `require` de la vue |

Règle posée : une vue ne lit jamais `$_POST`, `$_SESSION` ni `$pdo`. Elle ne connaît que les variables
que `render()` lui donne. La seule donnée commune, `$utilisateurConnecte`, est injectée par `render()`
pour la navigation.

Les pages `index.php`, `login.php` et `register.php` calculent des variables puis appellent `render()`.
Elles contiennent encore le SQL : c'est l'objet de l'étape suivante.
