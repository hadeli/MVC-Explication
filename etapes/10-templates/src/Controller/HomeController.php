<?php

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class HomeController
{
    public function __construct(private readonly View $view)
    {
    }

    public function index(Request $request): Response
    {
        return $this->view->render('home', ['titre' => 'Accueil']);
    }
}
