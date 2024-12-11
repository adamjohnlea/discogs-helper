<?php

declare(strict_types=1);

namespace DiscogsHelper;

final class Session
{
    public static function initialize(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function setMessage(string $message): void
    {
        self::initialize();
        $_SESSION['auth_message'] = $message;
    }

    public static function getMessage(): ?string
    {
        self::initialize();
        $message = $_SESSION['auth_message'] ?? null;
        unset($_SESSION['auth_message']);
        return $message;
    }

    public static function hasMessage(): bool
    {
        self::initialize();
        return isset($_SESSION['auth_message']);
    }

    public static function setErrors(array $errors): void
    {
        self::initialize();
        $_SESSION['profile_errors'] = $errors;
    }

    public static function getErrors(): array
    {
        self::initialize();
        $errors = $_SESSION['profile_errors'] ?? [];
        unset($_SESSION['profile_errors']);
        return $errors;
    }

    public static function hasErrors(): bool
    {
        self::initialize();
        return !empty($_SESSION['profile_errors']);
    }
}