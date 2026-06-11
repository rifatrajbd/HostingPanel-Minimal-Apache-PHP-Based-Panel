<?php

declare(strict_types=1);

namespace Panel\Support;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function validate(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION[self::KEY])
            && hash_equals($_SESSION[self::KEY], $token);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }
}
