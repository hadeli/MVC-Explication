# Étape 10 : un moteur de templates

> **Objectif** : que les vues ne contiennent plus de PHP, que l'échappement soit le comportement par défaut,
> et qu'un template puisse remplir plusieurs emplacements d'un layout.

## Le problème au départ

Inscrivez-vous à l'étape 9 avec l'email `<b>x</b>@test.fr`, puis retirez un `htmlspecialchars()` dans une vue :
le HTML saisi s'exécute dans la page. Une seule omission suffit, et rien ne la signale. Par ailleurs,
`<?php foreach ($erreurs as $erreur): ?>` et `<?php endforeach; ?>` restent du code pour quelqu'un qui ne fait
que de l'HTML.

## Ce qui change

```
10-templates/
├── src/Core/
│   ├── Template.php              NOUVEAU : compilation, cache, blocs, héritage
│   ├── View.php                  devient un adaptateur : Template -> Response
│   └── ...                       inchangé
├── views/                        .html au lieu de .php
│   ├── layout.html               le parent
│   ├── home.html, login.html, register.html, 404.html
└── cache/                        fichiers compilés, non versionnés, créés au premier rendu
```

## La syntaxe

| Template | PHP généré | Note |
|---|---|---|
| `{{ email }}` | `<?= $this->e($email) ?>` | Échappé. Toujours. |
| `{! html !}` | `<?= $html ?>` | Brut. Syntaxe voyante, pour que l'exception se remarque |
| `{{ utilisateur.email }}` | `$this->acceder($utilisateur, 'email')` | Tableau ou objet, `null` si absent |
| `{% if erreur != null %}` … `{% else %}` … `{% endif %}` | `if (…): … else: … endif;` | `not`, `and`, `or` acceptés |
| `{% for erreur in erreurs %}` … `{% endfor %}` | `foreach ($erreurs as $erreur):` | |
| `{% extends "layout" %}` | `$this->etendre("layout")` | Le template remplit des blocs, le parent affiche |
| `{% block titre %}` … `{% endblock %}` | `debutBloc('titre')` … `finBloc()` | Enfant : définit. Parent : valeur par défaut |
| `{% yield contenu %}` | `<?= $this->bloc('contenu') ?>` | Emplacement dans le parent |

Exemple complet, `views/login.html` :

```html
{% extends "layout" %}

{% block titre %}Connexion{% endblock %}

{% block contenu %}
<h1>Connexion</h1>
{% if erreur != null %}
    <p>{{ erreur }}</p>
{% endif %}
<form method="post" action="/login">
    <input type="email" name="email" value="{{ email }}" required>
    ...
</form>
{% endblock %}
```

## Comment ça marche

### 1. Compiler plutôt qu'interpréter

Le template n'est pas lu à chaque requête. La première fois, `Template::compiler()` le transforme en fichier
PHP ordinaire dans `cache/`, puis l'inclut. Les fois suivantes, seul le fichier compilé est inclus :

```php
$compile = $this->dossierCache . '/' . md5($source) . '.php';
if (!is_file($compile) || filemtime($compile) < filemtime($source)) {
    file_put_contents($compile, $this->traduire(file_get_contents($source)));
}
include $compile;
```

`filemtime()` compare les dates de modification : un template modifié est recompilé au prochain rendu, un
template inchangé ne coûte qu'un `include`.

### 2. Traduire avec des expressions régulières

Trois passes, dans cet ordre : les balises `{% %}`, puis l'affichage brut `{! !}`, puis l'affichage échappé
`{{ }}`. Chaque remplacement reste sur la même ligne : **la ligne 12 du fichier compilé est la ligne 12 du
template**, ce qui rend les messages d'erreur PHP exploitables.

Les expressions (`utilisateurConnecte.email`, `erreur != null`) passent par `expression()` : les chaînes
littérales sont mises de côté, `not`/`and`/`or` deviennent `!`/`&&`/`||`, chaque identifiant devient une
variable `$identifiant`, et chaque `.segment` devient un appel `$this->acceder(..., 'segment')`, puis les chaînes
sont remises.

