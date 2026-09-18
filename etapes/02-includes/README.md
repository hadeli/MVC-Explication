# Étape 2 : factoriser ce qui se répète

**Changement par rapport à l'étape 1** : les trois familles de code dupliqué sont sorties dans `includes/`.

| Fichier | Contenu |
|---|---|
| `includes/db.php` | Connexion PDO et création de la table, expose `$pdo` |
| `includes/header.php` | Doctype, `<head>`, navigation ; attend `$titre` |
| `includes/footer.php` | Fermeture du document |

`session_start()` reste dans chaque page : il doit s'exécuter avant toute sortie HTML et avant la lecture
de `$_SESSION`, donc avant l'inclusion de l'en-tête.

**Ce qui reste à résoudre** : chaque page mélange encore traitement du formulaire et HTML ; le chemin de la
base est écrit en dur dans `db.php`.

```bash
php -S localhost:8000
```
