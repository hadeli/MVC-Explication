<?php

namespace App\Core;

// Le contrat entre le routeur et les contrôleurs. Le routeur ne connaît QUE ces deux méthodes :
// il ne sait ni quelles classes existent, ni quelles URLs elles servent. Ce sont elles qui le lui disent.
interface ControllerInterface
{
    // « Sais-tu répondre à cette requête ? » Statique : on interroge la CLASSE, sans construire d'objet.
    // Ne doit rien faire d'autre que répondre oui ou non.
    public static function support(Request $request): bool;

    // « Alors réponds. » N'est appelée que sur le contrôleur dont support() a renvoyé true,
    // et c'est le seul qui est instancié.
    public function handle(Request $request): Response;
}
