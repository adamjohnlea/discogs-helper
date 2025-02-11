<?php

declare(strict_types=1);

namespace DiscogsHelper\Services\Discogs;

use DiscogsHelper\Exceptions\DiscogsCredentialsException;
use DiscogsHelper\Logging\Logger;
use GuzzleHttp\Client;
use RuntimeException;
use GuzzleHttp\Exception\ClientException;
use Exception;
use GuzzleHttp\Exception\RequestException;

final class DiscogsService
{
    public readonly Client $client;
    private const string BASE_URL = 'https://api.discogs.com';
    private const int PER_PAGE = 100; // Discogs allows up to 100 items per page

    public function __construct(
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
        private readonly string $userAgent,
        private readonly ?string $oauthToken = null,
        private readonly ?string $oauthTokenSecret = null
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
        if ($this->oauthToken !== null && $this->oauthTokenSecret !== null) {
            // Use OAuth authentication for user-specific actions
            return sprintf(
                'OAuth oauth_consumer_key="%s", oauth_nonce="%s", oauth_token="%s", oauth_signature="%s", oauth_signature_method="PLAINTEXT", oauth_timestamp="%d"',
                $this->consumerKey,
                bin2hex(random_bytes(16)),
                $this->oauthToken,
                $this->consumerSecret . '&' . $this->oauthTokenSecret,
                time()
            );
        }

        // Use simple key/secret authentication for non-user-specific actions
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

    public function searchRelease(
        string $query,
        int $page = 1,
        int $perPage = 10,
        bool $isBarcode = false
    ): array {
        $params = [
            'q' => $query,
            'page' => $page,
            'per_page' => $perPage,
            'type' => 'release'
        ];

        if ($isBarcode) {
            unset($params['q']);
            $params['barcode'] = $query;
        }

        $response = $this->client->get('/database/search', [
            'query' => $params
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return [
            'results' => $data['results'] ?? [],
            'pagination' => [
                'total' => $data['pagination']['items'] ?? 0,
                'pages' => $data['pagination']['pages'] ?? 0,
                'current_page' => $data['pagination']['page'] ?? 1
            ]
        ];
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
            
            // Generate unique filename
            $uniqueId = time() . '_' . bin2hex(random_bytes(4));
            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'jpg';
            $filename = $uniqueId . '.' . $extension;
            
            // Use project root path
            $projectRoot = dirname(__DIR__, 3); // Go up 3 levels from src/Services/Discogs
            $tempPath = sys_get_temp_dir() . '/' . $filename;
            $savePath = $projectRoot . '/public/images/covers/' . $filename;
            
            // Ensure the covers directory exists
            $coversDir = dirname($savePath);
            if (!is_dir($coversDir)) {
                mkdir($coversDir, 0755, true);
            }
            
            // Save original to temp file
            file_put_contents($tempPath, $response->getBody()->getContents());
            
            // Optimize image
            $this->optimizeImage($tempPath, $savePath);
            
            // Clean up temp file
            unlink($tempPath);
            
            // Return the relative path from public directory
            return 'images/covers/' . $filename;
        } catch (Exception $e) {
            Logger::error("Failed to download cover: " . $e->getMessage());
            return null;
        }
    }

    private function optimizeImage(string $sourcePath, string $targetPath): void
    {
        // Create image from file
        $image = imagecreatefromstring(file_get_contents($sourcePath));
        
        if (!$image) {
            throw new RuntimeException('Failed to create image from downloaded file');
        }
        
        // Get original dimensions
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Calculate new dimensions (max 800px width)
        $maxWidth = 800;
        if ($width > $maxWidth) {
            $ratio = $maxWidth / $width;
            $newWidth = $maxWidth;
            $newHeight = $height * $ratio;
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }
        
        // Create new image with new dimensions
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled(
            $newImage, $image,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );
        
        // Save with quality 80 (good balance between quality and size)
        imagejpeg($newImage, $targetPath, 80);
        
        // Clean up
        imagedestroy($image);
        imagedestroy($newImage);
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

    public function handleRateLimit(array $headers): void
    {
        // Get rate limit info from headers
        $remaining = (int)($headers['X-Discogs-Ratelimit-Remaining'][0] ?? 60);
        $resetTime = (int)($headers['X-Discogs-Ratelimit-Reset'][0] ?? 0);
        
        Logger::log("Discogs API rate limit: {$remaining} requests remaining");
        
        if ($remaining < 5) {
            $waitTime = $resetTime - time();
            Logger::log("Rate limit low, waiting {$waitTime} seconds");
            
            if ($waitTime > 0) {
                sleep($waitTime);
            }
        } else {
            // Adaptive delay based on remaining requests
            $delay = max(200000, 1000000 * (5 / $remaining));
            usleep($delay);
        }
    }

    public function getWantlist(string $username, int $page = 1): array
    {
        try {
            $response = $this->client->get("/users/{$username}/wants", [
                'query' => [
                    'page' => $page,
                    'per_page' => 100
                ]
            ]);
            
            $this->handleRateLimit($response->getHeaders());
            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 429) {
                // If we hit the rate limit, wait and try again
                sleep(60);
                return $this->getWantlist($username, $page);
            }
            throw $e;
        }
    }

    public function addToWantlist(string $username, int $releaseId): array
    {
        if (!$this->oauthToken || !$this->oauthTokenSecret) {
            throw new DiscogsCredentialsException('OAuth credentials required for modifying wantlist');
        }

        try {
            $response = $this->client->put("/users/{$username}/wants/{$releaseId}");
            $this->handleRateLimit($response->getHeaders());
            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 429) {
                // If we hit the rate limit, wait and try again
                sleep(60);
                return $this->addToWantlist($username, $releaseId);
            }
            throw $e;
        }
    }

    public function removeFromWantlist(string $username, int $releaseId): void
    {
        if (!$this->oauthToken || !$this->oauthTokenSecret) {
            throw new DiscogsCredentialsException('OAuth credentials required for modifying wantlist');
        }

        try {
            $response = $this->client->delete("/users/{$username}/wants/{$releaseId}");
            $this->handleRateLimit($response->getHeaders());
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 429) {
                // If we hit the rate limit, wait and try again
                sleep(60);
                $this->removeFromWantlist($username, $releaseId);
                return;
            }
            throw $e;
        }
    }

