<?php

declare(strict_types=1);

/**
 * panelctl validates everything itself — it must never trust its caller,
 * even though the panel validates too (defense in depth: this binary
 * runs as root).
 */
final class Validate
{
    public static function domain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        if (!preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            throw new InvalidArgumentException("Invalid domain: {$domain}");
        }
        return $domain;
    }

    public static function phpVersion(string $version): string
    {
        if (!in_array($version, PANELCTL_PHP_VERSIONS, true)) {
            throw new InvalidArgumentException("Unsupported PHP version: {$version}");
        }
        return $version;
    }

    public static function dbName(string $name): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]{2,31}$/', $name)) {
            throw new InvalidArgumentException("Invalid database name: {$name}");
        }
        return $name;
    }

    public static function dbUser(string $user): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]{2,31}$/', $user)) {
            throw new InvalidArgumentException("Invalid database user: {$user}");
        }
        return $user;
    }

    public static function email(string $address): string
    {
        $address = strtolower(trim($address));
        if (!preg_match('/^[a-z0-9._+-]{1,64}@([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $address)) {
            throw new InvalidArgumentException("Invalid email address: {$address}");
        }
        return $address;
    }

    /** Generated DB passwords are alphanumeric only — reject anything else. */
    public static function dbPassword(string $password): string
    {
        if (!preg_match('/^[A-Za-z0-9]{12,64}$/', $password)) {
            throw new InvalidArgumentException('DB password must be 12-64 alphanumeric characters.');
        }
        return $password;
    }

    public static function systemUserFor(string $domain): string
    {
        return 'web-' . substr((string) preg_replace('/[^a-z0-9]/', '', $domain), 0, 24);
    }
}
