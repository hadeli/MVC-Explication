<?php

namespace App\Core;

use LogicException;
use ReflectionClass;
use ReflectionNamedType;

// Construit un objet en devinant ses dépendances : pour chaque paramètre du constructeur, le type déclaré
// dit quel objet fournir. Plus besoin d'écrire `fn() => new LoginController($repository, $view)`.
final class Container
{
    /** @param array<class-string, object> $services objets déjà construits, indexés par leur classe */
    public function __construct(private readonly array $services = [])
    {
    }

    public function creer(string $classe): object
    {
        // Un service enregistré (View, UserRepository) est partagé : toujours la même instance.
        if (isset($this->services[$classe])) {
            return $this->services[$classe];
        }

        $constructeur = (new ReflectionClass($classe))->getConstructor();
        $arguments = [];

        foreach ($constructeur?->getParameters() ?? [] as $parametre) {
            $type = $parametre->getType();

            // On ne sait fournir que des objets : un string, un int ou un type union n'ont pas de « bonne » valeur.
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                throw new LogicException(sprintf(
                    'Impossible de deviner le paramètre $%s de %s : enregistrez-le comme service.',
                    $parametre->getName(),
                    $classe,
                ));
            }

            $arguments[] = $this->creer($type->getName()); // récursif : une dépendance peut en avoir d'autres
        }

        return new $classe(...$arguments);
    }
}
