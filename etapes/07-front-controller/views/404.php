<?php $titre = 'Page introuvable'; require __DIR__ . '/partials/header.php'; ?>
    <h1>Page introuvable</h1>
    <p>Aucune page ne correspond à <code><?= htmlspecialchars($chemin) ?></code>.</p>
    <p><a href="/">Retour à l'accueil</a></p>
<?php require __DIR__ . '/partials/footer.php'; ?>
