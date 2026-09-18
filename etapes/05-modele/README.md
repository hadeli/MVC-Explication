# Étape 5 : isoler l'accès aux données

**Changement par rapport à l'étape 4** : plus aucun `SELECT` ni `INSERT` hors de `models/`.

| Fichier | Rôle |
|---|---|
| `models/User.php` | Objet immuable représentant un utilisateur, sait vérifier son mot de passe |
| `models/UserRepository.php` | `findByEmail()`, `emailExists()`, `create()` ; reçoit `$pdo` dans son constructeur |

`create()` reçoit le mot de passe en clair et le hache lui-même : le reste du code ne peut pas oublier de le faire.

Les pages `login.php` et `register.php` se lisent maintenant comme une histoire : lire le formulaire, demander
au repository, décider, afficher. En contrepartie, chaque page commence par une pile de `require` : c'est le
problème de l'étape suivante.

Le `CREATE TABLE IF NOT EXISTS` reste dans `includes/db.php` : le schéma relève de l'infrastructure, pas du modèle.
