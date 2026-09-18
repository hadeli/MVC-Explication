# Étape 1 : les pages classiques

**Point de départ du parcours.** Trois fichiers autonomes, identiques à ceux de la racine du dépôt.
Chacun fait tout : session, connexion à la base, requêtes SQL, validation, HTML.

| Fichier | Rôle |
|---|---|
| `index.php` | Accueil, affiche l'utilisateur connecté depuis `$_SESSION['utilisateur']`, gère `?action=logout` |
| `register.php` | Validation (email, 8 caractères minimum, confirmation), unicité de l'email, `password_hash`, insertion |
| `login.php` | `password_verify`, stockage de l'id et de l'email en session, redirection vers l'accueil |

Stockage : SQLite via PDO dans `database.sqlite`, créé automatiquement à la première requête.

**La duplication est volontaire.** Le bloc de connexion PDO, `session_start()`, la navigation et le squelette
HTML sont copiés d'une page à l'autre. Ce code fonctionne : le parcours consiste à comprendre pourquoi on
voudrait quand même le changer, puis à le faire sans jamais casser le comportement.

```bash
php -S localhost:8000
```

puis ouvrir http://localhost:8000/index.php.
