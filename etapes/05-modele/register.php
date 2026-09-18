<?php
session_start();
require __DIR__ . '/includes/render.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/models/User.php';
require __DIR__ . '/models/UserRepository.php';

$repository = new UserRepository($pdo);
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
    if (empty($erreurs) && $repository->emailExists($email)) {
        $erreurs[] = 'Un compte existe déjà avec cet email.';
    }

    if (empty($erreurs)) {
        $repository->create($email, $password);
        $succes = true;
    }
}

render('register', ['erreurs' => $erreurs, 'succes' => $succes, 'email' => $email]);
