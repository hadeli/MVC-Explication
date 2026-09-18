# Du PHP classique au MVC : parcours guidé

Ce parcours ne vous donnera pas les réponses. Il vous pose des questions. À chaque étape, prenez le temps
d'y répondre par écrit ou à l'oral avant de toucher au code. Si une question vous semble évidente, méfiez-vous :
c'est souvent là que se cache la compréhension réelle.

**Règles du jeu**

- Pas de Composer, pas de bibliothèque externe. Tout ce que vous écrivez, vous le comprenez.
- À la fin de chaque étape, le site doit fonctionner exactement comme avant : inscription, connexion,
  accueil connecté, déconnexion. Si le comportement change, vous avez fait autre chose qu'une refactorisation.
- Avant chaque étape, notez ce qui vous gêne dans le code actuel. Après, relisez vos notes : le problème
  a-t-il disparu, s'est-il déplacé, ou en avez-vous créé un nouveau ?

**Comment lancer le projet**

```bash
php -S localhost:8000
```

puis ouvrir http://localhost:8000/index.php. Supprimer `database.sqlite` remet la base à zéro.

---

## Étape 1 : lire le code existant

Vous disposez de trois fichiers : `index.php`, `login.php`, `register.php`. Ne modifiez rien pour l'instant.

**Observer**

1. Ouvrez `login.php` et `register.php` côte à côte. Surlignez tout ce qui est identique ou presque.
   Combien de lignes avez-vous surlignées ? Quelle proportion du fichier cela représente-t-il ?
2. Dans `register.php`, tracez une ligne horizontale à l'endroit où, selon vous, « le PHP s'arrête et le HTML
   commence ». Est-ce vraiment une frontière nette ? Combien de fois la franchissez-vous dans le fichier ?
3. Listez tout ce que fait `login.php`, verbe par verbe : « démarre la session », « se connecte à la base »…
   Combien de responsabilités différentes trouvez-vous dans un seul fichier ?

**Diagnostiquer**

4. On vous demande de changer le nom de la base de données. Combien de fichiers devez-vous modifier ?
   Et si vous en oubliez un, comment vous en rendrez-vous compte ?
5. Un graphiste doit refaire le menu de navigation. Dans quels fichiers doit-il intervenir ? Quel risque
   prend-il en touchant à ces fichiers ?
6. Que se passe-t-il si quelqu'un tape l'URL `http://localhost:8000/database.sqlite` dans son navigateur ?
   Essayez. Qu'en concluez-vous sur ce qui est exposé ?
7. Si vous deviez ajouter une page « profil », que feriez-vous concrètement ? Quelles lignes copieriez-vous ?
   Que ressentez-vous à l'idée de le faire une dixième fois ?

**Prendre du recul**

8. Ce code fonctionne. Alors pourquoi voudrait-on le changer ? Formulez le problème en une phrase qui
   ne contient pas le mot « propre » ni le mot « mieux ».

---

## Étape 2 : factoriser ce qui se répète

**Concevoir**

1. Reprenez vos surlignages de l'étape 1. Regroupez les lignes identiques en familles. Combien de familles
   trouvez-vous ? Donnez un nom à chacune.
2. PHP permet d'inclure un fichier dans un autre. Connaissez-vous les quatre instructions qui le permettent ?
   Quelle différence entre `include` et `require` ? Entre `require` et `require_once` ? Dans quel cas la
   différence devient-elle importante ici ?
3. Si vous mettez la connexion à la base dans un fichier séparé, comment la page qui l'inclut récupère-t-elle
   la variable `$pdo` ? Est-ce évident en lisant la page ? Est-ce un problème ?
4. L'appel à `session_start()` doit-il aller dans le fichier d'en-tête ou rester dans chaque page ?
   Que se passe-t-il s'il est appelé deux fois ? Et s'il est appelé après qu'un premier caractère HTML a été envoyé ?

**Réaliser**

5. Créez un dossier `includes/` et déplacez-y les familles que vous avez identifiées. Décidez vous-même
   des noms de fichiers. Justifiez chaque nom.

**Vérifier**

6. Refaites le parcours complet. Puis changez le nom de la base de données : combien de fichiers avez-vous
   modifiés cette fois ?
