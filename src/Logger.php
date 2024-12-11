<?php

declare(strict_types=1);

namespace DiscogsHelper;

final class Logger
{
    private static string $logFile;

    public static function initialize(string $projectRoot): void
    {
        $logDir = $projectRoot . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        self::$logFile = $logDir . '/app.log';
    }

    public static function log(string $message, string $type = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents(self::$logFile, $formattedMessage, FILE_APPEND);
    }

    public static function error(string $message): void
    {
        self::log($message, 'ERROR');
    }
}