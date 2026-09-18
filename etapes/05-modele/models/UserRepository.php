<?php
// Tout l'accès à la table users passe par ici. Le reste du code ne connaît plus le SQL.
final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT id, email, password FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ligne ? $this->hydrater($ligne) : null;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);

        return $stmt->fetchColumn() !== false;
    }

    // Reçoit le mot de passe en clair et le hache lui-même : impossible d'enregistrer un mot de passe non haché.
    public function create(string $email, string $motDePasseClair): User
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password) VALUES (:email, :password)');
        $stmt->execute([
            'email' => $email,
            'password' => password_hash($motDePasseClair, PASSWORD_DEFAULT),
        ]);

        return new User((int) $this->pdo->lastInsertId(), $email, '');
    }

    private function hydrater(array $ligne): User
    {
        return new User((int) $ligne['id'], $ligne['email'], $ligne['password']);
    }
}
