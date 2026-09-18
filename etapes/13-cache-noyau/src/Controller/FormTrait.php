<?php

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;

// Code commun aux quatre contrôleurs de formulaire : lire l'email saisi, afficher (ou réafficher) le formulaire.
// Un trait n'est pas une classe : son code est COPIÉ dans chaque classe qui écrit `use FormTrait;`.
// Il suppose donc que cette classe possède une propriété $view (de type App\Core\View).
trait FormTrait
{
    private function lireEmail(Request $request): string
    {
        return trim($request->post('email', ''));
    }

    // Toujours le même contrat pour les vues de formulaire : un titre, l'email à réafficher ('' au premier
    // affichage), puis les données propres à chaque formulaire ('erreur' pour login, 'erreurs' et 'succes' pour register).
    private function rendreFormulaire(string $vue, string $titre, string $email, array $donnees = []): Response
    {
        return $this->view->render($vue, ['titre' => $titre, 'email' => $email] + $donnees);
    }
}
