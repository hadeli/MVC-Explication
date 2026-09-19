# Du PHP classique au MVC : parcours guidé

Ce plan dit, pour chaque étape, **ce qu'il faut faire** et pose **deux ou trois questions** auxquelles vous
devez savoir répondre avant de passer à la suivante. Le pourquoi détaillé est dans le `README.md` de chaque
étape : lisez-le **après** avoir essayé, pour comparer avec ce que vous avez fait.

**Règles**

- Pas de Composer, pas de bibliothèque externe. Tout ce que vous écrivez, vous le comprenez.
- À la fin de chaque étape, le site fait exactement la même chose : inscription, connexion, accueil connecté,
  déconnexion. `./verifier.sh NN` doit afficher `=> OK`. Les phrases affichées à l'utilisateur (« Votre compte
  a été créé », « existe déjà », etc.) restent celles de l'étape 1 : la liste est dans le `README.md` racine.
- On change la structure, jamais le comportement. Si le site fait autre chose, ce n'est plus une refactorisation.
- Les étapes 1 à 11 sont le parcours attendu. Les étapes 12 à 14 sont un **bonus** : elles préparent les
  unités d'enseignement suivantes et se font si le temps le permet, README de l'étape ouvert.

**Méthode pour chaque étape**

1. Copier le dossier de l'étape précédente, puis rétablir le `README.md` de l'étape, que la copie a écrasé :
   ```bash
   cp -r etapes/01-pages-classiques/. etapes/02-includes/
   git restore etapes/02-includes/README.md
   ```
2. Transformer le code selon les consignes ci-dessous.
3. Lancer `./verifier.sh NN` jusqu'à `=> OK`.
4. Répondre aux questions, puis lire le `README.md` de l'étape.

**Lancer**

```bash
php -S localhost:8000                # étapes 1 à 6, depuis le dossier de l'étape
php -S localhost:8000 -t public      # étapes 7 à 14
```

Supprimer `database.sqlite` remet la base à zéro. À partir de l'étape 3, copier `.env.example` en `.env`.
Sous Windows, travaillez dans WSL : `verifier.sh` est un script Bash (voir le `README.md` racine).

---

## Étape 1 : lire le code existant

**Objectif** : comprendre ce que fait le site avant d'y toucher.

**À faire**

1. Lancer le site, faire le parcours complet à la main : inscription, connexion, accueil, déconnexion.
2. Lancer `./verifier.sh 01` et lire chaque ligne : c'est le contrat que toutes les étapes devront respecter.
3. Ouvrir `login.php` et `register.php` côte à côte. Surligner tout ce qui est identique ou presque.
4. Après vous être inscrit **à la main** (pas via le script, qui efface la base à la fin), taper
   `http://localhost:8000/database.sqlite` dans le navigateur.

**Questions**

1. Combien de lignes sont dupliquées entre les deux fichiers ? Quelle proportion cela représente ?
2. Pour changer le nom de la base, combien de fichiers faut-il modifier ? Qu'a montré le point 4 ?
3. Formulez en une phrase le problème de ce code, sans utiliser « propre » ni « mieux ». **Gardez cette
   phrase** : vous la relirez à la fin du parcours.

---

## Étape 2 : factoriser ce qui se répète

**Objectif** : chaque information n'existe qu'à un seul endroit.

**À faire**

1. Créer `includes/db.php` : la connexion PDO et le `CREATE TABLE`, qui définit `$pdo`. Pas de `?>` final.
2. Créer `includes/header.php` (doctype, `<head>`, `<nav>`, qui utilise une variable `$titre`) et
   `includes/footer.php`.
3. Dans les trois pages, remplacer le code dupliqué par `require __DIR__ . '/includes/...'`.
4. Laisser `session_start()` en tête de chaque page.

**Pistes** : `require` vs `include`, suffixe `_once` ; `__DIR__` plutôt qu'un chemin relatif. Pas de `?>` final
dans `db.php` : tout ce qui suit la balise fermante, même un retour à la ligne, partirait vers le navigateur
avant les en-têtes, et les redirections échoueraient.

