<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions;

use DevToolbelt\LaravelFastCrud\Actions\ExportCsv;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use ReflectionMethod;

final class ExportCsvWriteLineTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new class {
            use ExportCsv;

            protected function modelClassName(): string
            {
                return '';
            }

            public function testWriteCsvLine(array $fields): string
            {
                $handle = fopen('php://memory', 'w+');
                $reflection = new ReflectionMethod($this, 'writeCsvLine');
                $reflection->invoke($this, $handle, $fields);
                rewind($handle);
                $content = stream_get_contents($handle);
                fclose($handle);
                return $content;
            }
        };
    }

    public function testWriteCsvLineWithSimpleValues(): void
    {
        $result = $this->controller->testWriteCsvLine(['John', 'Doe', '30']);

        $this->assertSame("John,Doe,30\n", $result);
    }

    public function testWriteCsvLineWithCommaInValue(): void
    {
        $result = $this->controller->testWriteCsvLine(['John, Jr.', 'Doe']);

        $this->assertSame("\"John, Jr.\",Doe\n", $result);
    }

    public function testWriteCsvLineWithNewlineInValue(): void
    {
        $result = $this->controller->testWriteCsvLine(["Line 1\nLine 2", 'Value']);

        $this->assertSame("\"Line 1\nLine 2\",Value\n", $result);
    }

    public function testWriteCsvLineReplacesDoubleQuotesWithSingleQuotes(): void
    {
        $result = $this->controller->testWriteCsvLine(['Model with "quotes"', 'Value']);

        // Double quotes should be replaced with single quotes
        $this->assertSame("Model with 'quotes',Value\n", $result);
        $this->assertStringNotContainsString('""', $result);
    }

    public function testWriteCsvLineWithQuotesAndCommas(): void
    {
        $result = $this->controller->testWriteCsvLine(['Model with "quotes" and, commas', 'Value']);

        // Double quotes replaced with single quotes, then wrapped in quotes due to comma
        $this->assertSame("\"Model with 'quotes' and, commas\",Value\n", $result);
        $this->assertStringNotContainsString('""', $result);
    }

    public function testWriteCsvLineWithOnlyQuotes(): void
    {
        $result = $this->controller->testWriteCsvLine(['Say "Hello"', 'World']);

        // Only quotes, no comma - should not wrap in quotes
        $this->assertSame("Say 'Hello',World\n", $result);
    }

    public function testWriteCsvLineWithEmptyValues(): void
    {
        $result = $this->controller->testWriteCsvLine(['', 'Value', '']);

        $this->assertSame(",Value,\n", $result);
    }

    public function testWriteCsvLineWithNumericValues(): void
    {
        $result = $this->controller->testWriteCsvLine([123, 45.67, '89']);

        $this->assertSame("123,45.67,89\n", $result);
    }

    public function testWriteCsvLineWithSpecialCharactersNoQuotes(): void
    {
        $result = $this->controller->testWriteCsvLine(['Hello!', '@email.com', '#hashtag']);

        $this->assertSame("Hello!,@email.com,#hashtag\n", $result);
    }

    public function testWriteCsvLineWithMultipleQuotesInSameField(): void
    {
        $result = $this->controller->testWriteCsvLine(['He said "Hello" and she said "Hi"']);

        $this->assertSame("He said 'Hello' and she said 'Hi'\n", $result);
    }

    public function testWriteCsvLineWithQuotesCommasAndNewlines(): void
    {
        $result = $this->controller->testWriteCsvLine(["Complex \"value\" with,\neverything"]);

        // Quotes replaced, wrapped due to comma and newline
        $this->assertSame("\"Complex 'value' with,\neverything\"\n", $result);
    }
}
