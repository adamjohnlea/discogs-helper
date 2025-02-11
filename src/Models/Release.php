<?php

declare(strict_types=1);

namespace DiscogsHelper\Models;

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
        return '/' . $this->coverPath;
    }

    /**
     * Get the tracklist as an array of tracks
     */
    public function getTracklistArray(): array
    {
        if (empty($this->tracklist)) {
            return [];
        }

        $tracklist = json_decode($this->tracklist, true);
        if (!is_array($tracklist)) {
            return [];
        }

        return array_map(function($track) {
            return [
                'position' => $track['position'] ?? null,
                'title' => $track['title'] ?? '',
                'duration' => $track['duration'] ?? null
            ];
        }, $tracklist);
    }

    public function getIdentifiersArray(): array
    {
        return json_decode($this->identifiers, true);
    }
} 