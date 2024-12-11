<?php

declare(strict_types=1);

namespace DiscogsHelper\Security;

use DiscogsHelper\Exceptions\SecurityException;
use DiscogsHelper\Session;

final class Csrf
{
    private const int TOKEN_LENGTH = 32;

    public static function generate(): string
    {
        $token = Session::get('csrf_token');
        if ($token === null) {
            $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
            Session::set('csrf_token', $token);
        }
        return $token;
    }

    public static function validate(?string $token): bool
    {
        if ($token === null) {
            return false;
        }

        return hash_equals(Session::get('csrf_token') ?? '', $token);
    }

    public static function getFormField(): string
    {
        return sprintf(
            '<input type="hidden" name="csrf_token" value="%s">',
            htmlspecialchars(self::generate())
        );
    }

    public static function validateOrFail(?string $token): void
    {
        if (!self::validate($token)) {
            throw new SecurityException('Invalid CSRF token');
        }
    }
}