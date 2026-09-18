<?php

namespace App\Controller;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Model\UserRepository;

// POST /register : valide le formulaire, crée le compte.
final class RegisterController extends AbstractController
{
    use FormTrait;

    public function __construct(
        private readonly UserRepository $repository,
        private readonly View $view,
    ) {
    }

    protected static function verb(): string
    {
        return 'POST';
    }

    protected static function path(): string
    {
        return '/register';
    }

    public function handle(Request $request): Response
    {
        $email = $this->lireEmail($request);
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

        return $this->rendreFormulaire('register', 'Inscription', $email, [
            'erreurs' => $erreurs,
            'succes' => empty($erreurs),
        ]);
    }
}
