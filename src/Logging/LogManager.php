<?php

declare(strict_types=1);

namespace DiscogsHelper\Logging;

final class LogManager
{
    private const int|float MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const int MAX_FILES = 30; // Keep 30 days of logs
    private const string COMPRESSION_EXTENSION = 'gz';

    public function __construct(
        private readonly string $logDirectory,
        private readonly string $logName = 'application.log'
    ) {
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0755, true);
        }
    }

    public function write(string $message): void
    {
        $logFile = $this->getCurrentLogPath();

        // Check if rotation needed
        if ($this->shouldRotate($logFile)) {
            $this->rotate();
        }

        // Write the log entry
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = sprintf('[%s] %s%s', $timestamp, $message, PHP_EOL);
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    private function shouldRotate(string $logFile): bool
    {
        if (!file_exists($logFile)) {
            return false;
        }

        return filesize($logFile) >= self::MAX_FILE_SIZE;
    }

    private function rotate(): void
    {
        $currentLog = $this->getCurrentLogPath();
        if (!file_exists($currentLog)) {
            return;
        }

        // Generate new filename with date
        $date = date('Y-m-d');
        $newName = sprintf(
            '%s/%s-%s.log',
            $this->logDirectory,
            pathinfo($this->logName, PATHINFO_FILENAME),
            $date
        );

        // If file exists, append timestamp to make unique
        if (file_exists($newName)) {
            $newName = sprintf(
                '%s/%s-%s-%s.log',
                $this->logDirectory,
                pathinfo($this->logName, PATHINFO_FILENAME),
                $date,
                date('His')
            );
        }

        // Rotate the file
        rename($currentLog, $newName);

        // Compress the rotated file
        $this->compressLog($newName);

        // Clean old logs
        $this->cleanOldLogs();
    }

    private function compressLog(string $logFile): void
    {
        $compressedFile = $logFile . '.' . self::COMPRESSION_EXTENSION;
        $handle = fopen($logFile, 'rb');
        $gzHandle = gzopen($compressedFile, 'wb9');

        while (!feof($handle)) {
            gzwrite($gzHandle, fread($handle, 8192));
        }

        fclose($handle);
        gzclose($gzHandle);
        unlink($logFile); // Remove uncompressed file
    }

    private function cleanOldLogs(): void
    {
        $files = glob($this->logDirectory . '/*.log.' . self::COMPRESSION_EXTENSION);
        if ($files === false) {
            return;
        }

        // Sort files by modification time
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        // Remove files beyond retention period
        foreach (array_slice($files, self::MAX_FILES) as $file) {
            unlink($file);
        }
    }

    private function getCurrentLogPath(): string
    {
        return $this->logDirectory . '/' . $this->logName;
    }
}