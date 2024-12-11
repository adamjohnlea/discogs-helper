<?php

declare(strict_types=1);

namespace DiscogsHelper;

use DiscogsHelper\Exceptions\DiscogsCredentialsException;
use GuzzleHttp\Client;
use RuntimeException;
use GuzzleHttp\Exception\ClientException;
use Exception;

final class DiscogsService
{
    public readonly Client $client;
    private const string BASE_URL = 'https://api.discogs.com';
    private const int PER_PAGE = 100; // Discogs allows up to 100 items per page

    public function __construct(
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
        private readonly string $userAgent
    ) {
        if (empty($consumerKey) || empty($consumerSecret)) {
            throw new DiscogsCredentialsException('Discogs credentials are required');
        }

        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'headers' => [
                'User-Agent' => $userAgent,
                'Authorization' => $this->getAuthorizationHeader(),
            ],
        ]);
    }

    private function getAuthorizationHeader(): string
    {
        return sprintf(
            'Discogs key=%s, secret=%s',
            $this->consumerKey,
            $this->consumerSecret
        );
    }

    /**
     * Validate Discogs credentials without creating a full service instance
     */
    public static function validateCredentials(
        string $consumerKey,
        string $consumerSecret,
        string $userAgent
    ): bool {
        try {
            // Create a temporary client to test the credentials
            $client = new Client([
                'base_uri' => self::BASE_URL,
                'headers' => [
                    'User-Agent' => $userAgent,
                    'Authorization' => sprintf(
                        'Discogs key=%s, secret=%s',
                        $consumerKey,
                        $consumerSecret
                    ),
                ],
            ]);

            // Make a simple API call to verify credentials
            // Using search with minimal data to reduce API impact
            $response = $client->get('/database/search', [
                'query' => [
                    'q' => 'test',
                    'per_page' => 1
                ]
            ]);

            // If we get here, the credentials are valid
            return $response->getStatusCode() === 200;

        } catch (ClientException $e) {
            // If we get a 401 Unauthorized, the credentials are invalid
            if ($e->getResponse()->getStatusCode() === 401) {
                return false;
            }
            // For other errors, we'll throw them to be handled by the caller
            throw $e;
        }
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
            Logger::log("Failed to download cover: " . $e->getMessage());
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
                    Logger::log("Failed to fetch release {$item['id']}: " . $e->getMessage());
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