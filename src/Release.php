<?php

declare(strict_types=1);

namespace DiscogsHelper;

final class Release
{
    public function __construct(
        public readonly int $id,
        public readonly int $discogsId,
        public readonly string $title,
        public readonly string $artist,
        public readonly ?int $year,
        public readonly string $format,
        public readonly string $formatDetails,
        public readonly ?string $coverPath,
        public readonly ?string $notes,
        public readonly string $tracklist,
        public readonly string $identifiers,
        public readonly ?string $dateAdded,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public function getCoverUrl(): string
    {
        return '/images/covers/' . basename($this->coverPath);
    }

    public function getTracklistArray(): array
    {
        return json_decode($this->tracklist, true);
    }

    public function getIdentifiersArray(): array
    {
        return json_decode($this->identifiers, true);
    }
} 