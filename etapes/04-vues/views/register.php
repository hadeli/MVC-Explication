<?php $titre = 'Inscription'; require __DIR__ . '/partials/header.php'; ?>
    <h1>Inscription</h1>

    <?php if ($succes): ?>
        <p>Votre compte a été créé. <a href="login.php">Connectez-vous</a>.</p>
    <?php else: ?>
        <?php if (!empty($erreurs)): ?>
            <ul>
                <?php foreach ($erreurs as $erreur): ?>
                    <li><?= htmlspecialchars($erreur) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="register.php">
            <p>
                <label for="email">Email</label><br>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </p>
            <p>
                <label for="password">Mot de passe</label><br>
                <input type="password" id="password" name="password" required>
            </p>
            <p>
                <label for="confirmation">Confirmation du mot de passe</label><br>
                <input type="password" id="confirmation" name="confirmation" required>
            </p>
            <button type="submit">S'inscrire</button>
        </form>
    <?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
