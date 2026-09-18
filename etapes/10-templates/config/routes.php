<?php

use App\Controller\AuthController;
use App\Controller\HomeController;

// [méthode HTTP, chemin, [classe du contrôleur, méthode à appeler]]
return [
    ['GET',  '/',         [HomeController::class, 'index']],
    ['GET',  '/login',    [AuthController::class, 'loginForm']],
    ['POST', '/login',    [AuthController::class, 'login']],
    ['GET',  '/register', [AuthController::class, 'registerForm']],
    ['POST', '/register', [AuthController::class, 'register']],
    ['GET',  '/logout',   [AuthController::class, 'logout']],
];
