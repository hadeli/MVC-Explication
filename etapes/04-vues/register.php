<?php
session_start();
require __DIR__ . '/includes/render.php';
require __DIR__ . '/includes/db.php';

$erreurs = [];
$succes = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = 'L\'adresse email est invalide.';
    }
    if (strlen($password) < 8) {
        $erreurs[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $confirmation) {
        $erreurs[] = 'Les deux mots de passe ne correspondent pas.';
    }

    if (empty($erreurs)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $erreurs[] = 'Un compte existe déjà avec cet email.';
        }
    }

    if (empty($erreurs)) {
        $stmt = $pdo->prepare('INSERT INTO users (email, password) VALUES (:email, :password)');
        $stmt->execute([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $succes = true;
    }
}

render('register', [
    'erreurs' => $erreurs,
    'succes' => $succes,
    'email' => $email,
]);
