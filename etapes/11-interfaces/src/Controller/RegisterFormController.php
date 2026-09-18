<?php

namespace App\Controller;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

// GET /register : affiche le formulaire d'inscription.
final class RegisterFormController extends AbstractController
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
        return '/register';
    }

    public function handle(Request $request): Response
    {
        return $this->rendreFormulaire('register', 'Inscription', '', ['erreurs' => [], 'succes' => false]);
    }
}
