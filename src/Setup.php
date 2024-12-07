<?php

declare(strict_types=1);

namespace DiscogsHelper;

use RuntimeException;

final class Setup
{
    public static function ensureDirectoryExists(string $path, int $permissions = 0755): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, $permissions, true)) {
                throw new RuntimeException("Failed to create directory: {$path}");
            }
        }

        if (!is_writable($path)) {
            throw new RuntimeException("Directory is not writable: {$path}");
        }
    }
} 