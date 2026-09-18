<?php
// Page d'inscription "classique" : connexion à la base, validation, insertion et HTML au même endroit.
session_start();

// Connexion à la base de données (ce bloc est dupliqué dans login.php).
$pdo = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL
)');

$erreurs = [];
$succes = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    // Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = 'L\'adresse email est invalide.';
    }
    if (strlen($password) < 8) {
        $erreurs[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $confirmation) {
        $erreurs[] = 'Les deux mots de passe ne correspondent pas.';
    }

    // Vérifie que l'email n'est pas déjà utilisé
    if (empty($erreurs)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $erreurs[] = 'Un compte existe déjà avec cet email.';
        }
    }

    // Insertion
    if (empty($erreurs)) {
        $stmt = $pdo->prepare('INSERT INTO users (email, password) VALUES (:email, :password)');
        $stmt->execute([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $succes = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription</title>
</head>
<body>
    <nav>
        <a href="index.php">Accueil</a>
        | <a href="login.php">Connexion</a>
        | <a href="register.php">Inscription</a>
    </nav>

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
</body>
</html>
