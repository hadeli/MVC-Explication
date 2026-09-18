<h1>Connexion</h1>

<?php if ($erreur !== null): ?>
    <p><?= htmlspecialchars($erreur) ?></p>
<?php endif; ?>

<form method="post" action="/login">
    <p>
        <label for="email">Email</label><br>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
    </p>
    <p>
        <label for="password">Mot de passe</label><br>
        <input type="password" id="password" name="password" required>
    </p>
    <button type="submit">Se connecter</button>
</form>

<p>Pas encore de compte ? <a href="/register">Inscrivez-vous</a>.</p>
