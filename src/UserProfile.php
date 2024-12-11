<?php

declare(strict_types=1);

namespace DiscogsHelper;

final class UserProfile
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly ?string $location,
        public readonly ?string $discogsUsername,
        public readonly ?string $discogsConsumerKey,
        public readonly ?string $discogsConsumerSecret,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function create(
        int $userId,
        ?string $location = null,
        ?string $discogsUsername = null,
        ?string $discogsConsumerKey = null,
        ?string $discogsConsumerSecret = null,
    ): self {
        return new self(
            id: 0, // Will be set by database
            userId: $userId,
            location: $location,
            discogsUsername: $discogsUsername,
            discogsConsumerKey: $discogsConsumerKey,
            discogsConsumerSecret: $discogsConsumerSecret,
            createdAt: date('Y-m-d H:i:s'),
            updatedAt: date('Y-m-d H:i:s')
        );
    }

    public function hasDiscogsCredentials(): bool
    {
        return $this->discogsUsername !== null &&
            $this->discogsConsumerKey !== null &&
            $this->discogsConsumerSecret !== null;
    }

    public function withUpdatedLocation(?string $location): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            location: $location,
            discogsUsername: $this->discogsUsername,
            discogsConsumerKey: $this->discogsConsumerKey,
            discogsConsumerSecret: $this->discogsConsumerSecret,
            createdAt: $this->createdAt,
            updatedAt: date('Y-m-d H:i:s')
        );
    }

    public function withUpdatedDiscogsInformation(
        ?string $discogsUsername,
        ?string $discogsConsumerKey,
        ?string $discogsConsumerSecret
    ): self {
        return new self(
            id: $this->id,
            userId: $this->userId,
            location: $this->location,
            discogsUsername: $discogsUsername,
            discogsConsumerKey: $discogsConsumerKey,
            discogsConsumerSecret: $discogsConsumerSecret,
            createdAt: $this->createdAt,
            updatedAt: date('Y-m-d H:i:s')
        );
    }
}
