<?php
// src/Auth.php

declare(strict_types=1);

namespace DiscogsHelper;

use RuntimeException;

final class Auth
{
    private ?User $currentUser = null;

    public function __construct(
        private Database $db
    ) {
        session_start();
    }

    public function register(string $username, string $email, string $password): User
    {
        // Check if username exists
        if ($this->db->findUserByUsername($username)) {
            throw new RuntimeException('Username already exists');
        }

        // Check if email exists
        if ($this->db->findUserByEmail($email)) {
            throw new RuntimeException('Email already exists');
        }

        $user = User::create($username, $email, $password);

        $id = $this->db->createUser(
            $user->username,
            $user->email,
            $user->passwordHash
        );

        return new User(
            id: $id,
            username: $user->username,
            email: $user->email,
            passwordHash: $user->passwordHash,
            createdAt: $user->createdAt,
            updatedAt: $user->updatedAt
        );
    }

    public function login(string $username, string $password): bool
    {
        $userData = $this->db->findUserByUsername($username);

        if (!$userData) {
            return false;
        }

        $user = new User(
            id: (int)$userData['id'],
            username: $userData['username'],
            email: $userData['email'],
            passwordHash: $userData['password_hash'],
            createdAt: $userData['created_at'],
            updatedAt: $userData['updated_at']
        );

        if (!$user->verifyPassword($password)) {
            return false;
        }

        $_SESSION['user_id'] = $user->id;
        $this->currentUser = $user;

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['user_id']);
        $this->currentUser = null;
        session_destroy();
    }

    public function getCurrentUser(): ?User
    {
        if ($this->currentUser) {
            return $this->currentUser;
        }

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $userData = $this->db->findUserById($_SESSION['user_id']);

        if (!$userData) {
            unset($_SESSION['user_id']);
            return null;
        }

        $this->currentUser = new User(
            id: (int)$userData['id'],
            username: $userData['username'],
            email: $userData['email'],
            passwordHash: $userData['password_hash'],
            createdAt: $userData['created_at'],
            updatedAt: $userData['updated_at']
        );

        return $this->currentUser;
    }

    public function isLoggedIn(): bool
    {
        return $this->getCurrentUser() !== null;
    }
}