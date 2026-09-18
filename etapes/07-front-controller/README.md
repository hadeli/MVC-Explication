# Étape 7 : un seul point d'entrée

**Changement par rapport à l'étape 6** : une seule URL mène à un fichier, `public/index.php`. Tout le reste
est hors du dossier public et donc inaccessible depuis le navigateur.

| Fichier | Rôle |
|---|---|
| `public/index.php` | Front controller : autoload, session, connexion, lecture de l'URL, choix de l'action |
| `public/.htaccess` | Pour Apache : réécrit toute URL vers `index.php`. Inutile avec `php -S`. |
| `config/routes.php` | Table `chemin => action` |
| `actions/home.php`, `login.php`, `register.php`, `logout.php` | Anciennes pages, sans `session_start()` ni `require` : le front controller s'en charge |
| `views/404.php` | Page renvoyée avec le code HTTP 404 pour un chemin inconnu |

Les URLs deviennent `/`, `/login`, `/register`, `/logout`. Les formulaires et les liens des vues sont mis à jour.

```bash
cp .env.example .env
php -S localhost:8000 -t public
```

Le serveur intégré de PHP envoie toute URL qui ne correspond pas à un fichier existant vers `index.php`.
Essayez `http://localhost:8000/database.sqlite` ou `/views/login.php` : 404.
