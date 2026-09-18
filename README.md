# Du PHP classique au MVC

Projet pédagogique : partir de trois pages PHP « classiques » (HTML et logique mélangés) et les transformer,
étape par étape, en une application MVC complète. Chaque étape résout un problème concret visible dans le code
de l'étape précédente, et le site fonctionne à l'identique à la fin de chacune.

## Règles

- **Aucune dépendance.** Pas de Composer, pas de framework, pas de bibliothèque. L'autoloader, le chargeur
  `.env` et le moteur de templates sont écrits à la main pour être compris.
- **PHP 8 ou plus**, avec l'extension `pdo_sqlite` (incluse par défaut).
- **Comportement constant.** À la fin de chaque étape, le parcours inscription, connexion, accueil connecté,
  déconnexion doit fonctionner exactement comme avant.

## Lancer le projet

```bash
php -S localhost:8000
```

puis ouvrir http://localhost:8000/index.php.

À partir de l'étape 7 (front controller), la commande devient :

```bash
php -S localhost:8000 -t public
```

La base SQLite `database.sqlite` est créée automatiquement à la première requête. La supprimer remet
les données à zéro.

## Documents

| Fichier | Pour qui | Contenu |
|---|---|---|
| [`PLAN.md`](PLAN.md) | Élèves | Parcours guidé en 10 étapes, sous forme de questions. Aucune solution. |
| [`MVC.md`](MVC.md) | Fin de parcours | Diagrammes de l'architecture cible et trace complète d'une requête `POST /login`. |
| [`CLAUDE.md`](CLAUDE.md) | Outils | Consignes pour Claude Code : contraintes, commandes, état courant. |

## Feuille de route

| Étape | Contenu | Concept introduit |
|---|---|---|
| 1 | `index.php`, `login.php`, `register.php` autonomes | Point de départ |
| 2 | `includes/` : connexion, en-tête, pied de page | Réutilisation |
| 3 | `.env` et `.env.example`, chargeur maison | Configuration hors du code |
| 4 | Dossier `views/` et fonction `render()` | Vue |
| 5 | Classes `User` et `UserRepository` | Modèle |
| 6 | `autoload.php` maison, puis namespaces `App\` reflétés dans `src/` | Autoloading |
| 7 | `public/index.php` et routeur, URLs `/login`, `/register` | Front controller |
| 8 | `AuthController`, `HomeController` | Contrôleur, injection de dépendances |
| 9 | `src/Core/` : `Router`, `Request`, `Response`, `View`, `Database` | Noyau réutilisable |
| 10 | `src/Core/Template.php`, syntaxe `{{ }}`, compilation dans `cache/` | Templating |

## État actuel

**Étape 1 terminée.** Trois pages à la racine, chacune contenant sa propre connexion PDO, son `session_start()`,
sa navigation et son HTML. La duplication est volontaire : c'est le problème que les étapes suivantes résolvent.

| Page | Rôle |
|---|---|
| `index.php` | Accueil, affiche l'utilisateur connecté, gère `?action=logout` |
| `register.php` | Validation, unicité de l'email, `password_hash`, insertion |
| `login.php` | `password_verify`, session, redirection vers l'accueil |

## Vérifier une étape à la main

```bash
php -S localhost:8000 &
curl -s -d 'email=test@example.com&password=motdepasse1&confirmation=motdepasse1' http://localhost:8000/register.php | grep -o 'Votre compte a été créé'
curl -s -c cookies -o /dev/null -w '%{http_code}\n' -d 'email=test@example.com&password=motdepasse1' http://localhost:8000/login.php   # attendu : 302
curl -s -b cookies http://localhost:8000/index.php | grep -o 'Bonjour <strong>[^<]*'
```

Adapter les URLs à partir de l'étape 7 (`/register`, `/login`, `/`).
