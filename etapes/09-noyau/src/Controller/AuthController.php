<?php

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Model\UserRepository;

// Ne touche ni aux superglobales ni à header() : il reçoit une Request et renvoie une Response.
final class AuthController
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly View $view,
    ) {
    }

    public function loginForm(Request $request): Response
    {
        if ($request->session('utilisateur') !== null) {
            return Response::redirect('/');
        }

        return $this->view->render('login', ['titre' => 'Connexion', 'erreur' => null, 'email' => '']);
    }

    public function login(Request $request): Response
    {
        $email = trim($request->post('email', ''));
        $password = $request->post('password', '');

        $utilisateur = $this->repository->findByEmail($email);

        if ($utilisateur !== null && $utilisateur->verifierMotDePasse($password)) {
            $request->setSession('utilisateur', ['id' => $utilisateur->id, 'email' => $utilisateur->email]);

            return Response::redirect('/');
        }

        return $this->view->render('login', [
            'titre' => 'Connexion',
            'erreur' => 'Email ou mot de passe incorrect.',
            'email' => $email,
        ]);
    }

    public function registerForm(Request $request): Response
    {
        return $this->view->render('register', ['titre' => 'Inscription', 'erreurs' => [], 'succes' => false, 'email' => '']);
    }

    public function register(Request $request): Response
    {
        $email = trim($request->post('email', ''));
        $password = $request->post('password', '');
        $confirmation = $request->post('confirmation', '');
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

        return $this->view->render('register', [
            'titre' => 'Inscription',
            'erreurs' => $erreurs,
            'succes' => empty($erreurs),
            'email' => $email,
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->detruireSession();

        return Response::redirect('/');
    }
}
