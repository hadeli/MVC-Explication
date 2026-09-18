<?php
session_start();
require __DIR__ . '/includes/render.php';

if (isset($_SESSION['utilisateur'])) {
    header('Location: index.php');
    exit;
}

require __DIR__ . '/includes/db.php';

$erreur = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT id, email, password FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($utilisateur && password_verify($password, $utilisateur['password'])) {
        $_SESSION['utilisateur'] = [
            'id' => $utilisateur['id'],
            'email' => $utilisateur['email'],
        ];
        header('Location: index.php');
        exit;
    }

    $erreur = 'Email ou mot de passe incorrect.';
}

render('login', [
    'erreur' => $erreur,
    'email' => $email,
]);
