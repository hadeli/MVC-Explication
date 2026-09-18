<?php

namespace App\Core;

// Adaptateur entre les contrôleurs et le moteur de templates : renvoie toujours une Response.
final class View
{
    private readonly Template $template;

    public function __construct(string $dossierTemplates, string $dossierCache, private array $partage = [])
    {
        $this->template = new Template($dossierTemplates, $dossierCache);
    }

    public function partager(string $cle, mixed $valeur): void
    {
        $this->partage[$cle] = $valeur;
    }

    public function render(string $vue, array $donnees = [], int $statut = 200): Response
    {
        return new Response($statut, $this->template->rendre($vue, $donnees + $this->partage));
    }
}
