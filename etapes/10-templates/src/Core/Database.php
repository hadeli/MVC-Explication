<?php

namespace App\Core;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    // Une seule connexion par requête, créée à la première demande.
    public static function connexion(string $racine): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dsn = Env::get('DB_DSN') ?? throw new RuntimeException('DB_DSN manquant dans .env');

        if (str_starts_with($dsn, 'sqlite:') && !str_starts_with(substr($dsn, 7), '/')) {
            $dsn = 'sqlite:' . $racine . '/' . substr($dsn, 7);
        }

        $pdo = new PDO($dsn, Env::get('DB_USER') ?: null, Env::get('DB_PASSWORD') ?: null);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL
        )');

        return self::$pdo = $pdo;
    }
}
