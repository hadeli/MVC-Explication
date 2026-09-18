<?php

namespace App\Controller;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

// GET /login : affiche le formulaire de connexion.
final class LoginFormController extends AbstractController
{
    use FormTrait;

    public function __construct(private readonly View $view)
    {
    }

    protected static function verb(): string
    {
        return 'GET';
    }

    protected static function path(): string
    {
        return '/login';
    }

    public function handle(Request $request): Response
    {
        if ($request->session('utilisateur') !== null) {
            return Response::redirect('/');
        }

        return $this->rendreFormulaire('login', 'Connexion', '', ['erreur' => null]);
    }
}
