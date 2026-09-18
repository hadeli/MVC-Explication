<?php
session_start();

if (isset($_SESSION['utilisateur'])) {
    header('Location: index.php');
    exit;
}

require __DIR__ . '/includes/db.php';

$erreur = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT id, email, password FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($utilisateur && password_verify($password, $utilisateur['password'])) {
        $_SESSION['utilisateur'] = [
            'id' => $utilisateur['id'],
            'email' => $utilisateur['email'],
        ];
        header('Location: index.php');
        exit;
    }

    $erreur = 'Email ou mot de passe incorrect.';
}

$titre = 'Connexion';
require __DIR__ . '/includes/header.php';
?>
    <h1>Connexion</h1>

    <?php if ($erreur !== null): ?>
        <p><?= htmlspecialchars($erreur) ?></p>
    <?php endif; ?>

    <form method="post" action="login.php">
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

    <p>Pas encore de compte ? <a href="register.php">Inscrivez-vous</a>.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>
