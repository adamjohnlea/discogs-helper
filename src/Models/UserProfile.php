<?php

declare(strict_types=1);

namespace DiscogsHelper\Models;

use RuntimeException;

final class UserProfile
{
    // Maximum lengths
    private const int MAX_LOCATION_LENGTH = 100;
    private const int MAX_DISCOGS_USERNAME_LENGTH = 50;
    private const int MIN_DISCOGS_USERNAME_LENGTH = 2;
    private const int MIN_API_KEY_LENGTH = 10; // Typical API key minimum

    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly ?string $location,
        public readonly ?string $discogsUsername,
        public readonly ?string $discogsConsumerKey,
        public readonly ?string $discogsConsumerSecret,
        public readonly ?string $discogsOAuthToken,
        public readonly ?string $discogsOAuthTokenSecret,
        public readonly ?string $lastfmApiKey,
        public readonly ?string $lastfmApiSecret,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
        // Validate optional fields if they're provided
        if ($location !== null) {
            $this->validateLocation($location);
        }

        if ($discogsUsername !== null) {
            $this->validateDiscogsUsername($discogsUsername);
        }

        if ($discogsConsumerKey !== null || $discogsConsumerSecret !== null) {
            $this->validateDiscogsCredentials($discogsConsumerKey, $discogsConsumerSecret);
        }

