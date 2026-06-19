<?php

namespace App\Support;

/**
 * Pantalles del dashboard que es poden atorgar a un rol de personal.
 *
 * El panell d'usuaris (`usuaris`) NO és atorgable: només els administradors hi
 * accedeixen, perquè són els qui gestionen rols i permisos.
 */
class Screens
{
    /**
     * Claus de pantalla atorgables, en l'ordre que es mostren al dashboard.
     *
     * @var list<string>
     */
    public const ALL = [
        'hores',
        'informacio',
        'serveis',
        'stock',
        'empleats',
        'posts',
        'imatges',
        'reserves',
        'reviews',
        'cancellacions',
        'xat',
    ];

    /**
     * Indica si una clau de pantalla és vàlida (atorgable).
     */
    public static function isValid(string $screen): bool
    {
        return in_array($screen, self::ALL, true);
    }

    /**
     * Filtra una llista deixant només les claus de pantalla vàlides i úniques.
     *
     * @param  array<int, mixed>  $screens
     * @return list<string>
     */
    public static function sanitize(array $screens): array
    {
        return array_values(array_unique(array_filter(
            $screens,
            fn ($s): bool => is_string($s) && self::isValid($s),
        )));
    }
}
