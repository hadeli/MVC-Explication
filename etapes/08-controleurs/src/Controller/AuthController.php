<?php

namespace App\Controller;

use App\Model\UserRepository;

// Regroupe toutes les actions liées à l'authentification.
// Le repository est reçu dans le constructeur : le contrôleur ne sait pas comment il est construit.
final class AuthController
{
    public function __construct(private readonly UserRepository $repository)
    {
    }

    public function loginForm(): void
    {
        if (isset($_SESSION['utilisateur'])) {
            $this->redirect('/');
        }

        render('login', ['erreur' => null, 'email' => '']);
    }

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $utilisateur = $this->repository->findByEmail($email);

        if ($utilisateur !== null && $utilisateur->verifierMotDePasse($password)) {
            $_SESSION['utilisateur'] = ['id' => $utilisateur->id, 'email' => $utilisateur->email];
            $this->redirect('/');
        }

        render('login', ['erreur' => 'Email ou mot de passe incorrect.', 'email' => $email]);
    }

    public function registerForm(): void
    {
        render('register', ['erreurs' => [], 'succes' => false, 'email' => '']);
    }

    public function register(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmation = $_POST['confirmation'] ?? '';
        $erreurs = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = 'L\'adresse email est invalide.';
        }
        if (strlen($password) < 8) {
            $erreurs[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        if ($password !== $confirmation) {
            $erreurs[] = 'Les deux mots de passe ne correspondent pas.';
        }
        if (empty($erreurs) && $this->repository->emailExists($email)) {
            $erreurs[] = 'Un compte existe déjà avec cet email.';
        }

        if (empty($erreurs)) {
            $this->repository->create($email, $password);
        }

        render('register', ['erreurs' => $erreurs, 'succes' => empty($erreurs), 'email' => $email]);
    }

    public function logout(): void
    {
        session_destroy();
        $this->redirect('/');
    }

    private function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}
