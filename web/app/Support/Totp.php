<?php

namespace App\Support;

/**
 * Minimal RFC 6238 TOTP implementation (SHA-1, 6 digits, 30s period).
 * Compatible with Google Authenticator, Aegis, 1Password, etc.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^[0-9]{6}$/', $code)) {
            return false;
        }
        $counter = intdiv(time(), self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $counter + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD
        );
    }

    private static function code(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        $binary = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binary, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            (ord($hash[$offset + 1]) << 16) |
            (ord($hash[$offset + 2]) << 8) |
            ord($hash[$offset + 3])
        ) % (10 ** self::DIGITS);
        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string
    {
        $out = '';
        $bits = 0;
        $value = 0;
        foreach (str_split($data) as $char) {
            $value = ($value << 8) | ord($char);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= self::ALPHABET[($value >> $bits) & 31];
            }
        }
        if ($bits > 0) {
            $out .= self::ALPHABET[($value << (5 - $bits)) & 31];
        }
        return $out;
    }

    private static function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(rtrim($encoded, '='));
        $out = '';
        $bits = 0;
        $value = 0;
        foreach (str_split($encoded) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                throw new \InvalidArgumentException('Invalid base32 input');
            }
            $value = ($value << 5) | $pos;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($value >> $bits) & 0xff);
            }
        }
        return $out;
    }
}
