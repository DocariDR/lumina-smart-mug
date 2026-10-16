<?php

declare(strict_types=1);

namespace Lumina\Subscription;

final class EmailValidator
{
    /** RFC 5321 : longueur maximale d'une adresse email. */
    private const MAX_LENGTH = 254;

    public static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function isValid(string $email): bool
    {
        if ($email === '' || strlen($email) > self::MAX_LENGTH) {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}