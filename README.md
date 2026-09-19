# Du PHP classique au MVC

Projet pédagogique : partir de trois pages PHP « classiques » (HTML et logique mélangés) et les transformer,
étape par étape, en une application MVC complète. Chaque étape résout un problème concret visible dans le code
de l'étape précédente, et le site fonctionne à l'identique à la fin de chacune.

## Règles

- **Aucune dépendance.** Pas de Composer, pas de framework, pas de bibliothèque. L'autoloader, le chargeur
  `.env` et les composants de vue sont écrits à la main pour être compris.
- **Comportement constant.** À la fin de chaque étape, le parcours inscription, connexion, accueil connecté,
  déconnexion doit fonctionner exactement comme avant.

## Prérequis

PHP 8.1 ou plus (le code utilise `readonly` et `never`) avec l'extension `pdo_sqlite` (incluse par défaut), `git`, et un terminal Bash avec `curl`
pour le script de vérification. Sous Linux et macOS, tout est déjà là ou s'installe avec le gestionnaire
de paquets habituel.

### Sous Windows : utilisez WSL

`verifier.sh` est un script Bash qui utilise `curl` : il ne tourne pas dans PowerShell ni dans l'invite de
commandes. Installez WSL (Windows Subsystem for Linux) et travaillez dedans :

```powershell
wsl --install                      # PowerShell en administrateur, puis redémarrer
```

Puis, dans le terminal Ubuntu qui s'ouvre :

```bash
sudo apt update && sudo apt install php-cli php-sqlite3 curl git
cd ~ && git clone <url-du-depot>    # clonez dans votre dossier Linux (~), pas dans /mnt/c/
```

Le serveur lancé avec `php -S localhost:8000` dans WSL est accessible depuis le navigateur Windows à la même
adresse. Depuis PhpStorm ou VS Code, ouvrez le dossier via `\\wsl$\Ubuntu\home\...` ou l'extension WSL.
Git Bash peut dépanner pour lancer le script si PHP pour Windows est dans le `PATH` avec `pdo_sqlite`
activé, mais WSL évite toutes ces surprises.

## Organisation

À la racine du dépôt, `index.php`, `login.php` et `register.php` sont le **point de départ** : les trois
pages classiques, identiques à celles de `etapes/01-pages-classiques/`.

```bash
php -S localhost:8000      # depuis la racine, puis http://localhost:8000/index.php
```

