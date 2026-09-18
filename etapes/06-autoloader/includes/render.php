<?php
// Affiche une vue en lui transmettant des données.
// Les clés du tableau deviennent des variables locales dans la vue ($erreurs, $email...).
// La vue ne voit rien d'autre : ni $pdo, ni $_POST, ni $_SESSION.
function render(string $vue, array $donnees = []): void
{
    // Donnée commune à toutes les vues, pour la navigation.
    $donnees += ['utilisateurConnecte' => $_SESSION['utilisateur'] ?? null];

    extract($donnees, EXTR_SKIP);
    require dirname(__DIR__) . '/views/' . $vue . '.php';
}
