<?php

namespace App\Controller;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;

// GET /logout : ferme la session. N'a besoin ni de la vue ni du modèle.
final class LogoutController extends AbstractController
{
    protected static function verb(): string
    {
        return 'GET';
    }

    protected static function path(): string
    {
        return '/logout';
    }

    public function handle(Request $request): Response
    {
        $request->detruireSession();

        return Response::redirect('/');
    }
}
