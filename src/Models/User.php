<?php

declare(strict_types=1);

namespace DiscogsHelper\Models;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly string $createdAt,
        public readonly string $updatedAt
    ) {
    }

    public static function create(string $username, string $email, string $password): self
    {
        return new self(
            id: 0, // Will be set by database
            username: $username,
            email: $email,
            passwordHash: password_hash($password, PASSWORD_DEFAULT),
            createdAt: date('Y-m-d H:i:s'),
            updatedAt: date('Y-m-d H:i:s')
        );
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }
}