7. Ouvrez `includes/header.php` seul dans le navigateur. Que voyez-vous ? Est-ce normal ? Est-ce souhaitable ?

**Prendre du recul**

8. Vous avez supprimé la duplication. Relisez `register.php` : la ligne « où le PHP s'arrête » est-elle
   plus nette qu'avant ? Qu'avez-vous résolu, et qu'avez-vous laissé intact ?

---

## Étape 3 : sortir les secrets du code

**Observer**

1. Ouvrez `includes/db.php`. Que contient-il qui n'a rien à voir avec du code ? Aujourd'hui c'est un simple
   chemin vers un fichier SQLite. Imaginez la même ligne avec un hôte MySQL, un utilisateur et un mot de passe.
2. Le projet est partagé sur un dépôt Git avec toute la classe. Qui peut lire ce fichier ? Qui pourrait le lire
   dans un an, si le dépôt devient public ? Effacer la ligne dans un commit suivant suffit-il ?
3. Votre camarade travaille sur Windows avec MySQL, vous sur Linux avec SQLite. Comment faire tourner le même
   code sur les deux machines sans que chacun modifie `db.php` et se retrouve avec un conflit à chaque `git pull` ?

**Concevoir**

4. On veut séparer ce qui est du **code** (identique pour tout le monde) de ce qui est de la **configuration**
   (propre à chaque machine). Listez tout ce qui, dans le projet, relève de la configuration. Y a-t-il
   autre chose que la base de données ?
5. Un fichier `.env` à la racine contient des lignes `CLE=valeur`. Il n'est pas versionné. Comment le code
   sait-il alors quelles clés il doit attendre ? Que fournir au camarade qui clone le projet pour la première fois ?
   Cherchez ce qu'est un fichier `.env.example`.
6. Il faut lire ce fichier et rendre ses valeurs accessibles au code. Écrivez d'abord la signature de la fonction
   qui s'en charge. Que retourne-t-elle ? Où stocker les valeurs : dans un tableau, dans `$_ENV`, via `putenv()` ?
   Cherchez la différence entre ces options.
7. Écrivez sur papier ce que doit faire votre lecteur pour chacune de ces lignes :
   ```
   # ceci est un commentaire
   DB_DSN=sqlite:database.sqlite

   DB_PASSWORD=
   APP_NAME="Mon appli"
   ```
   Que faire d'une ligne sans `=` ? D'une clé déjà définie par le système ?
8. Que doit-il se passer si le fichier `.env` est absent ? Continuer avec des valeurs par défaut, ou s'arrêter
   avec un message clair ? Quelle option évite les pires surprises en production ?

**Réaliser**

9. Créez `.env.example` (versionné), `.env` (ignoré par Git), et un fichier `includes/env.php` qui charge les
   variables. `includes/db.php` ne doit plus contenir aucune valeur en dur.

**Vérifier**

10. Lancez `git status`. Le fichier `.env` apparaît-il ? S'il apparaît, corrigez avant de continuer.
11. Supprimez `.env` et rechargez la page. Que se passe-t-il ? Le message vous dit-il quoi faire ?
12. Changez le chemin de la base dans `.env` uniquement. Le site utilise-t-il bien la nouvelle base, sans
    qu'aucun fichier PHP n'ait été modifié ?

**Prendre du recul**

13. La règle « la configuration vient de l'environnement, jamais du code » a un nom et fait partie d'une liste
    de douze principes pour les applications web. Trouvez-la. Quels autres principes de cette liste
    votre projet respecte-t-il déjà, sans le savoir ?

---

## Étape 4 : séparer ce qu'on calcule de ce qu'on affiche

**Observer**

1. Dans `register.php`, listez toutes les variables que le HTML utilise. D'où viennent-elles ?
   Le HTML a-t-il besoin de savoir comment elles ont été calculées ?
2. Le HTML de `register.php` accède-t-il directement à `$_POST`, `$_SESSION` ou `$pdo` ? Si oui, où ?
   Pourquoi cela pourrait-il être gênant ?

**Concevoir**

3. Imaginez que le HTML soit dans un fichier séparé, disons `views/register.php`. Quelles informations
   doit-on lui transmettre, et sous quelle forme ? Une variable par donnée, ou un tableau unique ?
