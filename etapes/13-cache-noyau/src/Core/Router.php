<?php

namespace App\Core;

use InvalidArgumentException;

// Reçoit des NOMS de classes (trouvés par ControllerFinder), demande à chacune si elle prend en charge la
// requête, et fait construire l'élue par le Container. Il n'écrit plus jamais `new`.
final class Router
{
    /** @param list<class-string<ControllerInterface>> $controleurs */
    public function __construct(
        private readonly array $controleurs,
        private readonly Container $container,
        private readonly View $view,
    ) {
        foreach ($controleurs as $classe) {
            if (!is_a($classe, ControllerInterface::class, true)) {
                throw new InvalidArgumentException("$classe n'implémente pas " . ControllerInterface::class);
            }
        }
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->controleurs as $classe) {
            if ($classe::support($request)) {
                return $this->container->creer($classe)->handle($request);
            }
        }

        return $this->view->render('404', ['chemin' => $request->path], 404);
    }
}