Le parcours est découpé en quatorze étapes, une par dossier :

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
├── 09-requete-reponse/
├── 10-noyau/
├── 11-composants/
├── 12-interfaces/
├── 13-routeur-autonome/
└── 14-cache-noyau/
```

**Seule l'étape 1 contient du code.** Les dossiers 2 à 14 ne contiennent que leur `README.md`, qui explique
le problème de l'étape, le mécanisme à mettre en place, les pièges et les limites. Le code, c'est vous qui
l'écrivez : on ne progresse pas en lisant quatorze corrigés à la suite.

**Les étapes 12 à 14 sont un bonus.** Le parcours attendu s'arrête à l'étape 11 : vous avez alors un MVC
complet et testable, des vues qui échappent toutes seules, et tout ce qu'il faut pour structurer le site de
votre SAÉ. Les trois dernières étapes (interfaces et traits, injection par réflexion, cache) préparent les
unités d'enseignement suivantes, où vous retrouverez ces mécanismes à l'intérieur d'un framework. Faites-les
si le temps le permet, en lisant le README de l'étape pendant que vous codez : à ce niveau, comprendre le
mécanisme compte plus que le retrouver seul.

La démarche pour une étape :

1. Lire les consignes de l'étape dans `PLAN.md` : ce qu'il faut créer, les fonctions à chercher, les
   questions auxquelles il faut savoir répondre.
2. Copier le code de l'étape précédente dans le dossier de l'étape, rétablir le `README.md` de l'étape que la
   copie vient d'écraser, puis transformer le code :
   ```bash
   cp -r etapes/01-pages-classiques/. etapes/02-includes/
   git restore etapes/02-includes/README.md
   ```
3. Lancer `./verifier.sh NN` jusqu'à obtenir `=> OK`.
4. Lire le `README.md` de l'étape, comparer avec ce que vous avez fait.

La correction complète des quatorze étapes est distribuée par l'enseignant.

## Lancer une étape

Étapes 1 à 6 (les pages sont à la racine du dossier) :

```bash
cd etapes/03-env
cp .env.example .env        # à partir de l'étape 3
php -S localhost:8000
```

puis ouvrir http://localhost:8000/index.php.

Étapes 7 à 14 (front controller, seul `public/` est exposé) :

```bash
cd etapes/10-noyau
cp .env.example .env
php -S localhost:8000 -t public
```

puis ouvrir http://localhost:8000/. Les URLs deviennent `/login`, `/register`, `/logout`.

La base SQLite `database.sqlite` est créée automatiquement dans le dossier de l'étape, à la première requête
qui la touche (inscription ou connexion). La supprimer remet les données à zéro.

## Vérifier une étape

```bash
./verifier.sh 05          # une étape
for n in 01 02 03 04 05 06 07 08 09 10 11 12 13 14; do ./verifier.sh $n; done   # toutes
```

Le script démarre un serveur PHP sur le bon dossier, crée `.env` depuis `.env.example` si besoin, puis
enchaîne : accueil non connecté, inscription refusée puis acceptée puis doublon, connexion refusée puis
acceptée, accueil connecté, déconnexion, échappement HTML. À partir de l'étape 7 il vérifie aussi le 404,
l'inaccessibilité par URL de la base et du `.env`, et que `/login/` répond comme `/login`. Une base SQLite
qui apparaît dans `public/` est signalée : c'est un `DB_DSN` relatif résolu depuis le dossier courant du
serveur au lieu de la racine du projet (piste de l'étape 3). Le comportement attendu est **le même à toutes
les étapes** : c'est tout l'intérêt, on change la structure, pas ce que voit l'utilisateur. Seule entorse,
annoncée dans le PLAN : à l'étape 11, le `<h1>` de l'accueil devient « Accueil » au lieu de « Bienvenue ».

Sur un dossier qui ne contient pas encore de code, le script le dit et s'arrête.

Le script reconnaît les pages par les **textes** des pages de l'étape 1. Vous pouvez restructurer le code
autant que vous voulez, mais ces phrases doivent rester telles quelles dans le HTML produit, les champs des
formulaires garder leurs noms (`email`, `password`, `confirmation`), et deux réponses rester des redirections :

| Vérification | Le HTML doit contenir |
|---|---|
| Accueil non connecté | `pas connecté` |
| Mot de passe trop court | `8 caractères` |
| Inscription réussie | `Votre compte a été créé` |
| Email déjà pris | `existe déjà` |
| Mauvais mot de passe | `incorrect` |
| Accueil connecté | `Bonjour <strong>test@example.com</strong>` |
| Slash final (`/login/`, étapes 7+) | `Se connecter` |
| Connexion réussie | rien : une redirection `302` vers l'accueil |
| Déconnexion | rien : une redirection `302` vers l'accueil |

En cas d'échec, le message indique la phrase attendue ou le code HTTP attendu.

## Documents

| Fichier | Pour qui | Contenu |
|---|---|---|
| [`PLAN.md`](PLAN.md) | Élèves | Parcours guidé en 14 étapes : consignes, pistes, deux ou trois questions par étape. Pas de corrigé. |
| [`MVC.md`](MVC.md) | Fin de parcours | Diagrammes de l'architecture cible et trace complète d'une requête `POST /login`, avec le code du noyau. À ne pas ouvrir avant l'étape 10. |
| `etapes/*/README.md` | Élèves, après réflexion | Ce que chaque étape a changé et pourquoi, avec des extraits. À lire après avoir essayé. |
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
| 9 | `src/Core/` : `Request`, `Response` ; les contrôleurs reçoivent l'une et retournent l'autre, `render()` capture le HTML | Objets requête et réponse, contrôleur testable |
| 10 | `src/Core/` : `Router`, `View` et `views/layout.php`, `Env`, `Database` | Noyau réutilisable |
| 11 | `src/View/` : `Component` abstraite (`render()`, `e()`, `renderAll()`), `Layout`, `Form`, `Input`… ; les vues assemblent des objets, plus un `htmlspecialchars()` hors de `Component::e()` | Composants de vue, héritage, composition |
| 12 (bonus) | `ControllerInterface`, `AbstractController` (`verb()`, `path()`), `FormTrait`, un contrôleur par verbe/chemin, routeur sans table de routes | Interface, classe abstraite, trait |
| 13 (bonus) | `ControllerFinder` balaye `src/Controller/`, `Container` construit par réflexion, plus aucun contrôleur nommé dans `index.php` | Découverte automatique, injection par réflexion |
| 14 (bonus) | `ControllerCache` écrit le résultat du balayage et de la réflexion dans `cache/controleurs.php`, invalidé par `filemtime` | Cache d'un calcul déterministe |
