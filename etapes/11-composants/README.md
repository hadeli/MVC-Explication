# Étape 11 : des composants pour les vues

> **Objectif** : que l'échappement soit le comportement par défaut, garanti par le code et non par la
> vigilance, et que le HTML qui se répète (un champ, un formulaire, la page entière) soit écrit une seule fois.
> Côté PHP, l'étape introduit l'héritage, les classes abstraites et `__toString()`.

## Le problème au départ

Comptez les `htmlspecialchars()` dans les vues de l'étape 10 : sept, dispersés dans cinq fichiers. Retirez
celui qui entoure `$email` dans `login.php`, puis tapez `"><b>gras</b>` comme email dans le formulaire de
connexion, avec n'importe quel mot de passe. Le formulaire réaffiche ce que vous avez saisi : le guillemet
ferme l'attribut `value`, et le mot « gras » apparaît en gras sous le champ. Une seule omission suffit, et
rien ne la signale.

Ensuite, le bloc `<p><label>…</label><br><input …></p>` est recopié cinq fois entre `login.php` et
`register.php`. Enfin `views/layout.php` reçoit une variable `$contenu` dont rien ne dit si elle est déjà du
HTML ou du texte à échapper : il faut le savoir.

Une première idée serait d'écrire un moteur de templates avec sa propre syntaxe (`{{ email }}`), compilé en
PHP. C'est ce que font Twig et Blade. Mais cela demande d'écrire un compilateur, d'inventer une syntaxe pour
les conditions et les boucles, et de gérer un cache : beaucoup de code sans rapport avec le MVC. On va faire
plus simple, avec des objets.

## Ce qui change

```
11-composants/
├── src/
│   ├── View/                     NOUVEAU : des objets qui produisent du HTML
│   │   ├── Component.php             classe abstraite : render(), __toString(), e(), renderAll()
│   │   ├── Layout.php                la page entière, remplace views/layout.php
│   │   ├── Form.php, Input.php, Button.php
│   │   ├── Alert.php                 liste de messages
│   │   └── Paragraph.php, Link.php, Strong.php
│   └── Core/View.php             n'exécute plus le layout : la vue s'en charge
├── views/
│   ├── layout.php                DISPARAÎT
│   └── home.php, login.php, register.php, 404.php   assemblent des composants, plus une balise HTML dedans
└── ...                           inchangé : contrôleurs, routes, index.php
```

## La règle

Un composant reçoit deux sortes de choses, et le type PHP dit laquelle :

| Ce qui entre | C'est | Le composant… |
|---|---|---|
| une `string` | du **texte** | l'échappe, toujours, avec `e()` |
| un `Component` | du **HTML** déjà produit | l'insère tel quel, sans jamais ré-échapper |

Il n'y a pas d'exception, donc pas de syntaxe « brut » à surveiller. Pour insérer du HTML dans un composant,
on lui donne un autre composant.

```php
new Paragraph(['Bonjour ', new Strong($email), ', vous êtes connecté.'])
//             ^ texte     ^ HTML             ^ texte
```

## Comment ça marche

### 1. La classe de base

```php
abstract class Component
{
    abstract public function render(): string;

    public function __toString(): string
    {
        return $this->render();
    }

    protected function e(string $texte): string
    {
        return htmlspecialchars($texte, ENT_QUOTES);
    }

    /** @param array<Component|string> $enfants */
    protected function renderAll(array $enfants): string
    {
        $html = '';
        foreach ($enfants as $enfant) {
            $html .= $enfant instanceof Component ? $enfant->render() : $this->e($enfant);
        }

        return $html;
    }
}
```

`abstract` : on ne peut pas faire `new Component()`, seulement en hériter, et toute classe fille **doit**
écrire `render()`. `__toString()` est appelée par PHP quand l'objet est utilisé comme une chaîne : c'est ce
qui permet `echo $composant`. `e()` est le seul `htmlspecialchars()` du projet : `grep -r htmlspecialchars src views`
ne renvoie plus qu'une ligne. `renderAll()` applique la règle : `$enfant instanceof Component` est vrai si
`$enfant` est un objet de cette classe ou d'une de ses filles.

