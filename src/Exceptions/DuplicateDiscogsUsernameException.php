<?php
// Add to src/Exceptions/DuplicateDiscogsUsernameException.php

declare(strict_types=1);

namespace DiscogsHelper\Exceptions;

use RuntimeException;

final class DuplicateDiscogsUsernameException extends RuntimeException
{
    public function __construct(string $discogsUsername)
    {
        parent::__construct(
            "A profile with Discogs username '{$discogsUsername}' already exists."
        );
    }
}