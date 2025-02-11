<?php

declare(strict_types=1);

namespace DiscogsHelper\Controllers;

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Database\Database;
use DiscogsHelper\Services\LastFmService;
use DiscogsHelper\Logging\Logger;
use DiscogsHelper\Http\Session;
use Exception;

final class RecommendationsController
{
    private const int MAX_SOURCE_ARTISTS = 20; // Limit number of artists to get recommendations for
    private const int SIMILAR_ARTISTS_PER_QUERY = 5; // Limit similar artists per query

    public function __construct(
        private Auth $auth,
        private Database $db,
        private LastFmService $lastfm
    ) {}

    public function getRecommendations(?bool $regenerate = false): array
    {
        $userId = $this->auth->getCurrentUser()->id;
        
        // Check for stored recommendations if not regenerating
        if (!$regenerate) {
            $stored = $this->getCachedRecommendations($userId);
            if ($stored !== null) {
                Logger::log("Using stored recommendations for user {$userId}");
                return $stored;
            }
        }

        // Generate new recommendations
        Logger::log("Generating new recommendations for user {$userId}");
        $recommendations = $this->generateRecommendations();
        
        // Store the results
        $this->cacheRecommendations($userId, $recommendations);
        
        return $recommendations;
    }

    private function generateRecommendations(): array
    {
        $userId = $this->auth->getCurrentUser()->id;
        
        // Get top artists from user's collection
        $ownedArtists = $this->getUniqueArtists($userId);
        if (empty($ownedArtists)) {
            return [];
        }

        // Create a simple list of owned artists for comparison, removing parenthetical numbers
        $ownedArtistsLower = array_map(function($artist) {
            // Remove parenthetical numbers and clean up the name
            $cleaned = preg_replace('/\s*\(\d+\)\s*$/', '', $artist);
            return strtolower($cleaned);
        }, $ownedArtists);
        Logger::log("Owned artists (cleaned): " . implode(", ", $ownedArtistsLower));
        
        // Take only the top N artists to avoid timeout
        $sourceArtists = array_slice($ownedArtists, 0, self::MAX_SOURCE_ARTISTS);
        
        // Get recommendations for each artist
        $recommendations = [];
        $processedArtists = [];

        foreach ($sourceArtists as $artist) {
            try {
                Logger::log("Getting similar artists for: " . $artist);
                $similarArtists = $this->lastfm->getSimilarArtists($artist, self::SIMILAR_ARTISTS_PER_QUERY);
                
                foreach ($similarArtists as $similar) {
                    $similarName = $similar['name'];
                    
                    // Special case: Direct check for 10,000 Maniacs
                    if (preg_match('/10[\s,]*000\s+maniacs/i', $similarName)) {
                        Logger::log("Found 10,000 Maniacs variant - skipping");
                        continue;
                    }
                    
                    // Clean up the similar artist name for comparison
                    $similarNameLower = strtolower(preg_replace('/\s*\(\d+\)\s*$/', '', $similarName));
                    
                    // Skip if we already own this artist (case insensitive)
                    if (in_array($similarNameLower, $ownedArtistsLower)) {
                        Logger::log("Skipping {$similarName} (already owned as: " . $artist . ")");
                        continue;
                    }

                    // Skip if we've already processed this artist
                    if (isset($processedArtists[$similarNameLower])) {
                        Logger::log("Skipping {$similarName} (already processed)");
                        continue;
                    }
                    
                    // Initialize or update recommendation score
                    if (!isset($recommendations[$similarName])) {
                        $recommendations[$similarName] = [
                            'name' => $similarName,
                            'score' => 0,
                            'match' => 0,
                            'similar_to' => [],
                            'tags' => []
                        ];
                    }
                    
                    // Update recommendation data
                    $recommendations[$similarName]['score'] += (float)$similar['match'];
                    $recommendations[$similarName]['similar_to'][] = $artist;
                    
                    // Get tags for additional context
                    if (empty($recommendations[$similarName]['tags'])) {
                        Logger::log("Getting tags for: " . $similarName);
                        $artistTags = $this->lastfm->getArtistTags($similarName);
                        $recommendations[$similarName]['tags'] = array_slice(
                            array_map(
                                fn($tag) => $tag['name'],
                                $artistTags
                            ),
                            0,
                            5
                        );
                    }
                    
                    $processedArtists[$similarNameLower] = true;
                }
            } catch (\Exception $e) {
                Logger::error("Error getting recommendations for {$artist}: " . $e->getMessage());
                continue;
            }
        }

        Logger::log("Generated " . count($recommendations) . " total recommendations");

        // Sort recommendations by score
        uasort($recommendations, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Calculate normalized match percentage
        foreach ($recommendations as &$rec) {
            $rec['match'] = min(round(($rec['score'] / count($rec['similar_to'])) * 100), 100);
            $rec['similar_to'] = array_slice($rec['similar_to'], 0, 3); // Limit to top 3 similar artists
        }

        return array_values($recommendations);
    }

    private function normalizeArtistName(string $name): string
    {
        // Log original name
        Logger::log("Normalizing artist name: " . $name);
        
        // Handle specific cases first - before any other transformations
        if (preg_match('/\b10[\s,]*000\s+maniacs?\b/i', $name)) {
            Logger::log("Found 10,000 Maniacs match - preserving exact format");
            return "10,000 Maniacs";
        }
        
        if (preg_match('/\bjack\s+white\b/i', $name)) {
            Logger::log("Found Jack White match");
            return "jackwhite";
        }
        
        // Convert to lowercase after checking specific cases
        $name = strtolower($name);
        
        // Remove common prefixes
        $name = preg_replace('/^the\s+/', '', $name);
        
        // Remove special characters and extra spaces
        $name = str_replace(['&', '+', ',', '.'], ' ', $name);
        $name = preg_replace('/[^\w\s-]/', '', $name);
        $name = trim(preg_replace('/\s+/', '', $name));
        
        Logger::log("Final normalized name: " . $name);
        return $name;
    }

    private function getUniqueArtists(int $userId): array
    {
        // Get all artists from the database
        $allArtists = $this->db->getTopArtists($userId, 1000);
        
        // Process and clean artist names
        $artists = array_map(
            function($artist) {
                // Take first artist if multiple and clean the name
                $name = explode(',', $artist['artist'])[0];
                return trim($name);
            },
            $allArtists
        );
        
        // Remove duplicates
        return array_values(array_unique($artists));
    }

    private function getCachedRecommendations(int $userId): ?array
    {
        $stmt = $this->db->getPdo()->prepare('
            SELECT recommendations 
            FROM artist_recommendations 
            WHERE user_id = :user_id 
            ORDER BY generated_at DESC 
            LIMIT 1
        ');
        
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }

        return json_decode($result['recommendations'], true);
    }

    private function cacheRecommendations(int $userId, array $recommendations): void
    {
        // First delete any existing recommendations for this user
        $stmt = $this->db->getPdo()->prepare('
            DELETE FROM artist_recommendations 
            WHERE user_id = :user_id
        ');
        $stmt->execute(['user_id' => $userId]);

        // Insert new recommendations
        $stmt = $this->db->getPdo()->prepare('
            INSERT INTO artist_recommendations (user_id, recommendations)
            VALUES (:user_id, :recommendations)
        ');
        
        $stmt->execute([
            'user_id' => $userId,
            'recommendations' => json_encode($recommendations)
        ]);
    }
} 