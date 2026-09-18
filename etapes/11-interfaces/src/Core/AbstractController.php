<?php

namespace App\Core;

// Mutualise support() : un contrôleur qui répond à UN verbe sur UN chemin n'a plus qu'à dire lesquels.
// Le routeur ne connaît pas cette classe : il ne connaît que ControllerInterface, qu'elle implémente.
abstract class AbstractController implements ControllerInterface
{
    // Statiques, comme support() qui les appelle : on interroge la classe, pas un objet.
    abstract protected static function verb(): string;

    abstract protected static function path(): string;

    // static:: (et non self::) : la méthode est appelée sur la classe FILLE, c'est son verb()/path() qu'on veut.
    public static function support(Request $request): bool
    {
        return $request->method === static::verb() && $request->path === static::path();
    }

    // handle() reste abstraite : héritée de l'interface, chaque contrôleur doit l'écrire.
}
