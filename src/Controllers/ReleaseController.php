<?php

declare(strict_types=1);

namespace DiscogsHelper\Controllers;

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Database\Database;
use DiscogsHelper\Services\Discogs\DiscogsService;
use DiscogsHelper\Exceptions\DiscogsCredentialsException;
use DiscogsHelper\Logging\Logger;
use Exception;

final class ReleaseController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Database $db,
        private readonly DiscogsService $discogs
    ) {}

    private function jsonResponse(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public function processAdd(): void
    {
        try {
            if (!$this->auth->isLoggedIn()) {
                $this->jsonResponse(['success' => false, 'message' => 'You must be logged in']);
                return;
            }

            $releaseId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$releaseId) {
                $this->jsonResponse(['success' => false, 'message' => 'Invalid release ID']);
                return;
            }

            $selectedImage = filter_input(INPUT_POST, 'selected_image', FILTER_SANITIZE_URL);
            
            // First add to Discogs collection
            try {
                $profile = $this->db->getUserProfile($this->auth->getCurrentUser()->id);
                if (!$profile || !$profile->discogsUsername) {
                    throw new Exception('Discogs username not set in your profile');
                }
                $username = $profile->discogsUsername;
                
                $this->discogs->addToCollection($username, $releaseId);
                
            } catch (DiscogsCredentialsException $e) {
                $this->jsonResponse([
                    'success' => false, 
                    'message' => 'Your Discogs credentials appear to be invalid. Please check your settings.'
                ]);
                return;
            } catch (Exception $e) {
                Logger::error('Failed to add to Discogs collection: ' . $e->getMessage());
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to add to Discogs collection: ' . $e->getMessage()
                ]);
                return;
            }

            // Then add to local database
            try {
                $release = $this->discogs->getRelease($releaseId);
                
                // Download the selected cover image if provided
                $coverPath = null;
                if ($selectedImage) {
                    $coverPath = $this->discogs->downloadCover($selectedImage);
                }
                
                // Add to local database
                $this->db->saveRelease(
                    $this->auth->getCurrentUser()->id,
                    $releaseId,
                    $release['title'],
                    implode(', ', array_column($release['artists'], 'name')),
                    $release['year'] ?? null,
                    $release['formats'][0]['name'] ?? 'Unknown',
                    $this->formatDetails($release['formats'] ?? []),
                    $coverPath,
                    $release['notes'] ?? null,
                    json_encode($release['tracklist'] ?? []),
                    json_encode($release['identifiers'] ?? []),
                    date('Y-m-d H:i:s')
                );

                $this->jsonResponse(['success' => true]);
                
            } catch (Exception $e) {
                Logger::error('Failed to add release to local database: ' . $e->getMessage());
                // Try to remove from Discogs collection since local add failed
                try {
                    $instanceId = $this->discogs->getCollectionItemInstance($username, $releaseId);
                    if ($instanceId) {
                        $this->discogs->removeFromCollection($username, $releaseId, $instanceId);
                    }
                } catch (Exception $rollbackError) {
                    Logger::error('Failed to rollback Discogs collection add: ' . $rollbackError->getMessage());
                }
                
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to add release to local collection: ' . $e->getMessage()
                ]);
            }
        } catch (Exception $e) {
            Logger::error('Unexpected error in processAdd: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'An unexpected error occurred'
            ]);
        }
    }

    private function formatDetails(array $formats): string
    {
        if (empty($formats)) {
            return '';
        }

        $format = $formats[0];
        $parts = [];

        if (!empty($format['descriptions'])) {
            $parts = $format['descriptions'];
        }

        if (!empty($format['text'])) {
            $parts[] = $format['text'];
        }

        return empty($parts) ? $format['name'] : $format['name'] . ' (' . implode(', ', $parts) . ')';
    }
} 