<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($titre ?? 'MVC') ?></title>
</head>
<body>
    <nav>
        <a href="/">Accueil</a>
        <?php if ($utilisateurConnecte === null): ?>
            | <a href="/login">Connexion</a>
            | <a href="/register">Inscription</a>
        <?php else: ?>
            | <a href="/logout">Déconnexion</a>
        <?php endif; ?>
    </nav>

<?= $contenu ?>
</body>
</html>