**Questions**

1. Pourquoi `session_start()` ne peut-il pas aller dans `header.php` ?
2. En lisant `login.php`, comment sait-on que `$pdo` existe ? Est-ce un problème ?

**Vérifier** : `./verifier.sh 02`. Changer le nom de la base : un seul fichier modifié.

---

## Étape 3 : sortir les secrets du code

**Objectif** : la configuration propre à une machine n'est ni dans le code ni dans Git.

**À faire**

1. Créer `.env.example` (versionné) avec la ligne `DB_DSN=sqlite:database.sqlite`, puis le copier en `.env`.
   Le `.gitignore` racine ignore déjà `.env`, `database.sqlite` et `cache/`.
2. Créer `includes/env.php` avec une fonction `chargerEnv(string $chemin): void` qui lit le fichier ligne par
   ligne, ignore les lignes vides et celles qui commencent par `#`, coupe sur le **premier** `=`, retire les
   guillemets, et stocke dans `$_ENV` (et `putenv()`). Si le fichier manque : lever une exception dont le message dit de
   copier `.env.example`.
3. Ajouter une fonction `env(string $cle, ?string $defaut = null): ?string` qui lit `$_ENV`, puis `getenv()` :
   une variable définie par le système (`DB_DSN=… php -S …`) gagne sur le fichier.
4. `includes/db.php` lit `env('DB_DSN')`. Plus aucune valeur en dur dans le PHP.

**Pistes** : `file()` avec `FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES`, `explode('=', $ligne, 2)`,
`trim()`. Un chemin SQLite relatif doit être résolu par rapport au dossier du projet, pas au dossier courant du
serveur : à partir de l'étape 7, `verifier.sh` signale une base créée dans `public/`.

**Questions**

1. Pourquoi versionner `.env.example` et pas `.env` ? Effacer une valeur dans un commit suivant suffit-il ?
2. Fichier absent : valeurs par défaut silencieuses ou erreur claire ? Laquelle évite les pires surprises ?

**Vérifier** : `./verifier.sh 03`. `git status` ne montre pas `.env`. Supprimer `.env` : le message, dans le terminal du serveur, dit quoi faire.

---

## Étape 4 : séparer ce qu'on calcule de ce qu'on affiche

**Objectif** : les pages calculent, les vues affichent.

**À faire**

1. Créer `views/home.php`, `views/login.php`, `views/register.php` : uniquement le HTML des trois pages.
   Déplacer `header.php` et `footer.php` dans `views/partials/`.
2. Créer `includes/render.php` avec `render(string $vue, array $donnees = []): void`, qui rend les clés du
   tableau disponibles comme variables puis inclut `views/<vue>.php`.
3. Les pages à la racine ne contiennent plus **aucune** balise HTML : elles calculent, puis appellent
   `render('login', ['erreur' => $erreur, 'email' => $email])`.

**Règle à appliquer** : une vue ne lit jamais `$_POST`, `$_SESSION`, `$_GET` ni `$pdo`. Elle n'utilise que
les variables qu'on lui donne, avec `if`, `foreach` et `<?= htmlspecialchars(...) ?>`.

**Pistes** : `extract()`, et pourquoi il est dangereux sur un tableau venant de l'utilisateur.

**Questions**

1. Le HTML a-t-il besoin de savoir comment `$erreur` a été calculée ?
2. Vous venez de créer la « Vue » du MVC. Définissez-la en une phrase sans regarder de définition.

**Vérifier** : `./verifier.sh 04`. `grep -r '\$_' views/` ne renvoie rien.

---

## Étape 5 : isoler l'accès aux données

**Objectif** : tout le SQL au même endroit, derrière des méthodes qui parlent d'utilisateurs, pas de tables.

**À faire**

1. Créer `models/User.php` : une classe avec `id`, `email`, `passwordHash` en propriétés typées, et une
   méthode `verifierMotDePasse(string $motDePasse): bool` qui appelle `password_verify()`.
