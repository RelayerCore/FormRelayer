<?php

declare(strict_types=1);

namespace FormRelayer\Tests\Unit\Security;

use FormRelayer\Security\Nonce;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Nonce class
 *
 * @covers \FormRelayer\Security\Nonce
 */
class NonceTest extends TestCase
{
    /**
     * @test
     */
    public function create_returns_string(): void
    {
        $nonce = Nonce::create('test_action');
        
        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
    }

    /**
     * @test
     */
    public function same_action_creates_same_nonce(): void
    {
        $nonce1 = Nonce::create('test_action');
        $nonce2 = Nonce::create('test_action');
        
        $this->assertEquals($nonce1, $nonce2);
    }

    /**
     * @test
     */
    public function different_actions_create_different_nonces(): void
    {
        $nonce1 = Nonce::create('action_one');
        $nonce2 = Nonce::create('action_two');
        
        $this->assertNotEquals($nonce1, $nonce2);
    }

    /**
     * @test
     */
    public function verify_returns_true_for_valid_nonce(): void
    {
        $action = 'verify_test';
        $_REQUEST['nonce'] = Nonce::create($action);
        
        $result = Nonce::verify($action);
        
        $this->assertTrue($result);
        
        unset($_REQUEST['nonce']);
    }

    /**
     * @test
     */
    public function verify_returns_false_for_invalid_nonce(): void
    {
        $_REQUEST['nonce'] = 'invalid_nonce_value';
        
        $result = Nonce::verify('test_action');
        
        $this->assertFalse($result);
        
        unset($_REQUEST['nonce']);
    }

    /**
     * @test
     */
    public function verify_returns_false_for_missing_nonce(): void
    {
        unset($_REQUEST['nonce']);
        
        $result = Nonce::verify('test_action');
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function verifyRequest_uses_custom_nonce_field(): void
    {
        $action = 'custom_field_test';
        $_REQUEST['my_nonce'] = Nonce::create($action);
        
        $result = Nonce::verifyRequest($action, 'my_nonce');
        
        $this->assertTrue($result);
        
        unset($_REQUEST['my_nonce']);
    }

    /**
     * @test
     */
    public function url_adds_nonce_parameter(): void
    {
        $url = 'https://example.com/page';
        $result = Nonce::url($url, 'test_action');
        
        $this->assertStringContainsString('_wpnonce=', $result);
    }

    /**
     * @test
     */
    public function field_returns_hidden_input(): void
    {
        $field = Nonce::field('form_action');
        
        $this->assertStringContainsString('<input', $field);
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="_wpnonce"', $field);
    }
}
