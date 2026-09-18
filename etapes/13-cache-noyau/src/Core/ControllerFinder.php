<?php

namespace App\Core;

use ReflectionClass;

// Trouve les contrôleurs tout seul : un fichier dans src/Controller/ = une classe App\Controller\<Fichier>.
// C'est exactement la convention d'autoload.php, lue dans l'autre sens (chemin → nom de classe).
final class ControllerFinder
{
    /** @return list<class-string<ControllerInterface>> triés par nom de fichier */
    public static function trouver(string $dossier, string $namespace): array
    {
        $classes = [];

        foreach (glob($dossier . '/*.php') as $fichier) {
            $classe = $namespace . '\\' . basename($fichier, '.php');

            // is_a(..., true) déclenche l'autoload puis vérifie le contrat ; un trait ou une classe abstraite
            // qui traînerait dans le dossier est ignoré (pas instanciable).
            if (is_a($classe, ControllerInterface::class, true) && (new ReflectionClass($classe))->isInstantiable()) {
                $classes[] = $classe;
            }
        }

        return $classes;
    }
}
