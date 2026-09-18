<?php

namespace App\Core;

use LogicException;
use ReflectionClass;
use ReflectionNamedType;

// Construit un objet en devinant ses dépendances : pour chaque paramètre du constructeur, le type déclaré
// dit quel objet fournir. Le « plan » d'une classe est la liste de ces types ; il peut venir du cache.
final class Container
{
    /**
     * @param array<class-string, object>             $services objets déjà construits, indexés par leur classe
     * @param array<class-string, list<class-string>> $plans    classe => types de son constructeur, déjà analysés
     */
    public function __construct(
        private readonly array $services = [],
        private array $plans = [],
    ) {
    }

    public function creer(string $classe): object
    {
        // Un service enregistré (View, UserRepository) est partagé : toujours la même instance.
        if (isset($this->services[$classe])) {
            return $this->services[$classe];
        }

        // Plan connu (cache) : aucune réflexion. Sinon on analyse, et on garde le résultat pour la requête.
        $this->plans[$classe] ??= self::analyser($classe);

        $arguments = array_map(fn(string $type) => $this->creer($type), $this->plans[$classe]); // récursif

        return new $classe(...$arguments);
    }

    /**
     * Lit le constructeur d'une classe et renvoie les types de ses paramètres, dans l'ordre.
     * C'est la seule méthode qui utilise la réflexion ; ControllerCache l'appelle pour remplir le cache.
     *
     * @return list<class-string>
     */
    public static function analyser(string $classe): array
    {
        $constructeur = (new ReflectionClass($classe))->getConstructor();
        $types = [];

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

            $types[] = $type->getName();
        }

        return $types;
    }
}
