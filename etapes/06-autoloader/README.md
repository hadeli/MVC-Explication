# Étape 6 : charger les classes automatiquement

**Changement par rapport à l'étape 5** : plus aucun `require` de classe dans les pages.

| Fichier | Rôle |
|---|---|
| `autoload.php` | `spl_autoload_register` : transforme `App\Model\User` en `src/Model/User.php` et le charge |
| `src/Model/User.php`, `src/Model/UserRepository.php` | Anciens `models/`, avec `namespace App\Model;` |

Règle de correspondance, la même que la norme PSR-4 : on retire le préfixe `App\`, on remplace `\` par `/`,
on ajoute `.php`, on cherche dans `src/`. Une classe est chargée une seule fois, à sa première utilisation.

Chaque page ne contient plus qu'un `require __DIR__ . '/autoload.php'` et un `use`. Ajouter une classe ne
demande plus d'inclure quoi que ce soit. Ce fichier ne bougera plus jusqu'à la fin du parcours.

Les fonctions `render()`, `chargerEnv()` et la connexion restent dans `includes/` : ce ne sont pas des classes.
