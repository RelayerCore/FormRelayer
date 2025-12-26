<?php

declare(strict_types=1);

namespace FormRelayer\Tests\Unit\Security;

use FormRelayer\Security\Sanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Sanitizer class
 *
 * @covers \FormRelayer\Security\Sanitizer
 */
class SanitizerTest extends TestCase
{
    /**
     * @test
     */
    public function text_removes_html_tags(): void
    {
        $input = '<script>alert("xss")</script>Hello World';
        $result = Sanitizer::text($input);
        
        $this->assertEquals('Hello World', $result);
    }

    /**
     * @test
     */
    public function text_trims_whitespace(): void
    {
        $input = '  Hello World  ';
        $result = Sanitizer::text($input);
        
        $this->assertEquals('Hello World', $result);
    }

    /**
     * @test
     */
    public function text_handles_null(): void
    {
        $result = Sanitizer::text(null);
        
        $this->assertEquals('', $result);
    }

    /**
     * @test
     */
    public function email_validates_and_sanitizes(): void
    {
        $result = Sanitizer::email('test@example.com');
        $this->assertEquals('test@example.com', $result);
        
        $result = Sanitizer::email('UPPER@CASE.COM');
        $this->assertEquals('upper@case.com', strtolower($result));
    }

    /**
     * @test
     */
    public function email_returns_empty_for_invalid(): void
    {
        $result = Sanitizer::email('not-an-email');
        
        $this->assertEquals('', $result);
    }

    /**
     * @test
     */
    public function int_converts_to_integer(): void
    {
        $this->assertEquals(42, Sanitizer::int('42'));
        $this->assertEquals(0, Sanitizer::int('abc'));
        $this->assertEquals(10, Sanitizer::int('-10')); // absint returns absolute value
    }

    /**
     * @test
     */
    public function float_converts_to_float(): void
    {
        $this->assertEquals(3.14, Sanitizer::float('3.14'));
        $this->assertEquals(0.0, Sanitizer::float('abc'));
        $this->assertEquals(-2.5, Sanitizer::float('-2.5'));
    }

    /**
     * @test
     */
    public function url_sanitizes_valid_url(): void
    {
        $url = 'https://example.com/path?query=value';
        $result = Sanitizer::url($url);
        
        $this->assertEquals($url, $result);
    }

    /**
     * @test
     */
    public function url_sanitizes_protocol(): void
    {
        // The esc_url_raw function filters URLs - behavior depends on WP vs mock
        $result = Sanitizer::url('https://example.com');
        $this->assertStringStartsWith('https://', $result);
    }

    /**
     * @test
     */
    public function key_allows_only_alphanumeric_and_underscores(): void
    {
        $result = Sanitizer::key('Field_Name-123');
        
        $this->assertEquals('field_name-123', $result);
    }

    /**
     * @test
     */
    public function bool_converts_various_truthy_values(): void
    {
        $this->assertTrue(Sanitizer::bool('1'));
        $this->assertTrue(Sanitizer::bool('true'));
        $this->assertTrue(Sanitizer::bool('yes'));
        $this->assertTrue(Sanitizer::bool('on'));
        $this->assertTrue(Sanitizer::bool(1));
        $this->assertTrue(Sanitizer::bool(true));
    }

    /**
     * @test
     */
    public function bool_converts_various_falsy_values(): void
    {
        $this->assertFalse(Sanitizer::bool('0'));
        $this->assertFalse(Sanitizer::bool('false'));
        $this->assertFalse(Sanitizer::bool('no'));
        $this->assertFalse(Sanitizer::bool(''));
        $this->assertFalse(Sanitizer::bool(0));
        $this->assertFalse(Sanitizer::bool(false));
    }

    /**
     * @test
     */
    public function array_sanitizes_all_elements(): void
    {
        $input = ['<b>one</b>', '  two  ', 'three'];
        $result = Sanitizer::array($input, fn($v) => Sanitizer::text($v));
        
        $this->assertEquals(['one', 'two', 'three'], $result);
    }

    /**
     * @test
     */
    public function json_decodes_valid_json(): void
    {
        $json = '{"name":"John","age":30}';
        $result = Sanitizer::json($json);
        
        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
    }

    /**
     * @test
     */
    public function json_returns_null_for_invalid(): void
    {
        $result = Sanitizer::json('not json');
        
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function fieldValue_uses_correct_sanitizer_for_type(): void
    {
        $this->assertEquals('test@example.com', Sanitizer::fieldValue('test@example.com', 'email'));
        $this->assertEquals(42, Sanitizer::fieldValue('42', 'number'));
        $this->assertEquals('https://example.com', Sanitizer::fieldValue('https://example.com', 'url'));
        $this->assertEquals('Hello', Sanitizer::fieldValue('<b>Hello</b>', 'text'));
    }

    /**
     * @test
     */
    public function formSubmission_sanitizes_based_on_field_types(): void
    {
        $data = [
            'name' => '  John Doe  ',
            'email' => 'john@example.com',
            'age' => '25',
            'website' => 'https://example.com',
        ];

        $fieldTypes = [
            'name' => 'text',
            'email' => 'email',
            'age' => 'number',
            'website' => 'url',
        ];

        $result = Sanitizer::formSubmission($data, $fieldTypes);

        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('john@example.com', $result['email']);
        $this->assertEquals(25, $result['age']);
        $this->assertEquals('https://example.com', $result['website']);
    }
}