Pourquoi une classe abstraite plutôt qu'une interface ? Une interface pourrait exiger `render()`, mais elle ne
pourrait pas **fournir** `e()` et `renderAll()` à ses filles. Ici, on veut les deux : un contrat et du code
partagé. Les interfaces arrivent à l'étape 12.

### 2. Un composant concret

```php
final class Input extends Component
{
    public function __construct(
        private readonly string $name,
        private readonly string $label,
        private readonly string $type = 'text',
        private readonly string $value = '',
    ) {
    }

    public function render(): string
    {
        $name = $this->e($this->name);
        $label = $this->e($this->label);
        $type = $this->e($this->type);
        $value = $this->e($this->value);

        return <<<HTML
            <p>
                <label for="{$name}">{$label}</label><br>
                <input type="{$type}" id="{$name}" name="{$name}" value="{$value}" required>
            </p>

            HTML;
    }
}
```

Le HTML est écrit dans un *heredoc* (`<<<HTML … HTML;`) : une chaîne sur plusieurs lignes où `{$name}` insère
une variable. Deux règles de syntaxe : la marque de fin `HTML;` peut être indentée, et son indentation est
retirée de **toutes** les lignes du bloc, donc aucune ligne ne doit être moins indentée qu'elle ; la ligne vide
avant `HTML;` ajoute un saut de ligne à la fin du HTML produit.

Les variables sont **toutes** échappées avant d'entrer dans le heredoc. `ENT_QUOTES` compte : sans lui,
l'email `"><b>gras</b>` du début sortirait de `value="…"` et fermerait l'attribut.

### 3. Emboîter les composants

`Layout` produit le document complet. Sa navigation est faite de `Link`, ses enfants sont la page :

```php
$nav = $this->renderAll($this->user === null
    ? [new Link('/', 'Accueil'), ' | ', new Link('/login', 'Connexion'), ' | ', new Link('/register', 'Inscription')]
    : [new Link('/', 'Accueil'), ' | ', new Link('/logout', 'Déconnexion')]);

$content = $this->renderAll($this->children);
```

Un composant qui en contient d'autres appelle leur `render()` à travers `renderAll()`. Le HTML final est
construit de l'intérieur vers l'extérieur : `Link` d'abord, puis `Paragraph`, puis `Layout`.

Le `<h1>` vient maintenant du titre : l'accueil affiche « Accueil » là où l'étape 10 affichait « Bienvenue ».
C'est la seule différence visible de tout le parcours, et elle est assumée : un titre par page, écrit une fois.

### 4. Les vues assemblent

`views/login.php`, en entier :

```php
<?php

use App\View\Alert;
use App\View\Button;
use App\View\Form;
use App\View\Input;
use App\View\Layout;
use App\View\Link;
use App\View\Paragraph;

echo new Layout($titre, $utilisateurConnecte, [
    new Alert($erreur === null ? [] : [$erreur]),
    new Form('/login', [
        new Input('email', 'Email', 'email', $email),
        new Input('password', 'Mot de passe', 'password'),
        new Button('Se connecter'),
    ]),
    new Paragraph(['Pas encore de compte ? ', new Link('/register', 'Inscrivez-vous'), '.']),
]);
```

Plus une balise, plus un `htmlspecialchars()`. Les variables `$titre`, `$erreur`, `$email`,
`$utilisateurConnecte` viennent du contrôleur et de `View`, comme à l'étape 10. Les `use` sont nécessaires :
ils valent par fichier, et une vue est un fichier comme un autre, même si c'est `View` qui l'inclut. Une
condition reste du PHP ordinaire : dans `register.php`, un `? :` choisit entre le message de succès et le
formulaire. Il n'y a aucune syntaxe nouvelle à apprendre pour les `if` et les boucles.

### 5. `View` se simplifie

Le layout n'est plus un fichier exécuté après la vue : c'est un composant que la vue instancie. `View::render()`
capture la vue et emballe le résultat dans une `Response`, c'est tout :

```php
public function render(string $vue, array $donnees = [], int $statut = 200): Response
{
    return new Response($statut, $this->capturer($vue . '.php', $donnees + $this->partage));
}
```

