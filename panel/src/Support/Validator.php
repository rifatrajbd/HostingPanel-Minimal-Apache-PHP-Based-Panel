<?php

declare(strict_types=1);

namespace Panel\Support;

final class Validator
{
    public static function domain(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        return (bool) preg_match(
            '/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
            $domain
        );
    }

    public static function dbName(string $name): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]{2,31}$/', $name);
    }

    public static function dbUser(string $user): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]{2,31}$/', $user);
    }

    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && (bool) preg_match('/^[a-z0-9._+-]+@[a-z0-9.-]+$/i', $email);
    }

    public static function phpVersion(string $version, array $allowed): bool
    {
        return in_array($version, $allowed, true);
    }

    /** Generate a strong random password safe for MySQL/Dovecot. */
    public static function randomPassword(int $length = 24): string
    {
        $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }
}
