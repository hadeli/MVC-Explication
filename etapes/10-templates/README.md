# Étape 10 : un moteur de templates

**Changement par rapport à l'étape 9** : les vues ne contiennent plus de PHP. Elles sont écrites dans une
syntaxe dédiée, compilées une fois en PHP dans `cache/`, puis incluses.

| Fichier | Rôle |
|---|---|
| `src/Core/Template.php` | Compilation (expressions régulières), cache par `filemtime`, blocs et héritage |
| `src/Core/View.php` | Devient un simple adaptateur : appelle `Template`, renvoie une `Response` |
| `views/*.html` | Templates. `layout.html` est le parent, les autres l'étendent |
| `cache/` | Fichiers compilés, ignorés par Git, régénérés quand le template source est plus récent |

## Syntaxe

| Template | PHP généré |
|---|---|
| `{{ email }}` | `<?= $this->e($email) ?>` : échappé, toujours |
| `{! html !}` | `<?= $html ?>` : brut, syntaxe volontairement voyante |
| `{{ utilisateur.email }}` | `$this->acceder($utilisateur, 'email')` : tableau ou objet, `null` si absent |
| `{% if erreur != null %} … {% else %} … {% endif %}` | `if … else … endif` ; `not`, `and`, `or` acceptés |
| `{% for erreur in erreurs %} … {% endfor %}` | `foreach ($erreurs as $erreur)` |
| `{% extends "layout" %}` | Le template remplit des blocs, le parent affiche |
| `{% block contenu %} … {% endblock %}` | Dans l'enfant : définit. Dans le parent : valeur par défaut |
| `{% yield contenu %}` | Emplacement du bloc dans le parent |

Les remplacements ne changent pas le nombre de lignes : la ligne 12 d'un fichier compilé correspond à la
ligne 12 du template. Le fichier compilé commence par un commentaire indiquant son template source.

## Ce qui a été gagné, ce qui a été perdu

Gagné : l'échappement est le comportement par défaut, l'oubli devient impossible ; les vues sont lisibles par
quelqu'un qui ne connaît pas PHP ; le layout accepte plusieurs emplacements (`titre`, `contenu`).

Perdu : une couche de plus à comprendre, des messages d'erreur qui pointent vers `cache/`, un moteur maison
qui ne couvre pas tout ce que Twig ou Blade proposent (filtres, macros, échappement contextuel).

```bash
cp .env.example .env
php -S localhost:8000 -t public
```