`capturer()` est celle de l'étape 10 (`extract`, `ob_start`, `require`, `ob_get_clean`). Les contrôleurs n'ont
pas changé d'une ligne : ils ne savent pas que les vues sont devenues des arbres d'objets.

## Expériences à faire

1. Dans le formulaire de connexion, tapez `"><b>gras</b>` comme email, n'importe quel mot de passe : le champ
   réaffiche la saisie telle quelle, rien n'est en gras, et le HTML source montre `&quot;&gt;&lt;b&gt;`.
2. Dans `home.php`, remplacez `new Strong($utilisateurConnecte['email'])` par
   `'<strong>' . $utilisateurConnecte['email'] . '</strong>'`. Les balises s'affichent en clair : une chaîne est
   du texte, même si elle ressemble à du HTML.
3. Retirez le `e()` de `Strong::render()`. Le gras revient. Il y a maintenant **un seul** fichier à ouvrir pour
   trouver une faille de ce genre, et la relecture d'un composant de six lignes est rapide.
4. Depuis le dossier de l'étape, en ligne de commande :
   `php -r 'require "autoload.php"; echo new App\View\Input("email", "Email");'`.
   Un composant se teste sans serveur, sans contrôleur, sans base.

## Ce qui a été gagné, ce qui a été perdu

| Gagné | Perdu |
|---|---|
| L'échappement est le défaut, et le type dit ce qui est texte et ce qui est HTML | Une vue ne ressemble plus à la page : il faut se figurer le HTML produit |
| Un champ de formulaire s'écrit une fois, dans `Input` | Un designer qui ne connaît pas PHP ne peut plus toucher aux vues |
| Les composants se testent un par un, en ligne de commande | Chaque nouvelle balise est une classe à créer |
| Pas de syntaxe à inventer : conditions et boucles sont du PHP | Le HTML est dans des chaînes PHP : pas de coloration, pas d'autocomplétion |

Ce découpage existe dans tous les écosystèmes : les *components* de Blade et de Twig, ceux de React et de Vue
côté navigateur. Le principe est le même : une unité de HTML avec des paramètres typés, qui échappe ce qu'elle
reçoit et qui peut en contenir d'autres.

## Pièges

- **Convertir un composant en chaîne avant de le passer.** `new Paragraph([(string) new Link('/', 'Accueil')])`
  affiche `<a href="/">Accueil</a>` en clair : une fois converti, c'est une `string`, donc du texte. On passe
  l'objet, jamais son rendu.
- **Une variable non échappée dans un heredoc.** `{$this->title}` directement dans le HTML contourne `e()`. La
  discipline : préparer des variables locales échappées en haut de `render()`, et n'insérer que celles-là.
- **Un composant qui fait autre chose que du HTML.** Interroger la base ou lire la session dans `render()` est
  tentant. Un composant reçoit tout ce dont il a besoin par son constructeur : la logique reste au contrôleur.
- **Un enfant `null`.** Une donnée que le contrôleur a oublié de passer arrive en `null` dans `renderAll()` ;
  `e()` attend une `string` et PHP lève `Component::e(): Argument #1 ($texte) must be of type string, null given`.
  C'est le message que vous verrez le plus souvent, et il ne désigne pas le coupable : la faute est dans le
  tableau passé par le contrôleur, pas dans le composant. C'est voulu : mieux vaut une erreur franche qu'une
  page où il manque quelque chose sans bruit.

## Ce qui reste imparfait

Ajouter une page demande encore de toucher trois fichiers : le contrôleur, `config/routes.php` et la liste
des fabriques dans `public/index.php`. Le routeur appelle `$controleur->$action()` sans qu'aucun contrat
garantisse que la méthode existe. Et `AuthController` porte cinq actions dont une, `logout()`, n'utilise aucune
de ses dépendances. Étape 12, qui ouvre la partie **bonus** du parcours : pour le site de votre SAÉ,
ce que vous avez ici suffit.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000 -t public
./verifier.sh 11               # depuis la racine du dépôt
grep -r htmlspecialchars src views    # une seule ligne : Component::e()
```

**Questions du plan** : `PLAN.md`, étape 11.