4. Vous voulez écrire une fonction `render()` qui affiche une vue en lui donnant des données. Écrivez sa
   signature avant son corps. Que prend-elle en paramètre ? Que retourne-t-elle, si elle retourne quelque chose ?
5. Cherchez ce que fait la fonction `extract()`. Que vous permet-elle ici ? Quel danger présente-t-elle
   si le tableau qu'on lui donne vient de l'utilisateur ?
6. Le fichier de vue peut-il encore contenir du PHP ? Lequel est acceptable, lequel ne l'est plus ?
   Rédigez une règle en une phrase que toute votre équipe pourrait appliquer.

**Réaliser**

7. Créez `views/`, déplacez-y le HTML des trois pages, écrivez `render()`. Chaque page à la racine ne doit
   plus contenir aucune balise HTML.

**Vérifier**

8. Ouvrez `views/register.php` et cherchez `$_`. Trouvez-vous quelque chose ? Si oui, votre règle de la
   question 6 est-elle respectée ?
9. Demandez à un camarade de modifier uniquement l'apparence du formulaire de connexion. A-t-il eu besoin
   d'ouvrir un fichier contenant du SQL ?

**Prendre du recul**

10. Vous venez de créer ce que le MVC appelle la « Vue ». Sans regarder de définition, écrivez la vôtre.
    Comparez ensuite avec une définition trouvée en ligne : qu'aviez-vous omis ?

---

## Étape 5 : isoler l'accès aux données

**Observer**

1. Cherchez toutes les requêtes SQL du projet. Combien y en a-t-il ? Dans combien de fichiers ?
2. La table `users` est créée avec `CREATE TABLE IF NOT EXISTS`. Où ? Combien de fois cette instruction
   s'exécute-t-elle par visite ? Est-ce raisonnable ?

**Concevoir**

3. Si vous deviez décrire à un collègue « ce qu'on peut faire avec les utilisateurs » sans parler de SQL,
   quels verbes utiliseriez-vous ? Ces verbes deviendront des méthodes.
4. Ces méthodes doivent-elles être des fonctions libres ou les méthodes d'une classe ? Qu'apporte la classe ?
   Que doit-elle recevoir dans son constructeur pour fonctionner ?
5. `findByEmail()` doit retourner un utilisateur. Sous quelle forme : un tableau associatif, ou un objet
   `User` ? Quels avantages voyez-vous à l'objet ? Quels inconvénients ?
6. Le hachage du mot de passe appartient-il au modèle, à la page, ou à autre chose ? Argumentez.
   Y a-t-il une réponse qui rendrait impossible d'enregistrer par erreur un mot de passe en clair ?

**Réaliser**

7. Créez `models/User.php` et `models/UserRepository.php`. Plus aucune page ne doit contenir le mot `SELECT`
   ni `INSERT`.

**Vérifier**

8. Relisez `login.php`. Combien de lignes reste-t-il ? Pouvez-vous le lire à voix haute comme une histoire
   sans mentionner de détail technique ?
9. Le SQL utilisé est-il spécifique à SQLite ? Si vous deviez passer sur MySQL, quels fichiers changeraient ?

**Prendre du recul**

10. Vous avez maintenant une Vue et un Modèle. Qu'est-ce qui reste dans `login.php` ? Trouvez un nom
    pour ce rôle. Ne cherchez pas encore la réponse : vous la vérifierez à l'étape 8.

---

## Étape 6 : charger les classes automatiquement

**Observer**

1. Comptez les `require` en tête de vos pages. Que se passe-t-il si vous ajoutez une classe et oubliez
   de l'inclure dans une page ? À quel moment l'erreur apparaît-elle : à l'écriture, ou à l'exécution ?
2. L'ordre des `require` a-t-il une importance ? Construisez un cas où il en a une.

**Concevoir**

3. Cherchez la documentation de `spl_autoload_register`. Quand PHP appelle-t-il la fonction qu'on lui donne ?
   Que reçoit-elle en paramètre ? Que doit-elle faire ? Que se passe-t-il si elle ne fait rien ?
4. Pour trouver le fichier d'une classe à partir de son nom, il faut une convention. Formulez la vôtre.
   Est-elle valable pour cent classes ? Pour des classes de deux dossiers différents ?
