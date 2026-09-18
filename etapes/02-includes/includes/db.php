<?php
// Connexion à la base de données, écrite une seule fois pour tout le site.
// Chaque page qui l'inclut dispose ensuite de la variable $pdo.
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL
)');
