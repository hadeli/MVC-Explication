<?php
session_start();
require __DIR__ . '/includes/db.php';

$erreurs = [];
$succes = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = 'L\'adresse email est invalide.';
    }
    if (strlen($password) < 8) {
        $erreurs[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $confirmation) {
        $erreurs[] = 'Les deux mots de passe ne correspondent pas.';
    }

    if (empty($erreurs)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $erreurs[] = 'Un compte existe déjà avec cet email.';
        }
    }

    if (empty($erreurs)) {
        $stmt = $pdo->prepare('INSERT INTO users (email, password) VALUES (:email, :password)');
        $stmt->execute([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $succes = true;
    }
}

$titre = 'Inscription';
require __DIR__ . '/includes/header.php';
?>
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
<?php require __DIR__ . '/includes/footer.php'; ?>
