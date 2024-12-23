<?php

declare(strict_types=1);

namespace DiscogsHelper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use RuntimeException;

final class DiscogsOAuth
{
    private const string BASE_URL = 'https://api.discogs.com';
    private const string REQUEST_TOKEN_URL = '/oauth/request_token';
    private const string AUTHORIZE_URL = 'https://www.discogs.com/oauth/authorize';
    private const string ACCESS_TOKEN_URL = '/oauth/access_token';

    private Client $client;

    public function __construct(
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
        private readonly string $userAgent,
        private readonly string $callbackUrl
    ) {
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'headers' => [
                'User-Agent' => $userAgent,
            ],
        ]);
    }

    public function getRequestToken(): array
    {
        try {
            $nonce = bin2hex(random_bytes(16));
            $timestamp = time();
            $signature = $this->consumerSecret . '&';

            $response = $this->client->get(self::REQUEST_TOKEN_URL, [
                'headers' => [
                    'Authorization' => sprintf(
                        'OAuth oauth_consumer_key="%s", oauth_nonce="%s", oauth_signature="%s", oauth_signature_method="PLAINTEXT", oauth_timestamp="%d", oauth_callback="%s"',
                        $this->consumerKey,
                        $nonce,
                        $signature,
                        $timestamp,
                        urlencode($this->callbackUrl)
                    ),
                ],
            ]);

            $data = [];
            parse_str($response->getBody()->getContents(), $data);

            if (!isset($data['oauth_token'], $data['oauth_token_secret'])) {
                throw new RuntimeException('Invalid response from Discogs OAuth request token endpoint');
            }

            return [
                'oauth_token' => $data['oauth_token'],
                'oauth_token_secret' => $data['oauth_token_secret'],
            ];
        } catch (ClientException $e) {
            Logger::error('Failed to get request token: ' . $e->getMessage());
            throw new RuntimeException('Failed to get OAuth request token from Discogs');
        }
    }

    public function getAuthorizeUrl(string $requestToken): string
    {
        return self::AUTHORIZE_URL . '?oauth_token=' . urlencode($requestToken);
    }

    public function getAccessToken(string $requestToken, string $requestTokenSecret, string $verifier): array
    {
        try {
            $nonce = bin2hex(random_bytes(16));
            $timestamp = time();
            $signature = $this->consumerSecret . '&' . $requestTokenSecret;

            $response = $this->client->post(self::ACCESS_TOKEN_URL, [
                'headers' => [
                    'Authorization' => sprintf(
                        'OAuth oauth_consumer_key="%s", oauth_nonce="%s", oauth_token="%s", oauth_signature="%s", oauth_signature_method="PLAINTEXT", oauth_timestamp="%d", oauth_verifier="%s"',
                        $this->consumerKey,
                        $nonce,
                        $requestToken,
                        $signature,
                        $timestamp,
                        $verifier
                    ),
                ],
            ]);

            $data = [];
            parse_str($response->getBody()->getContents(), $data);

            if (!isset($data['oauth_token'], $data['oauth_token_secret'])) {
                throw new RuntimeException('Invalid response from Discogs OAuth access token endpoint');
            }

            return [
                'oauth_token' => $data['oauth_token'],
                'oauth_token_secret' => $data['oauth_token_secret'],
            ];
        } catch (ClientException $e) {
            Logger::error('Failed to get access token: ' . $e->getMessage());
            throw new RuntimeException('Failed to get OAuth access token from Discogs');
        }
    }
} 