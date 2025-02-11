<?php

declare(strict_types=1);

namespace DiscogsHelper\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use DiscogsHelper\Database\Database;
use DiscogsHelper\Models\Release;
use Exception;

final class LastFmService
{
    private ?string $apiKey;
    private ?string $apiSecret;
    private Client $client;
    private const API_ROOT = 'http://ws.audioscrobbler.com/2.0/';

    public function __construct(Database $db, int $userId)
    {
        // Get Last.fm credentials from user_profiles
        $profile = $db->getUserProfile($userId);
        
        $this->apiKey = $profile?->lastfmApiKey;
        $this->apiSecret = $profile?->lastfmApiSecret;
        
        $this->client = new Client([
            'base_uri' => self::API_ROOT,
            'headers' => [
                'User-Agent' => 'DiscogsHelper/1.0',
            ]
        ]);
    }

    public function getSimilarArtists(string $artist, int $limit = 10): array
    {
        if (!$this->apiKey) {
            throw new \RuntimeException('Last.fm API key not configured');
        }

        $response = $this->client->get('', [
            'query' => [
                'method' => 'artist.getSimilar',
                'artist' => $artist,
                'api_key' => $this->apiKey,
                'format' => 'json',
                'limit' => $limit
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['similarartists']['artist'] ?? [];
    }

    public function getArtistTags(string $artist): array
    {
        if (!$this->apiKey) {
            throw new \RuntimeException('Last.fm API key not configured');
        }

        $response = $this->client->get('', [
            'query' => [
                'method' => 'artist.getTopTags',
                'artist' => $artist,
                'api_key' => $this->apiKey,
                'format' => 'json'
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['toptags']['tag'] ?? [];
    }

    public function getSimilarTracks(string $artist, string $track, int $limit = 10): array
    {
        if (!$this->apiKey) {
            throw new \RuntimeException('Last.fm API key not configured');
        }

        $response = $this->client->get('', [
            'query' => [
                'method' => 'track.getSimilar',
                'artist' => $artist,
                'track' => $track,
                'api_key' => $this->apiKey,
                'format' => 'json',
                'limit' => $limit
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['similartracks']['track'] ?? [];
    }
} 