### 3. Héritage par blocs

C'est la partie la plus subtile. Un template enfant qui fait `{% extends "layout" %}` ne produit **rien**
par lui-même :

1. `Template::executer("login")` inclut le compilé de `login.html`. La première instruction appelle `etendre("layout")`,
   qui mémorise le parent.
2. Chaque `{% block %}` de l'enfant capture son contenu dans un tampon et, puisqu'un parent est déclaré,
   le **stocke** dans `$this->blocs['titre']`, `$this->blocs['contenu']`, sans rien afficher.
3. Toute la sortie de l'enfant est jetée. Puisqu'un parent existe, `executer("layout")` est appelé avec les
   mêmes données.
4. Dans le parent, `{% yield contenu %}` affiche `$this->blocs['contenu']`. Un `{% block titre %}MVC{% endblock %}`
   dans le parent affiche le bloc de l'enfant s'il existe, sinon son propre contenu (« MVC ») : c'est une
   valeur par défaut.

Ordre d'exécution : **l'enfant d'abord, le parent ensuite**. C'est le même principe que `View::render()` à
l'étape 9 (la vue d'abord, le layout ensuite), généralisé à plusieurs emplacements nommés.

### 4. `View` ne fait plus que traduire

```php
public function render(string $vue, array $donnees = [], int $statut = 200): Response
{
    return new Response($statut, $this->template->rendre($vue, $donnees + $this->partage));
}
```

Les contrôleurs n'ont pas changé d'une ligne. Ils ne savent pas que les vues sont devenues des templates.

## Expériences à faire

1. Modifiez `login.html`, rechargez : le changement apparaît, et le fichier dans `cache/` a une nouvelle date.
2. Modifiez le fichier compilé à la main, rechargez : votre modification s'affiche. Touchez ensuite `login.html`
   (`touch views/login.html`) : elle disparaît, écrasée par la recompilation. Le cache n'est pas une source.
3. Ouvrez un fichier compilé : la première ligne indique son template source.
4. Retirez `{% extends %}` d'un template : il s'affiche sans layout, ses blocs sont rendus sur place.

## Ce qui a été gagné, ce qui a été perdu

| Gagné | Perdu |
|---|---|
| L'échappement est le défaut ; l'oubli devient impossible | Une couche de plus à comprendre avant d'écrire une vue |
| Vues lisibles sans connaître PHP | Une erreur PHP pointe vers `cache/…php`, il faut remonter au template |
| Plusieurs emplacements dans le layout (`titre`, `contenu`) | Moteur maison : pas de filtres (`|upper`), pas de macros, pas d'échappement contextuel (JS, attributs) |
| Un template compilé coûte un `include` | Le dossier `cache/` doit être accessible en écriture |

Twig et Blade font la même chose, en beaucoup plus complet et plus robuste. Si vous les ouvrez, vous
reconnaîtrez : compilation en PHP mis en cache, `{{ }}` échappé par défaut, `{% extends %}` et blocs.

## Pièges

- **Une expression trop maligne.** `{{ fonction(x) }}` devient `$fonction($x)` : le moteur ne gère que des
  variables, des chemins pointés et des opérateurs. C'est volontaire : la logique appartient au contrôleur.
- **`{! !}` pour aller plus vite.** Chaque usage doit pouvoir être justifié : « cette valeur vient de nous,
  pas de l'utilisateur ».
- **Un `cache/` non inscriptible** en production : la première requête échoue. Le dossier est créé
  automatiquement, mais les droits dépendent du serveur.

## Ce qui reste imparfait

Ajouter une page demande encore de toucher trois fichiers : le contrôleur, `config/routes.php` et la liste
des fabriques dans `public/index.php`. Le routeur appelle `$controleur->$action()` sans qu'aucun contrat
garantisse que la méthode existe. Et `AuthController` porte cinq actions dont certaines n'utilisent aucune de
ses dépendances. Étape 11.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000 -t public
./verifier.sh 10               # le script vide cache/ avant de tester
```

**Questions du plan** : `PLAN.md`, étape 10.
