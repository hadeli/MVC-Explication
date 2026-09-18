<?php

namespace App\Core;

// Enveloppe les superglobales. Les contrôleurs ne touchent plus jamais $_GET, $_POST, $_SERVER ni $_SESSION.
final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $get,
        private readonly array $post,
        private array $session,
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            rtrim($path, '/') ?: '/',
            $_GET,
            $_POST,
            $_SESSION ?? [],
        );
    }

    public function get(string $cle, ?string $defaut = null): ?string
    {
        return $this->get[$cle] ?? $defaut;
    }

    public function post(string $cle, ?string $defaut = null): ?string
    {
        return $this->post[$cle] ?? $defaut;
    }

    public function session(string $cle, mixed $defaut = null): mixed
    {
        return $this->session[$cle] ?? $defaut;
    }

    public function setSession(string $cle, mixed $valeur): void
    {
        $this->session[$cle] = $valeur;
        $_SESSION[$cle] = $valeur;
    }

    public function detruireSession(): void
    {
        $this->session = [];
        session_destroy();
    }
}
