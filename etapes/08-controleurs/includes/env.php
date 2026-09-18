<?php
// Charge un fichier .env (lignes CLE=valeur) dans $_ENV.
// Lignes vides et commentaires (#) ignorés. Les guillemets autour d'une valeur sont retirés.
// Une variable déjà présente dans l'environnement du système n'est pas écrasée.
function chargerEnv(string $chemin): void
{
    if (!is_file($chemin)) {
        throw new RuntimeException(
            "Fichier de configuration introuvable : $chemin\n"
            . "Copiez .env.example vers .env puis adaptez les valeurs."
        );
    }

    foreach (file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || str_starts_with($ligne, '#') || !str_contains($ligne, '=')) {
            continue;
        }

        [$cle, $valeur] = explode('=', $ligne, 2);
        $cle = trim($cle);
        $valeur = trim($valeur);

        if (strlen($valeur) >= 2 && ($valeur[0] === '"' || $valeur[0] === "'") && $valeur[-1] === $valeur[0]) {
            $valeur = substr($valeur, 1, -1);
        }

        if (getenv($cle) === false && !array_key_exists($cle, $_ENV)) {
            $_ENV[$cle] = $valeur;
            putenv("$cle=$valeur");
        }
    }
}

function env(string $cle, ?string $defaut = null): ?string
{
    return $_ENV[$cle] ?? $defaut;
}