2. Créer `models/UserRepository.php` : le constructeur reçoit le `PDO`. Méthodes :
   `findByEmail(string $email): ?User`, `emailExists(string $email): bool`,
   `create(string $email, string $motDePasseClair): User`. C'est `create()` qui appelle `password_hash()`.
   Une méthode privée construit un `User` à partir d'une ligne SQL.
3. Les pages utilisent le repository. Plus aucun `SELECT` ni `INSERT` hors de `UserRepository.php`.

**Pistes** : `PDO::FETCH_ASSOC`, type de retour nullable `?User`, promotion de constructeur
(`public readonly int $id` dans les paramètres du constructeur), `final`.

**Questions**

1. Pourquoi mettre le hachage dans `create()` plutôt que dans la page ? Qu'est-ce que cela rend impossible ?
2. Que reste-t-il dans `login.php` ? Donnez un nom à ce rôle. Vous vérifierez à l'étape 8.

**Vérifier** : `./verifier.sh 05`. `grep -il 'select\|insert' *.php` ne liste aucune page.

---

## Étape 6 : charger les classes automatiquement

**Objectif** : plus jamais de `require` pour une classe.

**À faire**

1. Créer `autoload.php` : avec `spl_autoload_register()`, transformer un nom de classe `App\Model\User` en
   chemin `src/Model/User.php` et l'inclure s'il existe. Ne rien faire pour un nom qui ne commence pas par `App\`.
2. Déplacer `models/` vers `src/Model/`. Ajouter `namespace App\Model;` en tête de chaque classe, et
   `use PDO;` où c'est nécessaire.
3. Chaque page qui utilise une classe garde un seul `require __DIR__ . '/autoload.php'`, plus un
   `use App\Model\UserRepository;`. `index.php` n'a besoin ni de l'un ni de l'autre.

**Pistes** : `str_starts_with()`, `substr()`, `str_replace('\\', '/', ...)`, `is_file()`. Cette convention
s'appelle PSR-4. Sur Linux, la casse du nom de fichier compte.

**Questions**

1. Quand PHP appelle-t-il votre fonction ? Mettez un `echo` dedans et chargez l'accueil : combien d'appels ?
2. Pourquoi ne rien faire, plutôt que lever une erreur, pour une classe hors de `App\` ?

**Vérifier** : `./verifier.sh 06`. Créer une classe vide `App\Model\Test`, l'utiliser sans `require`.

---

## Étape 7 : un seul point d'entrée

**Objectif** : toutes les requêtes passent par un fichier unique, et seul ce fichier est accessible par URL.

**À faire**

1. Créer `public/index.php`. C'est le **seul** fichier PHP dans `public/`. Il charge l'autoloader et les
   includes, démarre la session, lit le chemin de l'URL et choisit quoi exécuter. Un `.htaccess` pour
   Apache est facultatif : `php -S` envoie tout seul vers `index.php` ce qui ne correspond à aucun fichier.
2. Déplacer les anciennes pages dans `actions/` (`home.php`, `login.php`, `register.php`, `logout.php`),
   sans `session_start()` ni `require` : le front controller l'a déjà fait.
3. Créer `config/routes.php` qui retourne un tableau `chemin => action` : `'/' => 'home'`,
   `'/login' => 'login'`, etc.
4. Chemin inconnu : `http_response_code(404)` et une vue `views/404.php`.
5. Mettre à jour les liens et `action=` des formulaires : `/login`, `/register`, `/logout`.
6. Lancer avec `php -S localhost:8000 -t public`.

**Pistes** : `parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)`, `rtrim('/')`. Le serveur intégré envoie
tout chemin qui ne correspond pas à un fichier vers `index.php`.

**Questions**

1. Retapez `/database.sqlite` et `/views/login.php` dans le navigateur. Que se passe-t-il maintenant ? Pourquoi ?
2. Ce fichier s'appelle « front controller ». Il ne contient aucune logique métier : que contrôle-t-il ?

**Vérifier** : `./verifier.sh 07` (il teste aussi le 404, l'accès par URL à la base et au `.env`, et que
`/login/` répond comme `/login`).

---

## Étape 8 : les contrôleurs

**Objectif** : chaque action devient une méthode d'une classe, qui reçoit ce dont elle a besoin.

**À faire**

1. Créer `src/Controller/HomeController.php` (méthode `index()`) et `src/Controller/AuthController.php`
   (méthodes `loginForm()`, `login()`, `registerForm()`, `register()`, `logout()`). Le constructeur
   d'`AuthController` reçoit le `UserRepository`. Supprimer `actions/`.
2. Réécrire `config/routes.php` en `[méthode HTTP, chemin, [classe, méthode]]` :
   `['POST', '/login', [AuthController::class, 'login']]`, etc.
3. Dans `public/index.php` : construire le repository une fois, parcourir les routes, comparer méthode et
   chemin, instancier le contrôleur et appeler la méthode dont le nom est dans une variable.

**Pistes** : `$_SERVER['REQUEST_METHOD']`, `$objet->$methode()`, `Classe::class`. Les deux contrôleurs n'ont
pas le même constructeur : un tableau `classe => fn() => new ...` dit comment construire chacun sans le
construire tout de suite, et la boucle n'appelle que la fonction de la route trouvée.

**Questions**

1. Trois façons pour un contrôleur d'obtenir le repository : le créer dans la méthode, dans le constructeur,
   ou le recevoir en paramètre du constructeur. Laquelle permet de le tester sans base de données ?
2. Décrivez à voix haute le chemin d'un `POST /login`, fichier par fichier, sans regarder le code.

**Vérifier** : `./verifier.sh 08`. `autoload.php` n'a pas eu besoin de changer.

---

## Étape 9 : la requête et la réponse deviennent des objets

**Objectif** : un contrôleur ne lit plus aucune superglobale et n'envoie rien lui-même. Il reçoit une
`Request`, retourne une `Response`, et devient appelable depuis un test.

**À faire**

1. Créer `src/Core/Request.php` : enveloppe `$_GET`, `$_POST`, `$_SESSION`, méthode et chemin. Une méthode
   statique `fromGlobals()`, deux propriétés publiques en lecture seule `method` et `path`, des méthodes
   `get()`, `post()`, `session()`, `setSession()`, `detruireSession()`. Plus rien ne lit `$_GET` dans ce site
   depuis l'étape 7 : écrivez `get()` pour la symétrie avec `post()`, elle servira à une future page.
2. Créer `src/Core/Response.php` : statut, corps, en-têtes ; une méthode statique `redirect(string $url)`
   (302 et en-tête `Location`) ; une méthode `send()` appelée **une seule fois**, à la fin de `index.php`.
3. `includes/render.php` : `render(string $vue, array $donnees = [], int $statut = 200): Response`. La
   fonction capture le HTML de la vue au lieu de l'afficher, et le range dans une `Response`. Les vues ne
   changent pas.
4. Les contrôleurs : chaque méthode reçoit une `Request` et retourne une `Response`. Plus de `$_POST`, de
   `$_SESSION`, de `header()` ni de `exit`. La méthode privée `redirect()` disparaît.
5. `public/index.php` : construire `Request::fromGlobals()`, comparer les routes à `$request->method` et
   `$request->path`, garder la `Response` du contrôleur (ou celle de la 404, avec le statut 404 passé à
   `render()`), et appeler `send()` tout à la fin.

**Pistes** : `public static function fromGlobals(): self` s'appelle sur la classe, `Request::fromGlobals()`, sans
objet ; `self` est le type de retour « une instance de cette classe ». Le type `mixed` accepte n'importe quelle
valeur, utile pour `session()` dont le contenu n'est pas connu d'avance.

**Piste indispensable pour `render()`** : un `require` envoie son HTML directement au navigateur. Pour le
récupérer dans une variable, cherchez `ob_start()` et `ob_get_clean()` : tout ce qui est affiché entre les
deux est mis de côté au lieu d'être envoyé.

**Questions**

1. Retirez le `return` devant un `render(...)` dans un contrôleur. Que dit PHP, et à quel moment ?
2. Ajoutez un `echo 'x';` au début de `login()`, puis connectez-vous avec un bon mot de passe en regardant la
   réponse avec `curl -i -d 'email=…&password=…' http://localhost:8000/login`. Où est passé le `x` ? Pourquoi la
   redirection marche-t-elle quand même avec `php -S`, et que se passerait-il sur un serveur qui envoie la
   sortie au fil de l'eau ? Pourquoi `send()` doit-il être le dernier appel du script ?
