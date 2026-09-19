# Étape 14 : mettre le noyau en cache

> **Objectif** : ne payer le balayage de `src/Controller/` et la lecture des constructeurs qu'**une fois**,
> et relire le résultat depuis un fichier PHP tant que rien n'a changé.

> **Bonus** (voir l'étape 12) : étape facultative, qui prépare les unités d'enseignement suivantes. README
> ouvert pendant que vous codez.

## Le problème au départ

À l'étape 13, chaque requête refait le même travail : `glob()` sur le dossier, `is_a()` sur chaque nom
(donc autoload de chaque classe), `ReflectionClass` pour vérifier qu'elle est instanciable, puis
`ReflectionClass` à nouveau pour lire le constructeur de l'élue. Le résultat est **toujours le même** tant
que les fichiers ne changent pas. Un calcul déterministe dont l'entrée ne bouge pas se fait une fois.

La réponse est classique : écrire le résultat dans un fichier PHP, et le relire tant que la source n'a pas
changé, c'est-à-dire tant que sa date de modification ne dépasse pas celle du fichier écrit. C'est ce que font
Twig et Blade avec leurs templates compilés. Ici la « source » est un dossier.

## Ce qui change

```
14-cache-noyau/
├── public/index.php              charge les plans depuis le cache, les donne au Container et au Router
├── cache/                        NOUVEAU, non versionné, créé à la première requête
│   └── controleurs.php           classe => types du constructeur, généré
├── src/Core/
│   ├── ControllerCache.php       NOUVEAU : écrit et relit cache/controleurs.php, décide s'il est frais
│   ├── Container.php             la réflexion sort de creer() dans analyser(), publique et statique ; accepte des plans tout faits
│   ├── ControllerFinder.php      inchangé : appelé par ControllerCache, plus par index.php
│   └── ... (Router, ControllerInterface, AbstractController, Request, Response, View inchangés)
├── src/Controller/               INCHANGÉ
└── views/, src/View/, autoload.php   inchangés
```

## Comment ça marche

### Le fichier généré

```php
<?php
// Généré automatiquement depuis .../src/Controller. Ne pas modifier : régénéré dès que le dossier change.
// (mise en page condensée ici : var_export() écrit chaque tableau imbriqué sur plusieurs lignes)
return array (
  'App\\Controller\\HomeController' => array (0 => 'App\\Core\\View'),
  'App\\Controller\\LoginController' => array (0 => 'App\\Model\\UserRepository', 1 => 'App\\Core\\View'),
  'App\\Controller\\LogoutController' => array (),
  // ...
);
```

C'est un **plan de construction** : pour chaque contrôleur, les classes à fournir à son constructeur, dans
l'ordre. Tout ce que l'étape 13 calculait à chaque requête est là, en clair. `var_export()` a produit ce PHP ;
un `require` le relit et renvoie le tableau, sans `glob`, sans réflexion, sans autoload.

### `ControllerCache` : frais ou périmé ?

```php
public function charger(): array
{
    if ($this->estFrais()) {
        return require $this->fichier;
    }

    $plans = [];
    foreach (ControllerFinder::trouver($this->dossier, $this->namespace) as $classe) {
        $plans[$classe] = Container::analyser($classe);
    }
    $this->ecrire($plans);

    return $plans;
}
```

Le chemin lent est exactement l'étape 13 : finder puis analyse. Le chemin rapide est un `require`.
Toute la question est `estFrais()` :

```php
if (!is_file($this->fichier)) {                         // 0. pas encore de cache
    return false;
}

$dateCache = filemtime($this->fichier);

if ($dateCache <= filemtime($this->dossier)) {         // 1. le DOSSIER a changé
    return false;
}
foreach (glob($this->dossier . '/*.php') as $source) {  // 2. un FICHIER a changé
    if ($dateCache <= filemtime($source)) {
        return false;
    }
}
return true;
```

Deux vérifications, parce qu'un dossier et ses fichiers ne changent pas pour les mêmes raisons :

- la date d'un **dossier** change quand on y ajoute, supprime ou renomme une entrée. Pas quand on modifie
  le contenu d'un fichier. Elle attrape donc le nouveau `ProfilController.php` et le fichier effacé ;
- la date d'un **fichier** change quand on modifie son contenu : un paramètre ajouté au constructeur.
  Le dossier ne le voit pas ; il faut regarder chaque fichier.

Pourquoi `<=` et non `<` ? `filemtime()` est à la seconde près. Un fichier modifié dans la même seconde que
l'écriture du cache a la **même** date que lui : avec `<`, il passerait pour frais et la modification serait
ratée. Avec `<=`, l'égalité est traitée comme périmée : au pire, une régénération de trop.

Le `glob()` de la vérification coûte une lecture de répertoire et un `stat` par fichier. Ce n'est pas rien,
mais c'est sans commune mesure avec charger et analyser six classes.

### `ecrire()` : produire du PHP qu'un `require` relira

```php
private function ecrire(array $plans): void
{
    $dossierCache = dirname($this->fichier);

    if (!is_dir($dossierCache) && !mkdir($dossierCache, 0777, true)) {
        throw new RuntimeException("Impossible de créer le dossier de cache $dossierCache");
    }

    $contenu = "<?php\n"
        . "// Généré automatiquement depuis {$this->dossier}. Ne pas modifier : régénéré dès que le dossier change.\n"
        . 'return ' . var_export($plans, true) . ";\n";

    file_put_contents($this->fichier, $contenu);
}
```

`var_export($plans, true)` renvoie le tableau écrit en syntaxe PHP au lieu de l'afficher (second argument
`true`). Précédé de `return`, ce texte est un fichier PHP complet : `require` l'exécute et renvoie le tableau.
Aucun format à inventer, aucun parseur à écrire. `RuntimeException` est une classe globale : `use` en tête du
fichier, comme aux étapes 12 et 13.

