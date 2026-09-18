<?php

namespace App\Core;

// Une réponse HTTP : statut, en-têtes, corps. Rien n'est envoyé avant l'appel à send().
final class Response
{
    public function __construct(
        public readonly int $statut = 200,
        public readonly string $corps = '',
        public readonly array $entetes = [],
    ) {
    }

    public static function redirect(string $url): self
    {
        return new self(302, '', ['Location' => $url]);
    }

    public function send(): void
    {
        http_response_code($this->statut);
        foreach ($this->entetes as $nom => $valeur) {
            header("$nom: $valeur");
        }
        echo $this->corps;
    }
}
