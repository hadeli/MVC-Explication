<?php
// Autoloader du projet. PHP appelle cette fonction quand il rencontre une classe qu'il ne connaît pas encore.
// Convention (identique à PSR-4) : App\Model\UserRepository  ->  src/Model/UserRepository.php
spl_autoload_register(function (string $classe): void {
    $prefixe = 'App\\';

    if (!str_starts_with($classe, $prefixe)) {
        return; // Pas une classe du projet : on laisse la main à d'éventuels autres autoloaders.
    }

    $relatif = substr($classe, strlen($prefixe));
    $fichier = __DIR__ . '/src/' . str_replace('\\', '/', $relatif) . '.php';

    if (is_file($fichier)) {
        require $fichier;
    }
    // Sinon on ne fait rien : PHP lèvera "Class App\... not found", ce qui indique clairement le fichier attendu.
});
