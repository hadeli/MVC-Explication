# Étape 3 : sortir les secrets du code

**Changement par rapport à l'étape 2** : la configuration quitte le code PHP.

| Fichier | Rôle |
|---|---|
| `.env.example` | Versionné. Liste les clés attendues avec des valeurs d'exemple. |
| `.env` | Non versionné (voir `.gitignore` à la racine). Valeurs propres à la machine. |
| `includes/env.php` | `chargerEnv()` lit le fichier dans `$_ENV` ; `env()` lit une clé. |
| `includes/db.php` | Ne contient plus aucune valeur : il lit `DB_DSN`, `DB_USER`, `DB_PASSWORD`. |

Première utilisation :

```bash
cp .env.example .env
php -S localhost:8000
```

Sans `.env`, la page s'arrête avec un message qui explique quoi faire. Passer sur MySQL ne demande de
modifier que `.env`.
