<?php

declare(strict_types=1);

namespace DiscogsHelper\Exceptions;

use Exception;

final class DuplicateReleaseException extends Exception
{
    public function __construct(int $discogsId)
    {
        parent::__construct("This release is already in your collection.");
    }
} 