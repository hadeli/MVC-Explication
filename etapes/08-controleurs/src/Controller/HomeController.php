<?php

namespace App\Controller;

final class HomeController
{
    public function index(): void
    {
        render('home');
    }
}
