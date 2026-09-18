<?php
// Connexion à la base de données. Plus aucune valeur en dur : tout vient du fichier .env.
require_once __DIR__ . '/env.php';
chargerEnv(dirname(__DIR__) . '/.env');

$dsn = env('DB_DSN') ?? throw new RuntimeException('DB_DSN manquant dans .env');

// Un chemin SQLite relatif est résolu depuis la racine du projet, pas depuis le dossier courant.
if (str_starts_with($dsn, 'sqlite:') && !str_starts_with(substr($dsn, 7), '/')) {
    $dsn = 'sqlite:' . dirname(__DIR__) . '/' . substr($dsn, 7);
}

$pdo = new PDO($dsn, env('DB_USER') ?: null, env('DB_PASSWORD') ?: null);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL
)');
