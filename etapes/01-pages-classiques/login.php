<?php
// Page de connexion "classique" : même structure que register.php, avec le même code dupliqué.
session_start();

// Déjà connecté ? On renvoie vers l'accueil.
if (isset($_SESSION['utilisateur'])) {
    header('Location: index.php');
    exit;
}

// Connexion à la base de données (copié-collé depuis register.php).
$pdo = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL
)');

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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion</title>
</head>
<body>
    <nav>
        <a href="index.php">Accueil</a>
        | <a href="login.php">Connexion</a>
        | <a href="register.php">Inscription</a>
    </nav>

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
</body>
</html>
