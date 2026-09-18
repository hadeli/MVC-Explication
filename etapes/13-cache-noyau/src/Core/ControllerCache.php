<?php

namespace App\Core;

use RuntimeException;

// Mémorise dans un fichier PHP le résultat du balayage (ControllerFinder) et de l'analyse des constructeurs
// (Container::analyser) : classe => types de son constructeur. Même principe que Template à l'étape 10 :
// un travail déterministe et coûteux est fait une fois, puis relu par un simple `require` tant que la
// source n'a pas changé.
final class ControllerCache
{
    public function __construct(
        private readonly string $dossier,   // src/Controller
        private readonly string $namespace, // App\Controller
        private readonly string $fichier,   // cache/controleurs.php
    ) {
    }

    /** @return array<class-string<ControllerInterface>, list<class-string>> classe => plan de construction */
    public function charger(): array
    {
        if ($this->estFrais()) {
            return require $this->fichier;
        }

        $plans = [];
        foreach (ControllerFinder::trouver($this->dossier, $this->namespace) as $classe) {
            $plans[$classe] = Container::analyser($classe);
        }

        $this->ecrire($plans);

        return $plans;
    }

    // Le cache est périmé si le dossier a changé (fichier ajouté, supprimé ou renommé : c'est ce que
    // la date du dossier enregistre) ou si un fichier a été modifié (constructeur changé, par exemple).
    private function estFrais(): bool
    {
        if (!is_file($this->fichier)) {
            return false;
        }

        $dateCache = filemtime($this->fichier);

        if ($dateCache < filemtime($this->dossier)) {
            return false;
        }

        foreach (glob($this->dossier . '/*.php') as $source) {
            if ($dateCache < filemtime($source)) {
                return false;
            }
        }

        return true;
    }

    private function ecrire(array $plans): void
    {
        $dossierCache = dirname($this->fichier);

        if (!is_dir($dossierCache) && !mkdir($dossierCache, 0777, true)) {
            throw new RuntimeException("Impossible de créer le dossier de cache $dossierCache");
        }

        // var_export() produit du PHP valide : le fichier se relit avec require, sans parser quoi que ce soit.
        $contenu = "<?php\n"
            . "// Généré automatiquement depuis {$this->dossier}. Ne pas modifier : régénéré dès que le dossier change.\n"
            . 'return ' . var_export($plans, true) . ";\n";

        file_put_contents($this->fichier, $contenu);
    }
}
