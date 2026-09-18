# Étape 13 : mettre le noyau en cache

> **Objectif** : ne payer le balayage de `src/Controller/` et la lecture des constructeurs qu'**une fois**,
> et relire le résultat depuis un fichier PHP tant que rien n'a changé. Comme les templates de l'étape 10.

## Le problème au départ

À l'étape 12, chaque requête refait le même travail : `glob()` sur le dossier, `is_a()` sur chaque nom
(donc autoload de chaque classe), `ReflectionClass` pour vérifier qu'elle est instanciable, puis
`ReflectionClass` à nouveau pour lire le constructeur de l'élue. Le résultat est **toujours le même** tant
que les fichiers ne changent pas. Un calcul déterministe dont l'entrée ne bouge pas se fait une fois.

Vous connaissez déjà la réponse : à l'étape 10, un template est compilé en PHP dans `cache/`, et recompilé
seulement si sa date de modification dépasse celle du fichier compilé. Ici la « source » est un dossier.

## Ce qui change

```
13-cache-noyau/
├── public/index.php              charge les plans depuis le cache, les donne au Container et au Router
├── cache/
│   ├── <md5>.php                 templates compilés (étape 10)
│   └── controleurs.php           NOUVEAU : classe => types du constructeur, généré
├── src/Core/
│   ├── ControllerCache.php       NOUVEAU : écrit et relit cache/controleurs.php, décide s'il est frais
│   ├── Container.php             analyser() devient publique et statique ; accepte des plans tout faits
│   ├── ControllerFinder.php      inchangé : appelé par ControllerCache, plus par index.php
│   └── ... (Router, ControllerInterface, AbstractController, Request, Response, View, Template inchangés)
└── src/Controller/               INCHANGÉ
```

## Comment ça marche

### Le fichier généré

```php
<?php
// Généré automatiquement depuis .../src/Controller. Ne pas modifier : régénéré dès que le dossier change.
return array (
  'App\\Controller\\HomeController' => array (0 => 'App\\Core\\View'),
  'App\\Controller\\LoginController' => array (0 => 'App\\Model\\UserRepository', 1 => 'App\\Core\\View'),
  'App\\Controller\\LogoutController' => array (),
  // ...
);
```

C'est un **plan de construction** : pour chaque contrôleur, les classes à fournir à son constructeur, dans
l'ordre. Tout ce que l'étape 12 calculait à chaque requête est là, en clair. `var_export()` a produit ce PHP ;
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

Le chemin lent est exactement l'étape 12 : finder puis analyse. Le chemin rapide est un `require`.
Toute la question est `estFrais()` :

```php
$dateCache = filemtime($this->fichier);

if ($dateCache < filemtime($this->dossier)) {          // 1. le DOSSIER a changé
    return false;
}
foreach (glob($this->dossier . '/*.php') as $source) {  // 2. un FICHIER a changé
    if ($dateCache < filemtime($source)) {
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

Le `glob()` de la vérification coûte une lecture de répertoire et un `stat` par fichier. Ce n'est pas rien,
mais c'est sans commune mesure avec charger et analyser six classes.

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

La réflexion est isolée dans `analyser()`, devenue **publique et statique** : `ControllerCache` l'appelle pour
remplir le cache, `creer()` l'appelle en secours pour une classe absente des plans (une dépendance qui
n'est pas un contrôleur). `??=` garde le résultat pour le reste de la requête.

### `public/index.php`

```php
$plans = (new ControllerCache($racine . '/src/Controller', 'App\\Controller', $racine . '/cache/controleurs.php'))->charger();

$container = new Container([View::class => $view, UserRepository::class => $repository], $plans);
$router = new Router(array_keys($plans), $container, $view);
```

Les clés des plans sont la liste des contrôleurs ; les valeurs, leurs constructeurs. Un seul objet en plus
par rapport à l'étape 12, et `ControllerFinder` n'apparaît plus dans le front controller.

## Cheminement d'une requête (cache chaud)

```
POST /login → index.php
  → ControllerCache::charger()  → estFrais() : 1 stat du dossier, 7 stats de fichiers → require cache/controleurs.php
  → Router::dispatch()          → HomeController::support() false, LoginController::support() true
  → Container::creer()          → plan connu : [UserRepository, View] → new LoginController(...)   (0 réflexion)
  → handle() → ... → send()
```

Cache froid : `estFrais()` renvoie faux, l'étape 12 s'exécute une fois, le fichier est écrit, la requête
continue normalement. Le visiteur ne voit pas la différence, sauf quelques millisecondes.

## Ce qui a été gagné, ce qui a été perdu

| Gagné | Perdu |
|---|---|
| Plus de `glob` + `is_a` + réflexion par requête, un `require` à la place | Un fichier généré de plus à comprendre quand quelque chose ne va pas |
| Le plan de construction est lisible dans `cache/controleurs.php` | Une vérification de fraîcheur (8 `stat`) à chaque requête |
| Le mécanisme est celui de l'étape 10 : rien de nouveau à apprendre sur le cache | `cache/` doit être inscriptible, comme à l'étape 10 |
| `ControllerFinder` et la réflexion ont quitté le chemin critique | |

Ce que cette étape **ne** change **pas** : `support()` est toujours appelée sur chaque classe jusqu'à la
bonne, donc chaque contrôleur est toujours **chargé** par l'autoloader, même s'il n'est pas construit. Le
cache connaît la liste des classes, pas ce que leur `support()` décide. Pour éviter ce chargement, il
faudrait mettre dans le cache le verbe et le chemin de chaque contrôleur : c'est ce que font les frameworks,
et c'est... une table de routes, celle de l'étape 10, mais **générée** au lieu d'être écrite à la main.
La boucle est bouclée : on a supprimé la table pour la retrouver, produite par le code au lieu de le décrire.

Symfony et Laravel poussent la logique jusqu'au bout : en développement, ils vérifient la fraîcheur comme
ici ; en production, ils ne vérifient **rien** et le cache est construit au déploiement (`cache:warmup`,
`route:cache`). La vérification est un confort de développeur, pas un besoin de production.

## Pièges

- **Modifier `cache/controleurs.php` à la main.** Il sera écrasé à la prochaine modification du dossier, et
  d'ici là il ment. Comme pour les templates compilés : le cache n'est pas une source.
- **Une horloge qui recule, ou un fichier copié avec sa date d'origine** (`cp -p`, `git checkout`) : le fichier
  peut être « plus vieux » que le cache alors qu'il vient de changer. `rm -rf cache/` règle tout ; c'est
  pourquoi `verifier.sh` le fait avant chaque test.
- **Une classe dans un sous-dossier.** `glob('*.php')` ne descend pas ; ni le finder ni la vérification de
  fraîcheur ne la verront. Le finder et le cache doivent rester d'accord sur ce qu'est « le dossier ».
- **Deux requêtes simultanées sur un cache froid.** Les deux écrivent le même fichier ; `file_put_contents`
  n'est pas atomique. Ici elles écrivent le même contenu, le risque est nul ; un vrai framework écrit dans
  un fichier temporaire puis `rename()`, qui est atomique.

## Fin du parcours

Reprenez l'étape 1 et comparez avec ce dossier : même comportement, vérifié par le même script. Puis
relisez la question 8 de l'étape 1 dans `PLAN.md`.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000 -t public
./verifier.sh 13               # le script vide cache/ avant de tester
cat cache/controleurs.php      # après une première requête
```

**Questions du plan** : `PLAN.md`, étape 13.
