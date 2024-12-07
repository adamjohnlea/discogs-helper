<?php

declare(strict_types=1);

namespace DiscogsHelper;

use GuzzleHttp\Client;
use RuntimeException;
use GuzzleHttp\Exception\ClientException;

final class DiscogsService
{
    public readonly Client $client;
    private const BASE_URL = 'https://api.discogs.com';
    private const PER_PAGE = 100; // Discogs allows up to 100 items per page

    public function __construct(string $consumerKey, string $consumerSecret, string $userAgent)
    {
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'headers' => [
                'User-Agent' => $userAgent,
                'Authorization' => 'Discogs key=' . $consumerKey . ', secret=' . $consumerSecret,
            ],
        ]);
    }

    public function searchRelease(string $query): array
    {
        // Check if query is a UPC/Barcode (only numbers)
        $isBarcode = preg_match('/^\d+$/', $query);
        
        $response = $this->client->get('/database/search', [
            'query' => [
                $isBarcode ? 'barcode' : 'q' => $query,
                'type' => 'release',
                'per_page' => 10
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['results'] ?? [];
    }

    public function getRelease(int $id): array
    {
        try {
            $response = $this->client->get('/releases/' . $id);
            $this->handleRateLimit($response->getHeaders());
            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 429) {
                // If we hit the rate limit, wait and try again
                sleep(60);
                return $this->getRelease($id);
            }
            throw $e;
        }
    }

    public function downloadCover(string $url): ?string
    {
        try {
            $response = $this->client->get($url);
            
            // Get original filename from URL
            $originalFilename = basename(parse_url($url, PHP_URL_PATH));
            
            // Generate unique filename using timestamp and random string
            $uniqueId = time() . '_' . bin2hex(random_bytes(4));
            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'jpg';
            $filename = $uniqueId . '.' . $extension;
            
            $savePath = __DIR__ . '/../public/images/covers/' . $filename;
            
            file_put_contents($savePath, $response->getBody()->getContents());
            
            // Return the relative path from public directory
            return 'images/covers/' . $filename;
        } catch (Exception $e) {
            error_log("Failed to download cover: " . $e->getMessage());
            return null;
        }
    }

    public function getUserCollection(string $username): array
    {
        $page = 1;
        $collection = [];
        
        do {
            // Add delay between requests to respect rate limits
            usleep(1000000); // 1 second delay

            $response = $this->client->get("/users/{$username}/collection/folders/0/releases", [
                'query' => [
                    'page' => $page,
                    'per_page' => self::PER_PAGE,
                    'sort' => 'artist',
                    'sort_order' => 'asc'
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($data['releases'])) {
                throw new RuntimeException('Unable to fetch collection data');
            }

            foreach ($data['releases'] as $item) {
                // Add delay between each release request
                usleep(1000000); // 1 second delay
                
                try {
                    $collection[] = $this->getRelease($item['id']);
                } catch (Exception $e) {
                    // Log the error but continue with other releases
                    error_log("Failed to fetch release {$item['id']}: " . $e->getMessage());
                    continue;
                }
            }

            $page++;
            $hasMorePages = $data['pagination']['pages'] > $data['pagination']['page'];
        } while ($hasMorePages);

        return $collection;
    }

    private function handleRateLimit(array $headers): void
    {
        if (isset($headers['X-Discogs-Ratelimit-Remaining'][0])) {
            $remaining = (int)$headers['X-Discogs-Ratelimit-Remaining'][0];
            if ($remaining < 5) {  // If we're running low on requests
                sleep(60);  // Wait for a minute to reset
            }
        }
    }
} 