<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\DirectiveRequest;

class DirectiveRequestTest extends TestCase
{
    // ====================================================================
    // Constructor with all parameters
    // ====================================================================

    public function testConstructorSetsAllPropertiesWhenAllParamsProvided(): void
    {
        $id = '42';
        $name = 'App\Core\SomeDirective';
        $config = ['key-header' => 'x-test', 'value-header' => 'test-value'];
        $groups = ['normal', 'error'];

        $directiveRequest = new DirectiveRequest($id, $name, $config, $groups);

        $this->assertSame($id, $directiveRequest->id);
        $this->assertSame($name, $directiveRequest->name);
        $this->assertSame($config, $directiveRequest->config);
        $this->assertSame($groups, $directiveRequest->groups);
    }

    // ====================================================================
    // Constructor with default groups (null)
    // ====================================================================

    public function testConstructorSetsGroupsToNullWhenNotProvided(): void
    {
        $directiveRequest = new DirectiveRequest('1', 'SomeDirective', []);

        $this->assertSame('1', $directiveRequest->id);
        $this->assertSame('SomeDirective', $directiveRequest->name);
        $this->assertSame([], $directiveRequest->config);
        $this->assertNull($directiveRequest->groups);
    }

    // ====================================================================
    // Type integrity
    // ====================================================================

    public function testPropertiesHaveCorrectTypes(): void
    {
        $directiveRequest = new DirectiveRequest('10', 'MyDirective', ['status' => 200], ['admin']);

        $this->assertIsString($directiveRequest->id);
        $this->assertIsString($directiveRequest->name);
        $this->assertIsArray($directiveRequest->config);
        $this->assertIsArray($directiveRequest->groups);
    }

    // ====================================================================
    // Edge cases
    // ====================================================================

    public function testConstructorAcceptsEmptyConfigArray(): void
    {
        $directiveRequest = new DirectiveRequest('1', 'EmptyConfigDirective', []);

        $this->assertSame([], $directiveRequest->config);
        $this->assertNull($directiveRequest->groups);
    }

    public function testConstructorAcceptsEmptyGroupsArray(): void
    {
        $directiveRequest = new DirectiveRequest('2', 'EmptyGroupsDirective', ['status' => 201], []);

        $this->assertSame([], $directiveRequest->groups);
    }

    public function testConstructorAcceptsConfigWithMultipleKeys(): void
    {
        $config = [
            'key-header' => 'x-custom',
            'value-header' => 'custom-value',
            'status' => 404,
            'body' => 'not found',
            'key-memory' => 'myvar',
            'value-memory' => 'myvalue',
        ];

        $directiveRequest = new DirectiveRequest('5', 'MultiKeyDirective', $config);

        $this->assertSame($config, $directiveRequest->config);
        $this->assertNull($directiveRequest->groups);
    }

    public function testConstructorPreservesStringId(): void
    {
        $directiveRequest = new DirectiveRequest('0', 'ZeroIdDirective', ['path' => '/test']);

        $this->assertSame('0', $directiveRequest->id);
        $this->assertIsString($directiveRequest->id);
    }

    public function testConstructorPreservesNumericStringId(): void
    {
        $directiveRequest = new DirectiveRequest('999', 'NumericIdDirective', []);

        $this->assertSame('999', $directiveRequest->id);
        $this->assertIsString($directiveRequest->id);
    }
}