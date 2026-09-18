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

## Organisation

Chaque étape est un projet complet et fonctionnel dans son propre dossier :

```
etapes/
├── 01-pages-classiques/
├── 02-includes/
├── 03-env/
├── 04-vues/
├── 05-modele/
├── 06-autoloader/
├── 07-front-controller/
├── 08-controleurs/
├── 09-noyau/
└── 10-templates/
```

Chaque dossier contient un `README.md` qui résume ce qui a changé par rapport à l'étape précédente.
Comparer deux étapes consécutives montre exactement la transformation :

```bash
diff -r etapes/04-vues etapes/05-modele
```

## Lancer une étape

Étapes 1 à 6 (les pages sont à la racine du dossier) :

```bash
cd etapes/03-env
cp .env.example .env        # à partir de l'étape 3
php -S localhost:8000
```

puis ouvrir http://localhost:8000/index.php.

Étapes 7 à 10 (front controller, seul `public/` est exposé) :

```bash
cd etapes/09-noyau
cp .env.example .env
php -S localhost:8000 -t public
```

puis ouvrir http://localhost:8000/. Les URLs deviennent `/login`, `/register`, `/logout`.

La base SQLite `database.sqlite` est créée automatiquement à la première requête dans le dossier de l'étape.
La supprimer remet les données à zéro.

## Vérifier une étape

```bash
./verifier.sh 05          # une étape
for n in 01 02 03 04 05 06 07 08 09 10; do ./verifier.sh $n; done   # toutes
```

Le script démarre un serveur PHP sur le bon dossier, crée `.env` depuis `.env.example` si besoin, puis
enchaîne : accueil non connecté, inscription refusée puis acceptée puis doublon, connexion refusée puis
acceptée, accueil connecté, déconnexion, échappement HTML. À partir de l'étape 7 il vérifie aussi le 404 et
l'inaccessibilité de la base par URL.

## Documents

| Fichier | Pour qui | Contenu |
|---|---|---|
| [`PLAN.md`](PLAN.md) | Élèves | Parcours guidé en 10 étapes, sous forme de questions. Aucune solution. |
| [`MVC.md`](MVC.md) | Fin de parcours | Diagrammes de l'architecture cible et trace complète d'une requête `POST /login`. |
| `etapes/*/README.md` | Élèves, après réflexion | Ce que chaque étape a changé et pourquoi. Ce sont les corrigés. |
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

**Les 10 étapes sont réalisées** et passent toutes le script de vérification.