        if ($lastfmApiKey !== null || $lastfmApiSecret !== null) {
            $this->validateLastFmCredentials($lastfmApiKey, $lastfmApiSecret);
        }
    }

    public static function create(
        int $userId,
        ?string $location = null,
        ?string $discogsUsername = null,
        ?string $discogsConsumerKey = null,
        ?string $discogsConsumerSecret = null,
        ?string $discogsOAuthToken = null,
        ?string $discogsOAuthTokenSecret = null,
        ?string $lastfmApiKey = null,
        ?string $lastfmApiSecret = null,
    ): self {
        // Perform validations before creating
        $instance = new self(
            id: 0, // Will be set by database
            userId: $userId,
            location: $location,
            discogsUsername: $discogsUsername,
            discogsConsumerKey: $discogsConsumerKey,
            discogsConsumerSecret: $discogsConsumerSecret,
            discogsOAuthToken: $discogsOAuthToken,
            discogsOAuthTokenSecret: $discogsOAuthTokenSecret,
            lastfmApiKey: $lastfmApiKey,
            lastfmApiSecret: $lastfmApiSecret,
            createdAt: date('Y-m-d H:i:s'),
            updatedAt: date('Y-m-d H:i:s')
        );

        return $instance;
    }

    public function hasDiscogsCredentials(): bool
    {
        return $this->discogsUsername !== null &&
            $this->discogsConsumerKey !== null &&
            $this->discogsConsumerSecret !== null;
    }

    public function hasDiscogsOAuth(): bool
    {
        return $this->discogsOAuthToken !== null &&
            $this->discogsOAuthTokenSecret !== null;
    }

    public function hasLastFmCredentials(): bool
    {
        return $this->lastfmApiKey !== null &&
            $this->lastfmApiSecret !== null;
    }

    public function withUpdatedCredentials(
        ?string $discogsUsername = null,
        ?string $discogsConsumerKey = null,
        ?string $discogsConsumerSecret = null,
        ?string $discogsOAuthToken = null,
        ?string $discogsOAuthTokenSecret = null,
        ?string $lastfmApiKey = null,
        ?string $lastfmApiSecret = null,
    ): self {
        return new self(
            id: $this->id,
            userId: $this->userId,
            location: $this->location,
            discogsUsername: $discogsUsername ?? $this->discogsUsername,
            discogsConsumerKey: $discogsConsumerKey ?? $this->discogsConsumerKey,
            discogsConsumerSecret: $discogsConsumerSecret ?? $this->discogsConsumerSecret,
            discogsOAuthToken: $discogsOAuthToken ?? $this->discogsOAuthToken,
            discogsOAuthTokenSecret: $discogsOAuthTokenSecret ?? $this->discogsOAuthTokenSecret,
            lastfmApiKey: $lastfmApiKey ?? $this->lastfmApiKey,
            lastfmApiSecret: $lastfmApiSecret ?? $this->lastfmApiSecret,
            createdAt: $this->createdAt,
            updatedAt: date('Y-m-d H:i:s')
        );
    }

    private function validateLocation(string $location): void
    {
        $trimmedLocation = trim($location);

        if (strlen($trimmedLocation) > self::MAX_LOCATION_LENGTH) {
            throw new RuntimeException(sprintf(
                'Location cannot be longer than %d characters',
                self::MAX_LOCATION_LENGTH
            ));
        }

        // Basic sanitization check
        if (preg_match('/[<>]/', $trimmedLocation)) {
            throw new RuntimeException('Location contains invalid characters');
        }
    }

    private function validateDiscogsUsername(string $username): void
    {
        $trimmedUsername = trim($username);

        if (strlen($trimmedUsername) < self::MIN_DISCOGS_USERNAME_LENGTH) {
            throw new RuntimeException(sprintf(
                'Discogs username must be at least %d characters',
                self::MIN_DISCOGS_USERNAME_LENGTH
            ));
        }

        if (strlen($trimmedUsername) > self::MAX_DISCOGS_USERNAME_LENGTH) {
            throw new RuntimeException(sprintf(
                'Discogs username cannot be longer than %d characters',
                self::MAX_DISCOGS_USERNAME_LENGTH
            ));
        }

        // Basic format check for usernames (alphanumeric, underscore, hyphen)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $trimmedUsername)) {
            throw new RuntimeException('Discogs username contains invalid characters');
        }
    }

    private function validateDiscogsCredentials(?string $key, ?string $secret): void
    {
        // Both must be provided together
        if (($key === null && $secret !== null) || ($key !== null && $secret === null)) {
            throw new RuntimeException('Both Discogs Consumer Key and Secret must be provided together');
        }

        if ($key !== null) {
            if (strlen($key) < self::MIN_API_KEY_LENGTH) {
                throw new RuntimeException('Discogs Consumer Key appears to be invalid');
            }

            // Basic format check for API keys (typically alphanumeric)
            if (!preg_match('/^[a-zA-Z0-9]+$/', $key)) {
                throw new RuntimeException('Discogs Consumer Key contains invalid characters');
            }
        }

        if ($secret !== null) {
            if (strlen($secret) < self::MIN_API_KEY_LENGTH) {
                throw new RuntimeException('Discogs Consumer Secret appears to be invalid');
            }

            // Basic format check for API secrets (typically alphanumeric)
            if (!preg_match('/^[a-zA-Z0-9]+$/', $secret)) {
                throw new RuntimeException('Discogs Consumer Secret contains invalid characters');
            }
        }
    }

    private function validateLastFmCredentials(?string $key, ?string $secret): void
    {
        // Both must be provided together
        if (($key === null && $secret !== null) || ($key !== null && $secret === null)) {
            throw new RuntimeException('Both Last.fm API Key and Secret must be provided together');
        }

        if ($key !== null) {
            if (strlen($key) < self::MIN_API_KEY_LENGTH) {
                throw new RuntimeException('Last.fm API Key appears to be invalid');
            }

            // Basic format check for API keys (typically alphanumeric)
            if (!preg_match('/^[a-zA-Z0-9]+$/', $key)) {
                throw new RuntimeException('Last.fm API Key contains invalid characters');
            }
        }

        if ($secret !== null) {
            if (strlen($secret) < self::MIN_API_KEY_LENGTH) {
                throw new RuntimeException('Last.fm API Secret appears to be invalid');
            }

            // Basic format check for API secrets (typically alphanumeric)
            if (!preg_match('/^[a-zA-Z0-9]+$/', $secret)) {
                throw new RuntimeException('Last.fm API Secret contains invalid characters');
            }
        }
    }
}