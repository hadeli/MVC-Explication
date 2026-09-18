<?php

namespace App\Core;

use RuntimeException;

// Charge un fichier .env (lignes CLE=valeur) dans $_ENV. Voir l'étape 3 pour la version procédurale.
final class Env
{
    public static function charger(string $chemin): void
    {
        if (!is_file($chemin)) {
            throw new RuntimeException(
                "Fichier de configuration introuvable : $chemin\nCopiez .env.example vers .env puis adaptez les valeurs."
            );
        }

        foreach (file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
            $ligne = trim($ligne);
            if ($ligne === '' || str_starts_with($ligne, '#') || !str_contains($ligne, '=')) {
                continue;
            }

            [$cle, $valeur] = array_map('trim', explode('=', $ligne, 2));

            if (strlen($valeur) >= 2 && ($valeur[0] === '"' || $valeur[0] === "'") && $valeur[-1] === $valeur[0]) {
                $valeur = substr($valeur, 1, -1);
            }

            if (getenv($cle) === false && !array_key_exists($cle, $_ENV)) {
                $_ENV[$cle] = $valeur;
                putenv("$cle=$valeur");
            }
        }
    }

    public static function get(string $cle, ?string $defaut = null): ?string
    {
        return $_ENV[$cle] ?? $defaut;
    }
}