5. Cherchez ce qu'est un `namespace` en PHP. Quel problème résout-il ? En quoi peut-il aider votre autoloader
   à savoir dans quel dossier chercher ?
6. Voici une convention utilisée dans presque tout l'écosystème PHP : le namespace `App\Model\UserRepository`
   correspond au fichier `src/Model/UserRepository.php`. Écrivez sur papier les transformations de chaîne
   nécessaires pour passer de l'un à l'autre. Cette convention porte un nom : trouvez-le.
7. Votre autoloader reçoit un nom de classe qui ne commence pas par `App\`. Doit-il lever une erreur,
   ne rien faire, ou autre chose ? Pourquoi ?

**Réaliser**

8. Écrivez `autoload.php` en deux temps : d'abord sans namespace, puis avec. Déplacez `models/` vers
   `src/Model/`. Chaque page ne doit plus contenir qu'un seul `require` pour le chargement des classes.

**Vérifier**

9. Ajoutez une classe vide `App\Model\Test`, utilisez-la dans une page sans ajouter de `require`.
   Fonctionne-t-elle ? Supprimez ensuite le fichier mais gardez l'utilisation : quel message d'erreur
   obtenez-vous ? Est-il clair ? Sinon, comment votre autoloader pourrait-il l'améliorer ?
10. Placez un `echo` dans votre autoloader. Chargez la page d'accueil. Combien de fois s'affiche-t-il ?
    Que vous apprend ce chiffre sur le moment où PHP charge les fichiers ?

**Prendre du recul**

11. L'autoloader est une convention plus qu'un programme. Pourquoi une convention partagée par tous les
    développeurs PHP a-t-elle plus de valeur qu'une convention parfaite mais personnelle ?

---

## Étape 7 : un seul point d'entrée

**Observer**

1. Aujourd'hui, une URL correspond à un fichier. Listez tout ce que cela implique : pour la sécurité,
   pour les URLs, pour le code commun à toutes les pages.
2. Retournez à la question 6 de l'étape 1. Le problème est-il résolu ? Que faudrait-il pour qu'il le soit ?

**Concevoir**

3. Imaginez que toutes les requêtes arrivent dans un seul fichier. Comment ce fichier sait-il quelle page
   afficher ? Listez au moins deux sources d'information possibles dans la requête HTTP.
4. Cherchez `$_SERVER['REQUEST_URI']`. Que contient-il pour `/login?retour=accueil` ? Comment en extraire
   uniquement le chemin ?
5. Le serveur web doit envoyer toutes les URLs vers votre fichier unique. Cherchez comment faire avec le
   serveur intégré de PHP (`php -S`) et son option `-t`. Que doit contenir le dossier qu'on lui indique ?
   Que ne doit-il surtout pas contenir ?
6. Que doit-il se passer pour une URL qui ne correspond à rien ? Quel code HTTP ? Qui décide ?
7. Où doivent maintenant vivre `session_start()` et le chargement de l'autoloader ? Combien de fois ?

**Réaliser**

8. Créez `public/index.php`. Déplacez les anciennes pages hors de `public/`. Lancez
   `php -S localhost:8000 -t public` avec un fichier de routage si nécessaire. Les URLs deviennent
   `/`, `/login`, `/register`, `/logout`.

**Vérifier**

9. Tentez à nouveau d'accéder à la base de données ou à une vue depuis le navigateur. Résultat ?
10. Ajoutez une page « à propos » qui affiche un texte fixe. Combien de fichiers avez-vous créés ou modifiés ?
    Comparez avec votre réponse à la question 7 de l'étape 1.

**Prendre du recul**

11. Ce fichier unique s'appelle un « front controller ». Il ne contrôle pourtant rien de métier.
    Que contrôle-t-il exactement ? Pourquoi ce nom ?

---

## Étape 8 : les contrôleurs

**Observer**

1. Ouvrez vos anciens fichiers `login.php` et `register.php`. Ils ont maintenant quelques dizaines de lignes.
   Que font-ils encore ? Ont-ils quelque chose en commun dans leur déroulement ?
2. `login.php` gère à la fois l'affichage du formulaire (GET) et son traitement (POST). Est-ce une ou deux
   responsabilités ? Comment le routeur pourrait-il les distinguer ?

**Concevoir**

3. Regroupez vos actions par thème. Quels regroupements obtenez-vous ? Chacun deviendra une classe.
4. Une méthode de contrôleur a besoin du `UserRepository`. Trois options : le créer à l'intérieur de la méthode,
   le créer dans le constructeur, le recevoir en paramètre du constructeur. Listez un avantage et un inconvénient
   de chaque option. Laquelle rend le contrôleur testable sans base de données ?
5. Qui doit créer les contrôleurs et leur passer leurs dépendances ? Où ce code doit-il vivre ?
6. Le routeur associait un chemin à un fichier. Il doit maintenant associer un chemin et une méthode HTTP
   à une méthode d'une classe. Sous quelle forme représenter cette association dans un tableau PHP ?
   Comment appeler une méthode dont on ne connaît le nom qu'à l'exécution ?

**Réaliser**

7. Créez `src/Controller/AuthController.php` et `src/Controller/HomeController.php`. Les anciens fichiers
   d'action disparaissent. Votre autoloader de l'étape 6 ne doit pas avoir besoin d'être modifié.

**Vérifier**

8. Décrivez à voix haute le chemin d'une requête `POST /login` depuis le navigateur jusqu'au HTML renvoyé.
   Nommez chaque fichier traversé, dans l'ordre. Faites-le sans regarder le code.
9. Retournez à la question 10 de l'étape 5. Le nom que vous aviez proposé correspond-il ?

**Prendre du recul**

10. Un contrôleur qui contient une requête SQL, ou une vue qui lit `$_POST` : ce sont deux erreurs classiques.
    Pourquoi sont-elles tentantes ? Quel garde-fou pourriez-vous mettre en place pour les repérer ?

---

## Étape 9 : extraire le noyau

**Observer**

1. Dans `public/index.php` et vos contrôleurs, cherchez tout ce qui ne parle pas d'utilisateurs, de connexion
   ou d'inscription : lecture de l'URL, `header('Location: …')`, `http_response_code()`, appel de `render()`.
   Ce code appartient-il à votre application, ou à n'importe quelle application ?
2. Si vous démarriez demain un projet de blog, quels fichiers du projet actuel copieriez-vous tels quels ?

**Concevoir**

3. Vos contrôleurs lisent `$_POST` et `$_SERVER` directement. Qu'est-ce qui les empêche d'être testés sans
   navigateur ? Quelle classe pourrait envelopper ces superglobales ? Quelles méthodes lui donneriez-vous ?
4. Une redirection, une page 404 et une page HTML normale ont quelque chose en commun : ce sont toutes des
   réponses HTTP. Quelles informations une réponse contient-elle ? Comment une classe `Response` pourrait-elle
   les représenter, et à quel moment unique les envoyer ?
5. Toutes vos vues répètent le même squelette HTML. Comment une classe `View` pourrait-elle gérer un
   « layout » commun dans lequel s'insère chaque vue ? Dans quel ordre faut-il exécuter la vue et le layout ?
6. Le `Router` de l'étape 8 est un tableau et une boucle. Qu'est-ce qui changerait si on en faisait une classe ?
   Quelles méthodes exposerait-elle ?

**Réaliser**

7. Créez `src/Core/` avec `Router`, `Request`, `Response` et `View`. Les contrôleurs ne doivent plus toucher
   aucune superglobale ni appeler `header()` directement.

**Vérifier**

8. Instanciez un contrôleur depuis un script en ligne de commande, en lui fournissant une fausse `Request`
   et un faux `UserRepository`. Obtenez-vous une `Response` sans qu'aucune page web n'ait été chargée ?
9. Dessinez le schéma complet d'une requête, du navigateur au HTML, en plaçant chaque classe. Comparez avec
   le schéma « classique » de l'étape 1 : qu'est-ce qui a été ajouté ? Qu'est-ce qui a été enlevé ?

**Prendre du recul**

10. Vous avez écrit un mini framework. Les frameworks connus font-ils autre chose, ou font-ils la même chose
    en plus complet ? Ouvrez le code source d'un routeur populaire : reconnaissez-vous vos propres idées ?
11. Le MVC a un coût : plus de fichiers, plus d'indirections, plus de choses à comprendre avant d'écrire une
    ligne. Dans quel type de projet ce coût n'en vaut-il pas la peine ? Répondre « jamais » n'est pas une réponse.

---

## Étape 10 : un moteur de templates

**Observer**

1. Ouvrez vos vues. Combien de fois écrivez-vous `<?= htmlspecialchars(...) ?>` ? Que se passe-t-il si vous
   l'oubliez une seule fois sur une donnée saisie par l'utilisateur ? Essayez : inscrivez-vous avec l'email
   `<script>alert(1)</script>@test.fr` et affichez-le quelque part sans échappement.
2. Comparez `<?php foreach ($erreurs as $erreur): ?>` et `<?php endforeach; ?>` avec ce qu'un intégrateur HTML
   non développeur pourrait lire. Où est la frontière entre « un peu de PHP dans la vue » et « trop » ?
3. Le layout de l'étape 9 gère un seul emplacement, `$contenu`. Comment feriez-vous pour qu'une vue puisse
   aussi injecter un `<title>` différent, ou un script en bas de page ?

**Concevoir**

4. Un moteur de templates transforme une syntaxe simplifiée en PHP. Décidez de la vôtre : par exemple
   `{{ variable }}` pour afficher avec échappement et `{% for x in liste %}` pour boucler. Écrivez trois
   templates fictifs avec cette syntaxe avant d'écrire une ligne de moteur. La syntaxe est-elle agréable ?
   Ambiguë quelque part ?
5. Deux stratégies : interpréter le template à chaque requête, ou le **compiler** une fois en fichier PHP
   ordinaire puis inclure ce fichier. Quels avantages à compiler ? Comment savoir si le fichier compilé est
   périmé par rapport au template source ? Cherchez `filemtime()`.
6. `{{ variable }}` doit devenir `<?= htmlspecialchars($variable) ?>`. Quelle fonction PHP permet de faire
   cette transformation sur tout un fichier d'un coup ? Écrivez l'expression régulière sur papier avant de la
   tester.
7. Échapper par défaut, c'est plus sûr. Mais parfois on veut afficher du HTML volontairement. Quelle syntaxe
   choisir pour dire « ne pas échapper » ? Pourquoi est-il important que ce soit l'exception qui demande
   un effort, et pas l'inverse ?
8. Comment gérer l'héritage : un template `home.html` qui « étend » `layout.html` et remplit des blocs nommés ?
   Dans quel ordre exécuter l'enfant et le parent ? Cette question ressemble-t-elle à la question 5 de l'étape 9 ?

**Réaliser**

9. Créez `src/Core/Template.php` avec au minimum : affichage échappé, affichage brut, condition, boucle,
   et un layout avec blocs. Les fichiers compilés vont dans un dossier `cache/` ignoré par Git. Réécrivez
   les vues dans la nouvelle syntaxe. `Core\View` devient un simple adaptateur ou disparaît.

**Vérifier**

10. Refaites le test de la question 1 avec l'email piégé. Le script s'exécute-t-il encore ?
11. Modifiez un template, rechargez : le changement apparaît-il ? Regardez dans `cache/` : le fichier compilé
    a-t-il été régénéré ? Modifiez maintenant le fichier compilé à la main, rechargez : que se passe-t-il ?
    Est-ce le comportement que vous vouliez ?
12. Ouvrez un template compilé. Est-il lisible ? Si un message d'erreur PHP pointe vers une ligne du fichier
    compilé, l'élève saura-t-il retrouver la ligne du template source ?

**Prendre du recul**

13. Votre moteur ressemble-t-il à un outil connu ? Cherchez Twig ou Blade et comparez trois choses : la syntaxe,
    la gestion de l'échappement, et l'héritage de templates. Qu'ont-ils prévu que vous n'aviez pas imaginé ?
14. Vous avez ajouté une couche entre le développeur et le HTML final. Qu'avez-vous gagné en sécurité et en
    lisibilité ? Qu'avez-vous perdu en simplicité et en débogage ? À quel moment un projet justifie ce compromis ?

---

## Pour finir

Reprenez vos notes de la question 8 de l'étape 1, celle où vous avez formulé le problème en une phrase.
Le problème est-il résolu ? Reformulez cette phrase avec ce que vous savez maintenant. Si les deux versions
sont identiques, vous n'avez pas appris ce que ce parcours voulait vous apprendre. Si elles sont différentes,
expliquez en quoi.
