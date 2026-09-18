<?php

namespace App\Core;

use InvalidArgumentException;

// Plus de table de routes : le routeur demande à chaque classe de contrôleur si elle prend en charge
// la requête, puis n'instancie que celle qui a dit oui.
final class Router
{
    /** @param array<class-string<ControllerInterface>, callable(): ControllerInterface> $fabriques */
    public function __construct(
        private readonly array $fabriques, // classe => fn(): objet
        private readonly View $view,
    ) {
        // Le contrat est vérifié une fois pour toutes, au câblage : une classe qui n'implémente pas
        // l'interface est refusée ici, pas au moment où quelqu'un visite son URL.
        foreach ($fabriques as $classe => $fabrique) {
            if (!is_a($classe, ControllerInterface::class, true)) {
                throw new InvalidArgumentException("$classe n'implémente pas " . ControllerInterface::class);
            }
        }
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->fabriques as $classe => $fabrique) {
            if ($classe::support($request)) {
                return $fabrique()->handle($request);
            }
        }

        return $this->view->render('404', ['chemin' => $request->path], 404);
    }
}
