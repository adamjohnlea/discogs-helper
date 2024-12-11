<?php
// src/Auth.php
declare(strict_types=1);

namespace DiscogsHelper;

use DiscogsHelper\Exceptions\RateLimitExceededException;
use DiscogsHelper\Security\RateLimiter;
use RuntimeException;

final class Auth
{
    private ?User $currentUser = null;

    public function __construct(
        private readonly Database $db
    ) {
        Session::initialize();
    }

    public function register(string $username, string $email, string $password): User
    {
        $username = trim($username);
        $email = trim($email);

        if ($this->db->findUserByUsername($username)) {
            Logger::security('Registration failed: username already exists');
            throw new RuntimeException('Username already exists');
        }

        if ($this->db->findUserByEmail($email)) {
            Logger::security('Registration failed: email already exists');
            throw new RuntimeException('Email already exists');
        }

        $user = User::create($username, $email, $password);
        $id = $this->db->createUser(
            $user->username,
            $user->email,
            $user->passwordHash
        );

        Logger::security('New user registration successful');

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
        $username = trim($username);
        $rateLimiter = new RateLimiter($username);

        try {
            $rateLimiter->check();
            Logger::security('Login attempt');

            $userData = $this->db->findUserByUsername($username);
            if (!$userData) {
                Logger::security('Login failed: invalid credentials');
                $rateLimiter->recordAttempt();
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
                Logger::security('Login failed: invalid credentials');
                $rateLimiter->recordAttempt();
                return false;
            }

            Logger::security('Login successful');
            $rateLimiter->reset();
            $_SESSION['user_id'] = $user->id;
            $this->currentUser = $user;

            return true;
        } catch (RateLimitExceededException $e) {
            Logger::security('Login failed: rate limit exceeded');
            throw $e;
        }
    }

    public function logout(): void
    {
        Logger::security('Logout initiated');

        // Clear user data
        $this->currentUser = null;
        Session::remove('user_id');

        // Regenerate session ID
        session_regenerate_id(true);

        Logger::security('Logout completed');
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
            Logger::security('Invalid session detected and cleared');
            Session::remove('user_id');
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