<?php
// Page d'accueil "classique" : la logique PHP et le HTML sont mélangés dans le même fichier.
session_start();

$utilisateur = $_SESSION['utilisateur'] ?? null;

// Déconnexion : on gère l'action directement ici, dans la page.
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Accueil</title>
</head>
<body>
    <nav>
        <a href="index.php">Accueil</a>
        <?php if ($utilisateur === null): ?>
            | <a href="login.php">Connexion</a>
            | <a href="register.php">Inscription</a>
        <?php else: ?>
            | <a href="index.php?action=logout">Déconnexion</a>
        <?php endif; ?>
    </nav>

    <h1>Bienvenue</h1>

    <?php if ($utilisateur !== null): ?>
        <p>Bonjour <strong><?= htmlspecialchars($utilisateur['email']) ?></strong>, vous êtes connecté.</p>
    <?php else: ?>
        <p>Vous n'êtes pas connecté. <a href="login.php">Connectez-vous</a> ou <a href="register.php">créez un compte</a>.</p>
    <?php endif; ?>
</body>
</html>
