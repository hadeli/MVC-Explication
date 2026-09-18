<?php
// Début de page commun : doctype, <head> et navigation.
// Attend une variable $titre définie par la page qui l'inclut.
$utilisateurConnecte = $_SESSION['utilisateur'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($titre) ?></title>
</head>
<body>
    <nav>
        <a href="index.php">Accueil</a>
        <?php if ($utilisateurConnecte === null): ?>
            | <a href="login.php">Connexion</a>
            | <a href="register.php">Inscription</a>
        <?php else: ?>
            | <a href="index.php?action=logout">Déconnexion</a>
        <?php endif; ?>
    </nav>
