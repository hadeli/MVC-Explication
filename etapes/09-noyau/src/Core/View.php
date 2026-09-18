<?php

namespace App\Core;

// Rend une vue à l'intérieur du layout commun et renvoie une Response.
final class View
{
    // $partage : données disponibles dans toutes les vues (ex. l'utilisateur connecté pour la navigation).
    public function __construct(private readonly string $dossier, private array $partage = [])
    {
    }

    public function partager(string $cle, mixed $valeur): void
    {
        $this->partage[$cle] = $valeur;
    }

    public function render(string $vue, array $donnees = [], int $statut = 200): Response
    {
        $donnees += $this->partage;

        // 1. la vue, capturée dans un tampon      2. le layout, qui reçoit le résultat dans $contenu
        $contenu = $this->capturer($vue . '.php', $donnees);
        $html = $this->capturer('layout.php', $donnees + ['contenu' => $contenu]);

        return new Response($statut, $html);
    }

    private function capturer(string $fichier, array $donnees): string
    {
        extract($donnees, EXTR_SKIP);
        ob_start();
        require $this->dossier . '/' . $fichier;

        return ob_get_clean();
    }
}
