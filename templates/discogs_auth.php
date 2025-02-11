<?php

declare(strict_types=1);

/** @var Auth $auth Authentication instance */
/** @var Database $db Database instance */
/** @var string|null $content Main content HTML */
/** @var string|null $styles Page-specific styles */

use DiscogsHelper\Security\Auth;
use DiscogsHelper\Database\Database;
use DiscogsHelper\Services\Discogs\DiscogsOAuth;
use DiscogsHelper\Logging\Logger;
use DiscogsHelper\Http\Session;

if (!$auth->isLoggedIn()) {
    header('Location: ?action=login');
    exit;
}

$userId = $auth->getCurrentUser()->id;
$profile = $db->getUserProfile($userId);

if (!$profile || empty($profile->discogsConsumerKey) || empty($profile->discogsConsumerSecret)) {
    Session::setMessage('Please set up your Discogs API credentials first.');
    header('Location: ?action=profile_edit');
    exit;
}

// Handle OAuth callback
if (isset($_GET['oauth_token'], $_GET['oauth_verifier'])) {
    try {
        // Get the request token secret from session
        $requestTokenSecret = $_SESSION['oauth_token_secret'] ?? null;
        if (!$requestTokenSecret) {
            throw new RuntimeException('OAuth session expired. Please try again.');
        }

        // Create OAuth handler
        $oauth = new DiscogsOAuth(
            consumerKey: $profile->discogsConsumerKey,
            consumerSecret: $profile->discogsConsumerSecret,
            userAgent: 'DiscogsHelper/1.0',
            callbackUrl: 'http://' . $_SERVER['HTTP_HOST'] . '/?action=discogs_auth'
        );

        // Exchange request token for access token
        $tokens = $oauth->getAccessToken(
            requestToken: $_GET['oauth_token'],
            requestTokenSecret: $requestTokenSecret,
            verifier: $_GET['oauth_verifier']
        );

        Logger::log('Received OAuth tokens from Discogs: ' . json_encode([
            'oauth_token_exists' => !empty($tokens['oauth_token']),
            'oauth_token_secret_exists' => !empty($tokens['oauth_token_secret'])
        ]));

        // Update user profile with OAuth tokens
        $updatedProfile = $profile->withUpdatedCredentials(
            discogsOAuthToken: $tokens['oauth_token'],
            discogsOAuthTokenSecret: $tokens['oauth_token_secret']
        );

        Logger::log('Updated profile OAuth tokens: ' . json_encode([
            'oauth_token_exists' => !empty($updatedProfile->discogsOAuthToken),
            'oauth_token_secret_exists' => !empty($updatedProfile->discogsOAuthTokenSecret)
        ]));

        $db->updateUserProfile($updatedProfile);

        // Verify the update
        $verifyProfile = $db->getUserProfile($userId);
        Logger::log('Verified profile OAuth tokens after save: ' . json_encode([
            'oauth_token_exists' => !empty($verifyProfile->discogsOAuthToken),
            'oauth_token_secret_exists' => !empty($verifyProfile->discogsOAuthTokenSecret)
        ]));

        // Clean up session
        unset($_SESSION['oauth_token_secret']);

        Session::setMessage('Successfully connected to your Discogs account!');
        header('Location: ?action=profile');
        exit;
    } catch (Exception $e) {
        Logger::error('OAuth callback error: ' . $e->getMessage());
        Session::setErrors(['Failed to complete Discogs authorization: ' . $e->getMessage()]);
        header('Location: ?action=profile_edit');
        exit;
    }
}

// Start OAuth process
try {
    // Create OAuth handler
    $oauth = new DiscogsOAuth(
        consumerKey: $profile->discogsConsumerKey,
        consumerSecret: $profile->discogsConsumerSecret,
        userAgent: 'DiscogsHelper/1.0',
        callbackUrl: 'http://' . $_SERVER['HTTP_HOST'] . '/?action=discogs_auth'
    );

    // Get request token
    $tokens = $oauth->getRequestToken();

    // Store request token secret in session
    $_SESSION['oauth_token_secret'] = $tokens['oauth_token_secret'];

    // Get authorization URL
    $authorizeUrl = $oauth->getAuthorizeUrl($tokens['oauth_token']);

    // Redirect to Discogs
    header('Location: ' . $authorizeUrl);
    exit;
} catch (Exception $e) {
    Logger::error('OAuth initialization error: ' . $e->getMessage());
    Session::setErrors(['Failed to start Discogs authorization: ' . $e->getMessage()]);
    header('Location: ?action=profile_edit');
    exit;
} 