3. `grep -rn '\$_' src/ includes/ public/` : quels fichiers lisent encore une superglobale ? Lequel devra
   encore bouger ?

**Vérifier** : `./verifier.sh 09` doit passer, mais il passait déjà à l'étape 8 : il ne voit pas la différence.
Le vrai test de cette étape est un script `test.php` à la racine de l'étape, lancé avec `php test.php`, qui
appelle un contrôleur **sans serveur web** :

```php
require __DIR__ . '/autoload.php';
require __DIR__ . '/includes/render.php';
// … un PDO SQLite en mémoire (`sqlite::memory:`) avec la table users, un UserRepository, un AuthController
$response = $controleur->login(new Request('POST', '/login', [], ['email' => 'a@b.fr', 'password' => 'x'], []));
echo $response->statut;   // 200, et $response->corps contient « incorrect »
```

S'il reste un `exit`, un `header()` ou un `$_POST` dans un contrôleur, ce script ne peut pas aboutir.

---

## Étape 10 : extraire le noyau

**Objectif** : séparer ce qui est propre à ce site de ce qui servirait à n'importe quel site.

**À faire**

1. Créer `src/Core/View.php` : reçoit le dossier des vues et un tableau de données partagées par toutes les
   vues (l'utilisateur connecté) ; `render(string $vue, array $donnees = [], int $statut = 200): Response`.
   Elle exécute la vue, capture son HTML, puis exécute `views/layout.php` en lui donnant ce HTML dans `$contenu`.
   Supprimer `views/partials/` et `includes/render.php` : le layout et la classe les remplacent. Attention : le
   routeur rend `404.php` avec `chemin` pour seule donnée, donc sans `titre`. Le layout doit prévoir un titre par
   défaut (`$titre ?? …`), sinon la page 404 produit un avertissement « Undefined variable » que `verifier.sh`
   compte comme un échec.
2. Créer `src/Core/Router.php` : reçoit les routes, un tableau `classe => fabrique` et la `View` ;
   `dispatch(Request): Response`. La boucle d'`index.php` et la 404 déménagent ici.
3. Créer `src/Core/Env.php` (`charger()`, `get()`, statiques) et `src/Core/Database.php`
   (`connexion(string $racine): PDO`) à partir des includes. Supprimer `includes/`.
4. Les contrôleurs reçoivent la `View` dans leur constructeur et appellent `$this->view->render(...)`. Le
   titre de la page devient une donnée (`'titre' => 'Connexion'`) au lieu d'une variable posée dans la vue.
5. `public/index.php` ne fait plus que câbler : `Env::charger()`, session, repository, `View`, `Router`, puis
   `$router->dispatch(Request::fromGlobals())->send()`.

**Pistes** : la capture de l'étape 9 sert deux fois, une pour la vue, une pour le layout ; `$donnees + ['contenu' => $contenu]`.
Une méthode `static` s'appelle sur la classe (`Env::get('DB_DSN')`) : c'est la fonction `env()` de l'étape 3,
rangée dans une classe pour être autoloadée.

**Questions**

1. Dans quel ordre exécuter la vue et le layout ? Pourquoi pas l'inverse ?
2. Si vous démarriez un blog demain, quels fichiers copieriez-vous tels quels ? Ce sont eux, le noyau.
3. Le MVC a un coût : plus de fichiers, plus d'indirections. Dans quel type de projet ne vaut-il pas la peine ?

**Vérifier** : `./verifier.sh 10`. Le script de test de l'étape 9 fonctionne encore, sans le `require` de
`render.php` (le fichier n'existe plus) et avec une `View` en plus dans le constructeur. `grep -rn '\$_' src/` ne renvoie que `Request.php` et `Env.php`.

---

## Étape 11 : des composants pour les vues

**Objectif** : plus un seul `htmlspecialchars()` dans les vues. L'échappement est fait par des objets qui
produisent le HTML, et le type d'une valeur dit si elle est du texte ou du HTML. Côté PHP : héritage, classe
abstraite, `__toString()`.

**À faire**

1. Créer `src/View/Component.php`, classe **abstraite** : `abstract public function render(): string`,
   `__toString()` qui appelle `render()`, `protected function e(string $texte): string` (le seul
   `htmlspecialchars()` du projet, avec `ENT_QUOTES`), `protected function renderAll(array $enfants): string`
   qui concatène les enfants **sans séparateur** : une `string` est échappée avec `e()`, un `Component` est
   rendu tel quel. Partout ci-dessous, `array $children` est un tableau de `Component|string`.
2. Créer les composants concrets dans `src/View/`, tous `final` et héritant de `Component` :
   - `Layout(string $title, ?array $user, array $children)` : le document complet. `$user` est le tableau
     `utilisateurConnecte` partagé par `View` (ou `null`) ; la navigation est celle de `views/layout.php` ;
     le titre sert au `<title>` et à un `<h1>` ; les enfants viennent ensuite. Conséquence assumée : l'accueil
     affiche « Accueil » en `<h1>` au lieu de « Bienvenue », seule différence visible de tout le parcours ;
   - `Form(string $action, array $children, string $method = 'post')` ;
   - `Input(string $name, string $label, string $type = 'text', string $value = '')` : le bloc
     `<p><label>…</label><br><input …></p>` de l'étape 10, avec `id` égal à `name` et `required` ;
   - `Button(string $label)` : `<button type="submit">` ;
   - `Alert(array $messages)` : une liste `<ul>`, ou rien si le tableau est vide ;
   - `Paragraph(array $children)`, `Link(string $href, string $text)`, `Strong(string $text)`.
3. Réécrire `views/home.php`, `login.php`, `register.php`, `404.php` : chacune commence par les `use App\View\…`
   dont elle a besoin, puis fait `echo new Layout(...)` avec les données reçues du contrôleur, et ne contient
   plus aucune balise. La page de connexion a une seule `$erreur`, parfois `null` : `new Alert($erreur === null ? [] : [$erreur])`.
   Le routeur ne passe que `$chemin` à `404.php` : cette vue fixe son propre titre. Supprimer `views/layout.php`.
4. `View::render()` ne fait plus que capturer la vue et l'emballer dans une `Response`. Les contrôleurs et
   `public/index.php` ne changent pas.

**Pistes** : `abstract class`, `extends`, `__toString()`, `instanceof`. Pour écrire du HTML sur plusieurs
lignes dans `render()`, cherchez la syntaxe *heredoc* (`<<<HTML … HTML;`) et l'insertion `{$variable}`. La
marque de fin `HTML;` peut être indentée, mais aucune ligne du bloc ne doit l'être moins qu'elle : c'est une
erreur de syntaxe. Préparez des variables locales **déjà échappées** en haut de la méthode et n'insérez que
celles-là. Si vous voyez `Component::e(): Argument #1 ($texte) must be of type string, null given`, ce n'est pas
un bug du composant : une donnée manque dans le tableau passé par le contrôleur.

**Questions**

1. Pourquoi les enfants sont-ils `Component|string` et non seulement `string` ? Que perdrait-on si `Layout`
   acceptait une chaîne de HTML déjà prêt ?
2. `new Paragraph([(string) new Link('/', 'Accueil')])` : qu'affiche la page, et pourquoi ?
3. Twig et Blade proposent une syntaxe propre (`{{ email }}`) compilée en PHP. Qu'a-t-on gagné en restant
   en PHP pur ? Qu'a-t-on perdu ?

**Vérifier** : `./verifier.sh 11`. Dans le formulaire de connexion, tapez `"><b>gras</b>` comme email : rien ne
doit apparaître en gras et le champ doit réafficher la saisie entière. `grep -r htmlspecialchars src views`
doit renvoyer une seule ligne de code.

---

## Étapes 12 à 14 : bonus

Ce qui suit n'est pas nécessaire pour structurer le site de votre SAÉ : l'étape 11 vous donne déjà tout.
Ces trois étapes introduisent les mécanismes que les frameworks (Symfony, Laravel) utilisent en interne :
contrat par interface, injection de dépendances automatique, cache de configuration. Vous les reverrez dans
les unités d'enseignement suivantes ; les avoir écrits une fois à la main rendra ces cours plus concrets.
Ici, lisez le `README.md` de l'étape **pendant** que vous codez, pas après : le but est de comprendre le
mécanisme, pas de le réinventer. Si vous vous arrêtez à l'étape 11, passez directement à « Pour finir ».

---

## Étape 12 : une interface pour les contrôleurs

**Objectif** : le routeur ne connaît plus aucun contrôleur concret, seulement un contrat.

**À faire**

1. Créer `src/Core/ControllerInterface.php` avec deux méthodes :
   `public static function support(Request $request): bool` et `public function handle(Request $request): Response`.
2. Découper `AuthController` en une classe par couple verbe/chemin : `LoginFormController`, `LoginController`,
   `RegisterFormController`, `RegisterController`, `LogoutController` ; `HomeController`, qui existe déjà, suit
   le même modèle. Chacune implémente
   l'interface : son `support()` compare le verbe et le chemin, son `handle()` est l'ancienne méthode. Chacune ne
   reçoit dans son constructeur que ce qu'elle utilise. Vous écrirez six fois presque la même ligne dans
   `support()` : c'est voulu, le point 4 la factorise.
3. Réécrire `Router` : il reçoit `[classe => fabrique]`, vérifie au démarrage que chaque classe implémente
   l'interface, puis pour chaque requête appelle `$classe::support($request)` et n'instancie **que** la
   première qui répond `true`. Supprimer `config/`.
4. Créer `src/Core/AbstractController.php` qui implémente `support()` à partir de deux méthodes abstraites
   statiques `verb()` et `path()`. Faire hériter les six contrôleurs. Le routeur ne change pas.
5. Créer `src/Controller/FormTrait.php` avec `lireEmail(Request $request): string` (l'email saisi, `trim()`é) et
   `rendreFormulaire(string $vue, string $titre, string $email, array $donnees = []): Response`, qui appelle
   `$this->view->render()` avec le titre, l'email et les données propres au formulaire. Le trait est utilisé
   par les quatre contrôleurs de `/login` et `/register`, et suppose que la classe hôte a une propriété `$view`.

**Pistes** : `is_a($classe, Interface::class, true)`, `$classe::support()`, `static::path()` et non
`self::path()` (« late static binding »), `trait` et `use` dans une classe.

**Questions**

1. Retirez `extends AbstractController` d'un contrôleur sans toucher à ses méthodes. Quand la plainte
   arrive-t-elle, et qui se plaint : le langage ou le routeur ? Remettez-le, puis supprimez `handle()` du
   même contrôleur : mêmes questions.
2. Remplacez `static::` par `self::` dans `AbstractController::support()`. Que se passe-t-il ?
3. Interface, classe abstraite, trait : en une phrase chacun, qu'est-ce qu'il partage, et avec qui ?

**Vérifier** : `./verifier.sh 12`. Testez le routeur en ligne de commande avec un contrôleur factice qui
répond `ok` sur `/test`, sans base ni serveur.

---

## Étape 13 : un routeur autonome

**Objectif** : déposer un fichier dans `src/Controller/` suffit pour qu'il réponde. `index.php` ne nomme
plus aucun contrôleur.

**À faire**

1. Créer `src/Core/ControllerFinder.php` avec `trouver(string $dossier, string $namespace): array` :
   pour chaque `*.php` du dossier, construire le nom de classe, garder celles qui implémentent l'interface
   et sont instanciables. `FormTrait.php` doit être ignoré sans erreur.
2. Créer `src/Core/Container.php` : le constructeur reçoit `[classe => objet déjà construit]` (la `View`,
   le `UserRepository`). `creer(string $classe): object` lit les types des paramètres du constructeur avec
   la réflexion et construit récursivement. Un paramètre `string` ou sans type : exception avec un message clair.
3. `Router` reçoit la liste de noms de classes, le conteneur et la vue, et délègue le `new` au conteneur.
4. `public/index.php` appelle le finder et construit le conteneur. Les contrôleurs ne changent pas.

**Pistes** : `glob()`, `basename($f, '.php')`, `ReflectionClass::getConstructor()`,
`ReflectionParameter::getType()`, `ReflectionNamedType::isBuiltin()`, `new $classe(...$arguments)`.

**Questions**

1. Créez `ProfilController.php` qui répond `GET /profil`. Combien de fichiers avez-vous modifiés ?
2. Renommez `LoginController.php` en `ZLoginController.php` sans toucher à la classe. Que se passe-t-il ?
3. `src/Controller/` est devenu de la configuration. Dans quels cas préféreriez-vous une liste explicite ?

**Vérifier** : `./verifier.sh 13`.

---

## Étape 14 : mettre le noyau en cache

**Objectif** : ne pas refaire à chaque requête un calcul qui ne dépend que des fichiers.

**À faire**

1. Rendre l'analyse d'un constructeur accessible sans construire d'objet : `Container::analyser(string $classe): array`,
   publique et statique, qui retourne la liste des types des paramètres. `creer()` l'utilise.
2. Créer `src/Core/ControllerCache.php` : reçoit le dossier, le namespace et le chemin du fichier de cache.
   `charger(): array` retourne `[classe => types du constructeur]`. Si `cache/controleurs.php` existe et est
   **strictement** plus récent que le dossier **et** que chaque `*.php` du dossier, le relire avec `require`.
   Sinon, appeler le finder et `analyser()`, écrire le résultat avec `var_export()`, et le retourner.
3. `Container` accepte ces plans en second argument et ne fait plus de réflexion pour une classe connue.
4. `public/index.php` n'appelle plus le finder : il appelle `charger()`, donne les plans au conteneur et les
   noms de classes au routeur.

**Pistes** : `filemtime()` d'un dossier change quand on y ajoute, supprime ou renomme un fichier, **pas**
quand on modifie le contenu d'un fichier : d'où la double comparaison. `filemtime()` est à la seconde près :
une date égale à celle du cache doit compter comme périmée. `var_export($plans, true)` produit du PHP
relisible sans rien parser.

**Questions**

1. Ajoutez un `echo` dans le finder. Rechargez trois fois, puis modifiez un constructeur, ajoutez un contrôleur,
   supprimez-le : à quels rechargements l'affichage revient-il, et laquelle des deux dates l'a déclenché ?
2. Lisez `cache/controleurs.php` à côté du `config/routes.php` de l'étape 11. Qu'ont-ils en commun ? Que
   manque-t-il au cache pour éviter de charger chaque contrôleur jusqu'à celui qui répond ?
3. Pourrait-on mettre en cache de la même façon le HTML produit par `Layout` à l'étape 11 ? À quelle
   condition un calcul peut-il être mis en cache ?

**Vérifier** : `./verifier.sh 14` (il vide `cache/` avant). Lire `cache/controleurs.php` après une requête.

---

## Pour finir

Que vous vous soyez arrêté à l'étape 11 ou que vous soyez allé jusqu'à la 14, reprenez la phrase écrite à la
question 3 de l'étape 1. Le problème est-il résolu ? Réécrivez-la avec ce que vous savez maintenant. Si les
deux versions sont identiques, relisez les questions des étapes 4, 5 et 8.
