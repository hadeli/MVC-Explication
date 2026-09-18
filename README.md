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

À la racine du dépôt, `index.php`, `login.php` et `register.php` sont le **point de départ** : les trois
pages classiques, identiques à celles de `etapes/01-pages-classiques/`.

```bash
php -S localhost:8000      # depuis la racine, puis http://localhost:8000/index.php
```

Le parcours est découpé en treize étapes, une par dossier :

```
etapes/
├── 01-pages-classiques/       code complet : le point de départ
├── 02-includes/               README.md seulement : à vous d'écrire le code
├── 03-env/
├── 04-vues/
├── 05-modele/
├── 06-autoloader/
├── 07-front-controller/
├── 08-controleurs/
├── 09-noyau/
├── 10-templates/
├── 11-interfaces/
├── 12-routeur-autonome/
└── 13-cache-noyau/
```

**Sur la branche `master`, seule l'étape 1 contient du code.** Les dossiers 2 à 13 ne contiennent que leur
`README.md`, qui explique le problème de l'étape, le mécanisme à mettre en place, les pièges et les limites.
Le code, c'est vous qui l'écrivez : on ne progresse pas en lisant treize corrigés à la suite.

La démarche pour une étape :

1. Lire les questions de l'étape dans `PLAN.md` et y répondre, sur papier ou en tête, **avant** de coder.
2. Copier le code de l'étape précédente dans le dossier de l'étape (`cp -r etapes/01-pages-classiques/. etapes/02-includes/`
   pour la première), puis le transformer.
3. Lancer `./verifier.sh NN` jusqu'à obtenir `=> OK`.
4. Lire le `README.md` de l'étape, comparer avec ce que vous avez fait.

### La correction

La branche `correction` contient les treize dossiers complets, chacun fonctionnel et vérifié :

```bash
git switch correction                          # voir tout le code corrigé
diff -r etapes/04-vues etapes/05-modele        # exactement ce qu'une étape a changé
git switch master                              # revenir au parcours
```

Sans changer de branche : `git diff correction:etapes/04-vues correction:etapes/05-modele`, ou
`git show correction:etapes/09-noyau/src/Core/Router.php` pour un fichier précis.

## Lancer une étape

Étapes 1 à 6 (les pages sont à la racine du dossier) :

```bash
cd etapes/03-env
cp .env.example .env        # à partir de l'étape 3
php -S localhost:8000
```

puis ouvrir http://localhost:8000/index.php.

Étapes 7 à 13 (front controller, seul `public/` est exposé) :

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
for n in 01 02 03 04 05 06 07 08 09 10 11 12 13; do ./verifier.sh $n; done   # toutes
```

Le script démarre un serveur PHP sur le bon dossier, crée `.env` depuis `.env.example` si besoin, puis
enchaîne : accueil non connecté, inscription refusée puis acceptée puis doublon, connexion refusée puis
acceptée, accueil connecté, déconnexion, échappement HTML. À partir de l'étape 7 il vérifie aussi le 404 et
l'inaccessibilité de la base par URL. Le comportement attendu est **le même à toutes les étapes** : c'est
tout l'intérêt, on change la structure, pas ce que voit l'utilisateur.

Sur un dossier qui ne contient pas encore de code, le script le dit et s'arrête.

## Documents

| Fichier | Pour qui | Contenu |
|---|---|---|
| [`PLAN.md`](PLAN.md) | Élèves | Parcours guidé en 13 étapes, sous forme de questions. Aucune solution. |
| [`MVC.md`](MVC.md) | Fin de parcours | Diagrammes de l'architecture cible et trace complète d'une requête `POST /login`. |
| `etapes/*/README.md` | Élèves, après réflexion | Ce que chaque étape a changé et pourquoi, avec des extraits. À lire après avoir essayé. |
| Branche `correction` | Élèves bloqués, enseignants | Les treize dossiers avec tout leur code. |
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
| 11 | `ControllerInterface`, `AbstractController` (`verb()`, `path()`), `FormTrait`, un contrôleur par verbe/chemin, routeur sans table de routes | Interface, classe abstraite, trait |
| 12 | `ControllerFinder` balaye `src/Controller/`, `Container` construit par réflexion, plus aucun contrôleur nommé dans `index.php` | Découverte automatique, injection par réflexion |
| 13 | `ControllerCache` écrit le résultat du balayage et de la réflexion dans `cache/controleurs.php`, invalidé par `filemtime` | Cache d'un calcul déterministe |

## État actuel

**Les 13 étapes sont réalisées** sur la branche `correction` et y passent toutes le script de vérification.
Sur `master`, seule l'étape 1 a du code.
