<?php

declare(strict_types=1);

namespace FormRelayer\Tests\Unit\Core;

use FormRelayer\Core\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Logger class
 *
 * @covers \FormRelayer\Core\Logger
 */
class LoggerTest extends TestCase
{
    private ?Logger $logger = null;
    private string $testLogDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock WP_CONTENT_DIR
        if (!defined('WP_CONTENT_DIR')) {
            define('WP_CONTENT_DIR', sys_get_temp_dir());
        }
        
        $this->testLogDir = WP_CONTENT_DIR . '/fr-logs';
        
        // Enable debug for testing
        update_option('fr_enable_debug', 1);
        
        $this->logger = Logger::getInstance();
    }

    protected function tearDown(): void
    {
        // Clean up test logs
        $files = glob($this->testLogDir . '/formrelayer-*.log');
        foreach ($files ?: [] as $file) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test cleanup
            unlink($file);
        }
        
        if (is_dir($this->testLogDir)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test cleanup
            @rmdir($this->testLogDir);
        }
        
        parent::tearDown();
    }

    /**
     * @test
     */
    public function getInstance_returns_singleton(): void
    {
        $instance1 = Logger::getInstance();
        $instance2 = Logger::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }

    /**
     * @test
     */
    public function debug_logs_when_enabled(): void
    {
        $this->logger->setEnabled(true);
        $this->logger->debug('Test debug message');
        
        $logs = $this->logger->getRecentLogs(10);
        
        $this->assertNotEmpty($logs);
        $this->assertStringContainsString('DEBUG', end($logs));
        $this->assertStringContainsString('Test debug message', end($logs));
    }

    /**
     * @test
     */
    public function error_logs_even_when_disabled(): void
    {
        $this->logger->setEnabled(false);
        $this->logger->error('Critical error');
        
        $logs = $this->logger->getRecentLogs(10);
        
        $this->assertNotEmpty($logs);
        $this->assertStringContainsString('ERROR', end($logs));
    }

    /**
     * @test
     */
    public function log_includes_context(): void
    {
        $this->logger->setEnabled(true);
        $this->logger->info('User action', ['user_id' => 123, 'action' => 'login']);
        
        $logs = $this->logger->getRecentLogs(10);
        $lastLog = end($logs);
        
        $this->assertStringContainsString('user_id', $lastLog);
        $this->assertStringContainsString('123', $lastLog);
    }

    /**
     * @test
     */
    public function isEnabled_returns_correct_state(): void
    {
        $this->logger->setEnabled(true);
        $this->assertTrue($this->logger->isEnabled());
        
        $this->logger->setEnabled(false);
        $this->assertFalse($this->logger->isEnabled());
    }
}
