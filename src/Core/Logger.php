<?php

declare(strict_types=1);

namespace FormRelayer\Core;

/**
 * Logger
 *
 * Debug mode logging for troubleshooting
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class Logger
{
    private static ?Logger $instance = null;

    private const LOG_DIR = WP_CONTENT_DIR . '/fr-logs';

    private bool $enabled;

    private function __construct()
    {
        $this->enabled = (bool) get_option('fr_enable_debug', false);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log a debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Log an info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Log a warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Log an error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Write log entry
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled && $level !== 'ERROR') {
            return;
        }

        $this->ensureLogDirectory();

        $logFile = self::LOG_DIR . '/formrelayer-' . gmdate('Y-m-d') . '.log';

        $entry = sprintf(
            "[%s] [%s] %s%s\n",
            gmdate('Y-m-d H:i:s'),
            $level,
            $message,
            !empty($context) ? ' ' . wp_json_encode($context) : ''
        );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

        $this->cleanOldLogs();
    }

    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory(): void
    {
        if (!file_exists(self::LOG_DIR)) {
            wp_mkdir_p(self::LOG_DIR);

            // Add .htaccess to protect logs
            $htaccess = self::LOG_DIR . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all\n");
            }

            // Add index.php
            $index = self::LOG_DIR . '/index.php';
            if (!file_exists($index)) {
                file_put_contents($index, "<?php // Silence is golden.\n");
            }
        }
    }

    /**
     * Clean logs older than 7 days
     */
    private function cleanOldLogs(): void
    {
        static $cleaned = false;

        if ($cleaned) {
            return;
        }

        $cleaned = true;
        $files = glob(self::LOG_DIR . '/formrelayer-*.log');

        if (!$files) {
            return;
        }

        $cutoff = strtotime('-7 days');

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                unlink($file);
            }
        }
    }

    /**
     * Get recent log entries
     */
    public function getRecentLogs(int $lines = 100): array
    {
        $logFile = self::LOG_DIR . '/formrelayer-' . gmdate('Y-m-d') . '.log';

        if (!file_exists($logFile)) {
            return [];
        }

        $content = file_get_contents($logFile);
        $allLines = explode("\n", trim($content));

        return array_slice($allLines, -$lines);
    }

    /**
     * Check if logging is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable/disable logging
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        update_option('fr_enable_debug', $enabled ? 1 : 0);
    }
}
