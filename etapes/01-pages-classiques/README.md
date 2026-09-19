# Étape 1 : les pages classiques

> **Objectif** : disposer d'un site qui fonctionne, écrit comme on écrit naturellement ses premières pages PHP,
> pour pouvoir observer précisément ce qui pose problème avant de changer quoi que ce soit.

Ces trois fichiers sont identiques à ceux de la racine du dépôt : c'est le point de départ des élèves.

## Arborescence

```
01-pages-classiques/
├── index.php          accueil, déconnexion
├── login.php          formulaire + traitement de la connexion
├── register.php       formulaire + traitement de l'inscription
└── database.sqlite    créée à la première visite de login.php ou register.php (non versionnée)
```

## Ce que fait chaque page

| Fichier | Responsabilités, dans l'ordre du fichier |
|---|---|
| `index.php` | Démarre la session, lit l'utilisateur connecté, traite `?action=logout` (détruit la session, redirige), affiche la page |
| `register.php` | Démarre la session, ouvre la connexion PDO, crée la table si besoin, lit le POST, valide (email, 8 caractères minimum, confirmation), vérifie l'unicité, hache le mot de passe, insère, affiche le formulaire ou le message de succès |
| `login.php` | Démarre la session, redirige si déjà connecté, ouvre la connexion PDO, lit le POST, cherche l'utilisateur, vérifie le mot de passe, remplit la session, redirige ou affiche l'erreur |

## Comment fonctionne une requête

```
Navigateur ── POST /login.php ──► serveur web ──► login.php ──► HTML
```

Le serveur web fait correspondre directement l'URL à un fichier sur le disque. Le fichier s'exécute de haut
en bas : d'abord le PHP qui calcule, puis le HTML qui affiche, avec des allers-retours entre les deux
(`<?php if ... ?>`, `<?= ... ?>`).

## Ce qui est déjà bien

Ce code n'est pas « mauvais ». Il applique déjà des règles qu'on gardera jusqu'au bout :

- les mots de passe sont hachés avec `password_hash()` et vérifiés avec `password_verify()`, jamais comparés en clair ;
- toutes les requêtes SQL sont préparées : aucune donnée utilisateur n'est concaténée dans du SQL ;
- toute donnée affichée passe par `htmlspecialchars()` ;
- après une connexion réussie, on redirige (`header('Location: ...')` puis `exit`) au lieu d'afficher directement la page : recharger ne renvoie pas le formulaire.

## Ce qui pose problème

Ouvrez `login.php` et `register.php` côte à côte.

1. **Duplication.** Le bloc de connexion PDO avec son `CREATE TABLE` est copié dans `login.php` et
   `register.php` ; le `session_start()`, le `<nav>` et le squelette HTML le sont dans les trois fichiers.
   Changer le nom de la base, c'est modifier deux fichiers et espérer n'en oublier aucun.
2. **Mélange des responsabilités.** `register.php` fait neuf choses différentes. Un graphiste qui veut
   retoucher le formulaire ouvre un fichier qui contient des requêtes SQL.
3. **Une URL = un fichier.** Une fois inscrit, tapez `http://localhost:8000/database.sqlite` : le serveur vous envoie la base
   de données. Tout ce qui est dans le dossier est public.
4. **Coût marginal constant.** Ajouter une page « profil » demande de copier-coller une vingtaine de lignes.
   La dixième page coûte aussi cher que la première.

Aucun de ces problèmes n'empêche le site de fonctionner. Ils rendent seulement chaque modification future
plus risquée et plus lente. C'est cela que le parcours va corriger, sans jamais changer le comportement.

## Lancer

```bash
php -S localhost:8000
```

puis ouvrir http://localhost:8000/index.php. Pour repartir de zéro : supprimer `database.sqlite`.

## Vérifier

Depuis la racine du dépôt : `./verifier.sh 01`. À la main : s'inscrire, se connecter, constater le
« Bonjour », se déconnecter. Ce parcours est le contrat que toutes les étapes suivantes devront respecter.

**Questions du plan** : `PLAN.md`, étape 1.