### `Container` : le plan vient d'ailleurs

```php
public function creer(string $classe): object
{
    if (isset($this->services[$classe])) {
        return $this->services[$classe];
    }

    $this->plans[$classe] ??= self::analyser($classe);     // plan du cache, sinon réflexion

    $arguments = array_map(fn(string $type) => $this->creer($type), $this->plans[$classe]);

    return new $classe(...$arguments);
}
```

La réflexion sort de `creer()` pour une nouvelle méthode `analyser()`, **publique et statique** : `ControllerCache` l'appelle pour
remplir le cache, `creer()` l'appelle en secours pour une classe absente des plans (une dépendance qui
n'est pas un contrôleur). `$a ??= $b` n'assigne `$b` que si `$a` est absent ou `null` : un plan connu n'est
jamais recalculé, et un plan analysé en secours est gardé pour le reste de la requête.

### `public/index.php`

```php
$plans = (new ControllerCache($racine . '/src/Controller', 'App\\Controller', $racine . '/cache/controleurs.php'))->charger();

$container = new Container([View::class => $view, UserRepository::class => $repository], $plans);
$router = new Router(array_keys($plans), $container, $view);
```

Les clés des plans sont la liste des contrôleurs ; les valeurs, leurs constructeurs. Un seul objet en plus
par rapport à l'étape 13, et `ControllerFinder` n'apparaît plus dans le front controller.

## Cheminement d'une requête (cache chaud)

```
POST /login → index.php
  → ControllerCache::charger()  → estFrais() : 1 stat du cache, 1 du dossier, 7 des fichiers → require cache/controleurs.php
  → Router::dispatch()          → HomeController::support() false, LoginController::support() true
  → Container::creer()          → plan connu : [UserRepository, View] → new LoginController(...)   (0 réflexion)
  → handle() → ... → send()
```

