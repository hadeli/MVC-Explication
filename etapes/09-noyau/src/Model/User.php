<?php

namespace App\Model;
// Représente une ligne de la table users. Objet immuable : on ne modifie pas un User, on en recrée un.
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $passwordHash,
    ) {
    }

    public function verifierMotDePasse(string $motDePasse): bool
    {
        return password_verify($motDePasse, $this->passwordHash);
    }
}
