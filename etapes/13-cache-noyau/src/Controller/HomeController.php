<?php

namespace App\Controller;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

// GET /  : la page d'accueil.
final class HomeController extends AbstractController
{
    public function __construct(private readonly View $view)
    {
    }

    protected static function verb(): string
    {
        return 'GET';
    }

    protected static function path(): string
    {
        return '/';
    }

    public function handle(Request $request): Response
    {
        return $this->view->render('home', ['titre' => 'Accueil']);
    }
}