Cache froid : `estFrais()` renvoie faux, l'étape 13 s'exécute une fois, le fichier est écrit, la requête
continue normalement. Le visiteur ne voit pas la différence, sauf quelques millisecondes.

## Expériences à faire

1. Videz `cache/`, chargez une page, puis `cat cache/controleurs.php` : les six contrôleurs et leurs
   constructeurs. Rechargez : le fichier n'a pas bougé (`ls -l --time-style=full-iso cache/`).
2. `touch src/Controller/LoginController.php`, rechargez : le fichier de cache a une nouvelle date. Le dossier
   n'a pas changé, c'est la vérification fichier par fichier qui a vu le `touch`.
3. Créez un fichier vide `src/Controller/Vide.php`, rechargez, supprimez-le, rechargez : deux régénérations,
   déclenchées par la date du dossier. Le contenu du cache est identique avant et après : un fichier qui ne
   contient pas de contrôleur ne change rien au plan.

## Ce qui a été gagné, ce qui a été perdu

| Gagné | Perdu |
|---|---|
| Plus de `glob` + `is_a` + réflexion par requête, un `require` à la place | Un fichier généré de plus à comprendre quand quelque chose ne va pas |
| Le plan de construction est lisible dans `cache/controleurs.php` | Une vérification de fraîcheur (9 `stat`) à chaque requête |
| Le mécanisme est celui de tous les caches de fichiers (templates compilés de Twig, conteneur de Symfony) | `cache/` doit être inscriptible : un besoin nouveau pour le projet |
| `ControllerFinder` et la réflexion ont quitté le chemin critique | |

Ce que cette étape **ne** change **pas** : `support()` est toujours appelée sur chaque classe jusqu'à la
bonne, donc chaque contrôleur est toujours **chargé** par l'autoloader, même s'il n'est pas construit. Le
cache connaît la liste des classes, pas ce que leur `support()` décide. Pour éviter ce chargement, il
faudrait mettre dans le cache le verbe et le chemin de chaque contrôleur : c'est ce que font les frameworks,
et c'est... une table de routes, le `config/routes.php` supprimé à l'étape 12, mais **générée** au lieu d'être écrite à la main.
La boucle est bouclée : on a supprimé la table pour la retrouver, produite par le code au lieu de le décrire.

Symfony et Laravel poussent la logique jusqu'au bout : en développement, ils vérifient la fraîcheur comme
ici ; en production, ils ne vérifient **rien** et le cache est construit au déploiement (`cache:warmup`,
`route:cache`). La vérification est un confort de développeur, pas un besoin de production.

## Pièges

- **Modifier `cache/controleurs.php` à la main.** Il sera écrasé à la prochaine modification du dossier, et
  d'ici là il ment. Le cache n'est pas une source.
- **Une horloge qui recule, ou un fichier copié avec sa date d'origine** (`cp -p`, `rsync -a`, une archive décompressée) : le fichier
  peut être « plus vieux » que le cache alors qu'il vient de changer. `rm -rf cache/` règle tout ; c'est
  pourquoi `verifier.sh` le fait avant chaque test.
- **Une classe dans un sous-dossier.** `glob('*.php')` ne descend pas ; ni le finder ni la vérification de
  fraîcheur ne la verront. Le finder et le cache doivent rester d'accord sur ce qu'est « le dossier ».
- **Deux requêtes simultanées sur un cache froid.** Les deux écrivent le même fichier ; `file_put_contents`
  n'est pas atomique. Ici elles écrivent le même contenu, le risque est nul ; un vrai framework écrit dans
  un fichier temporaire puis `rename()`, qui est atomique.

## Fin du parcours

Reprenez l'étape 1 et comparez avec ce dossier : même comportement, vérifié par le même script. Puis
relisez la question 3 de l'étape 1 dans `PLAN.md`.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000 -t public
./verifier.sh 14               # le script vide cache/ avant de tester
cat cache/controleurs.php      # après une première requête
```

**Questions du plan** : `PLAN.md`, étape 14.
