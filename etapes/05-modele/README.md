# Étape 5 : isoler l'accès aux données

> **Objectif** : que le reste du code parle d'**utilisateurs**, jamais de tables, de colonnes ni de SQL.

## Le problème au départ

Trois requêtes SQL sont réparties dans deux pages. `login.php` sait que la table s'appelle `users` et que le
hash est dans une colonne `password`. `register.php` sait comment insérer. Si on renomme une colonne, on
cherche dans tout le projet. Si on passe de SQLite à MySQL, on relit chaque requête.

Plus grave : rien n'oblige à hacher le mot de passe. `password_hash()` est appelé dans `register.php` juste
avant l'`INSERT`. Une deuxième page qui crée des utilisateurs pourrait l'oublier, et la base contiendrait des
mots de passe en clair sans qu'aucune erreur ne se produise.

## Ce qui change

```
05-modele/
├── models/                       NOUVEAU
│   ├── User.php                  un utilisateur : id, email, hash ; sait vérifier un mot de passe
│   └── UserRepository.php        findByEmail(), emailExists(), create()
├── includes/  (db.php, env.php, render.php)
├── views/     (inchangé)
├── index.php
├── login.php                     plus aucun SQL
└── register.php                  plus aucun SQL
```

Vérification : `grep -rn 'SELECT\|INSERT' *.php` ne renvoie rien à la racine.

## Comment ça marche

Deux classes aux rôles distincts.

**`User` représente une ligne.** C'est un objet immuable : trois propriétés `readonly` et une méthode.

```php
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $passwordHash,
    ) {}

    public function verifierMotDePasse(string $motDePasse): bool
    {
        return password_verify($motDePasse, $this->passwordHash);
    }
}
```

Trois mots-clés nouveaux. `final` : personne ne pourra hériter de cette classe. Écrire `public readonly int $id`
directement dans les paramètres du constructeur déclare la propriété **et** l'assigne, en une ligne
(promotion de constructeur, PHP 8). `readonly` : assignée une fois, plus jamais modifiée ;
`$utilisateur->email = 'x'` produit `Error: Cannot modify readonly property User::$email`.

Par rapport au tableau associatif de l'étape 4 : les propriétés sont déclarées, donc une faute de frappe
(`$user->emial`) est soulignée par l'éditeur avant même d'exécuter le code, alors que rien ne connaît les
clés de `$user['emial']` ; l'éditeur complète les noms ; et le comportement
« vérifier un mot de passe » vit à côté de la donnée qu'il utilise.

**`UserRepository` parle à la base.** Il reçoit `$pdo` dans son constructeur et expose des verbes métier :

```php
final class UserRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findByEmail(string $email): ?User    // null si absent
    public function emailExists(string $email): bool
    public function create(string $email, string $motDePasseClair): User

    private function hydrater(array $ligne): User     // une ligne SQL -> un objet, en un seul endroit
}
```

`create()` reçoit le mot de passe **en clair** et le hache lui-même. C'est le point le plus important de
l'étape : le hachage n'est plus une étape que l'appelant doit penser à faire, c'est une propriété du repository.
Il devient impossible d'enregistrer un mot de passe non haché en passant par lui.

Les pages se lisent maintenant comme une histoire :

```php
// login.php
$utilisateur = $repository->findByEmail($email);

if ($utilisateur !== null && $utilisateur->verifierMotDePasse($password)) {
    $_SESSION['utilisateur'] = ['id' => $utilisateur->id, 'email' => $utilisateur->email];
    header('Location: index.php');
    exit;
}
```

## Où va le `CREATE TABLE` ?

Il reste dans `includes/db.php`. Le schéma relève de l'infrastructure (comme la connexion), pas du modèle :
le repository suppose que la table existe. Dans un vrai projet, ce `CREATE TABLE IF NOT EXISTS` exécuté à
chaque requête serait remplacé par des migrations lancées une fois. On le garde ici pour que le projet démarre
sans aucune commande d'installation.

## Ce que ça change concrètement

- Passer sur MySQL : modifier `.env`, relire deux fichiers (`UserRepository.php` et le `CREATE TABLE` de
  `includes/db.php`) pour vérifier la compatibilité du SQL. Les pages ne bougent pas.
- Ajouter une colonne `created_at` : `UserRepository` (le `SELECT` et `hydrater()`), `User` et le
  `CREATE TABLE` de `db.php` changent ; les pages, non.
- Tester la logique de connexion sans base, depuis le dossier de l'étape :
  `php -r 'require "models/User.php"; $u = new User(1, "a@b.fr", password_hash("secret", PASSWORD_DEFAULT)); var_dump($u->verifierMotDePasse("secret"));'`
  affiche `bool(true)`.

## Pièges

- **Un repository qui renvoie des tableaux.** On perd tout l'intérêt de `User`. Le repository doit toujours
  renvoyer des objets (`hydrater()` fait la conversion en un seul endroit).
- **Un modèle qui produit du HTML ou lit `$_POST`.** Le modèle ne sait pas qu'il est dans une application web.
  Il doit fonctionner tel quel depuis un script en ligne de commande.
- **Oublier le `?` de `?User`.** `findByEmail()` renvoie `null` quand l'utilisateur n'existe pas ; le type
  de retour doit le dire, et l'appelant doit le tester.

## Ce qui reste imparfait

Regardez le haut de `register.php` :

```php
require __DIR__ . '/includes/render.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/models/User.php';
require __DIR__ . '/models/UserRepository.php';
```

Quatre `require`, et chaque nouvelle classe en ajoute un dans chaque page qui l'utilise. Étape 6.

## Lancer et vérifier

```bash
cp .env.example .env
php -S localhost:8000
./verifier.sh 05               # depuis la racine du dépôt
```

**Questions du plan** : `PLAN.md`, étape 5.
