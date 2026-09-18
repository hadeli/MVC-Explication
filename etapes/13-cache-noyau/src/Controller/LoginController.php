<?php

namespace App\Controller;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Model\UserRepository;

// POST /login : vérifie les identifiants, ouvre la session.
final class LoginController extends AbstractController
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
        return '/login';
    }

    public function handle(Request $request): Response
    {
        $email = $this->lireEmail($request);
        $password = $request->post('password', '');

        $utilisateur = $this->repository->findByEmail($email);

        if ($utilisateur !== null && $utilisateur->verifierMotDePasse($password)) {
            $request->setSession('utilisateur', ['id' => $utilisateur->id, 'email' => $utilisateur->email]);

            return Response::redirect('/');
        }

        return $this->rendreFormulaire('login', 'Connexion', $email, ['erreur' => 'Email ou mot de passe incorrect.']);
    }
}
