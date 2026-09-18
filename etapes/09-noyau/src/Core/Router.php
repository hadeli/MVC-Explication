<?php

namespace App\Core;

final class Router
{
    /** @param array<int, array{0: string, 1: string, 2: array{0: class-string, 1: string}}> $routes */
    public function __construct(
        private readonly array $routes,
        private readonly array $fabriques, // classe => fn(): objet
        private readonly View $view,
    ) {
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as [$methode, $chemin, [$classe, $action]]) {
            if ($methode === $request->method && $chemin === $request->path) {
                $controleur = ($this->fabriques[$classe])();

                return $controleur->$action($request);
            }
        }

        return $this->view->render('404', ['chemin' => $request->path], 404);
    }
}