    public function addToCollection(string $username, int $releaseId): void
    {
        if (!$this->oauthToken || !$this->oauthTokenSecret) {
            throw new DiscogsCredentialsException('OAuth credentials required for modifying collection');
        }

        try {
            // Add to the main collection folder (folder_id = 1)
            $response = $this->client->post("/users/{$username}/collection/folders/1/releases/{$releaseId}");
            $this->handleRateLimit($response->getHeaders());
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 429) {
                // If we hit the rate limit, wait and try again
                sleep(60);
                $this->addToCollection($username, $releaseId);
                return;
            }
            throw $e;
        }
    }

    public function removeFromCollection(string $username, int $releaseId, int $instanceId): void
    {
        if (!$this->oauthToken || !$this->oauthTokenSecret) {
            throw new DiscogsCredentialsException('OAuth credentials required for modifying collection');
        }

        try {
            // Remove from the main collection folder (folder_id = 1)
            $response = $this->client->delete("/users/{$username}/collection/folders/1/releases/{$releaseId}/instances/{$instanceId}");
            $this->handleRateLimit($response->getHeaders());
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 429) {
                // If we hit the rate limit, wait and try again
                sleep(60);
                $this->removeFromCollection($username, $releaseId, $instanceId);
                return;
            }
            throw $e;
        }
    }

    public function getCollectionItemInstance(string $username, int $releaseId): ?int
    {
        try {
            $response = $this->client->get("/users/{$username}/collection/releases/{$releaseId}");
            $this->handleRateLimit($response->getHeaders());
            $data = json_decode($response->getBody()->getContents(), true);
            
            // Return the instance ID of the first occurrence in the collection
            if (!empty($data['releases'][0]['instance_id'])) {
                return (int)$data['releases'][0]['instance_id'];
            }
            
            return null;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 429) {
                // If we hit the rate limit, wait and try again
                sleep(60);
                return $this->getCollectionItemInstance($username, $releaseId);
            }
            throw $e;
        }
    }
}