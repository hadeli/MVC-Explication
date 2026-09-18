<?php $titre = 'Accueil'; require __DIR__ . '/partials/header.php'; ?>
    <h1>Bienvenue</h1>

    <?php if ($utilisateurConnecte !== null): ?>
        <p>Bonjour <strong><?= htmlspecialchars($utilisateurConnecte['email']) ?></strong>, vous êtes connecté.</p>
    <?php else: ?>
        <p>Vous n'êtes pas connecté. <a href="login.php">Connectez-vous</a> ou <a href="register.php">créez un compte</a>.</p>
    <?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
