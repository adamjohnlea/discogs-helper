<?php
// src/Auth.php
declare(strict_types=1);

namespace DiscogsHelper\Security;

use DiscogsHelper\Exceptions\RateLimitExceededException;
use DiscogsHelper\Security\RateLimiter;
use DiscogsHelper\Models\User;
use DiscogsHelper\Database\Database;
use DiscogsHelper\Http\Session;
use DiscogsHelper\Logging\Logger;
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

        // Username validations
        if (strlen($username) < 3) {
            Logger::security('Registration failed: username too short');
            throw new RuntimeException('Username must be at least 3 characters long');
        }

        if (strlen($username) > 30) {
            Logger::security('Registration failed: username too long');
            throw new RuntimeException('Username cannot be longer than 30 characters');
        }

        // Only allow alphanumeric characters, underscores, and hyphens
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            Logger::security('Registration failed: invalid username characters');
            throw new RuntimeException('Username can only contain letters, numbers, underscores, and hyphens');
        }

        // Check for duplicate username
        if ($this->db->findUserByUsername($username)) {
            Logger::security('Registration failed: username already exists');
            throw new RuntimeException('Username already exists');
        }

        // Password validations
        if (strlen($password) < 8) {
            Logger::security('Registration failed: password too short');
            throw new RuntimeException('Password must be at least 8 characters long');
        }

        if (strlen($password) > 72) {  // bcrypt has a 72-byte limit
            Logger::security('Registration failed: password too long');
            throw new RuntimeException('Password cannot be longer than 72 characters');
        }

        // Check password complexity
        if (!$this->isValidPassword($password)) {
            Logger::security('Registration failed: password does not meet complexity requirements');
            throw new RuntimeException('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character');
        }

        // Email validations
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Logger::security('Registration failed: invalid email format');
            throw new RuntimeException('Invalid email address format');
        }

        if (!$this->isValidEmailFormat($email)) {
            Logger::security('Registration failed: email does not meet requirements');
            throw new RuntimeException('Email address must be properly formatted (e.g., user@domain.com)');
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

    private function isValidPassword(string $password): bool
    {
        // Check for at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }

        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }

        // Check for at least one number
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }

        // Check for at least one special character
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            return false;
        }

        return true;
    }

    private function isValidEmailFormat(string $email): bool
    {
        // Additional email validation rules
        $pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';

        // Check minimum length requirements
        if (strlen($email) < 6) { // user@x.yy minimum valid email
            return false;
        }

        // Check domain has at least one period and valid TLD length
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }

        $domain = $parts[1];
        if (!str_contains($domain, '.')) {
            return false;
        }

        $tld = explode('.', $domain);
        $lastPart = end($tld);
        if (strlen($lastPart) < 2) { // Minimum TLD length
            return false;
        }

        return preg_match($pattern, $email) === 1;
    }
}