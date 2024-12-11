<?php

declare(strict_types=1);

namespace DiscogsHelper;

use DiscogsHelper\Logging\LogManager;

final class Logger
{
    private static ?LogManager $manager = null;

    public static function initialize(string $projectRoot): void
    {
        self::$manager = new LogManager($projectRoot . '/logs');
    }

    public static function log(string $message): void
    {
        if (self::$manager === null) {
            return;
        }

        // Prevent logging of sensitive patterns
        if (
            str_contains(strtolower($message), 'token') ||
            str_contains(strtolower($message), 'session') ||
            str_contains(strtolower($message), 'password') ||
            str_contains($message, '$_SESSION') ||
            str_contains($message, '$_POST')
        ) {
            return;
        }

        self::$manager->write($message);
    }

    public static function security(string $message): void
    {
        self::log('SECURITY: ' . $message);
    }

    public static function error(string $message): void
    {
        // Ensure we're not logging sensitive data in errors
        $message = preg_replace('/token[=:]\s*[^\s&]+/i', 'token=REDACTED', $message);
        $message = preg_replace('/session[=:]\s*[^\s&]+/i', 'session=REDACTED', $message);

        self::log('ERROR: ' . $message);
    }
}