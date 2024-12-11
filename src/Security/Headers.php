<?php

declare(strict_types=1);

namespace DiscogsHelper\Security;

final class Headers
{
    private const array HEADERS = [
        'Content-Security-Policy' => [
            "default-src 'self'",
            "img-src 'self' https://api.discogs.com",
            "script-src 'self' 'unsafe-inline'",  // Allow inline scripts if needed
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net", // Allow Water.css CDN
            "form-action 'self'",
        ],
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains'
    ];

    public static function apply(): void
    {
        foreach (self::HEADERS as $header => $value) {
            if (is_array($value)) {
                $value = implode('; ', $value);
            }
            header("$header: $value");
        }
    }
}
