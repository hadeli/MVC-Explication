<?php
require __DIR__ . '/autoload.php';

use App\Model\UserRepository;

session_start();
require __DIR__ . '/includes/render.php';

if (isset($_SESSION['utilisateur'])) {
    header('Location: index.php');
    exit;
}

require __DIR__ . '/includes/db.php';

$repository = new UserRepository($pdo);
$erreur = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $utilisateur = $repository->findByEmail($email);

    if ($utilisateur !== null && $utilisateur->verifierMotDePasse($password)) {
        $_SESSION['utilisateur'] = ['id' => $utilisateur->id, 'email' => $utilisateur->email];
        header('Location: index.php');
        exit;
    }

    $erreur = 'Email ou mot de passe incorrect.';
}

render('login', ['erreur' => $erreur, 'email' => $email]);
