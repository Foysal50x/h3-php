<?php

declare(strict_types=1);

namespace Foysal50x\H3\Tests;

use Foysal50x\H3\H3Exception;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for H3Exception.
 */
class H3ExceptionTest extends TestCase
{
    public function testExceptionMessage(): void
    {
        $exception = new H3Exception('Test error', 5);

        $this->assertStringContainsString('Test error', $exception->getMessage());
        $this->assertStringContainsString('H3Index cell argument was not valid', $exception->getMessage());
        $this->assertStringContainsString('error code: 5', $exception->getMessage());
    }

    public function testGetH3ErrorCode(): void
    {
        $exception = new H3Exception('Test', 4);
        $this->assertEquals(4, $exception->getH3ErrorCode());
    }

    public function testGetH3ErrorDescription(): void
    {
        $exception = new H3Exception('Test', 4);
        $this->assertEquals('Resolution argument was outside of acceptable range', $exception->getH3ErrorDescription());
    }

    public function testIsInvalidCell(): void
    {
        $invalidCellException = new H3Exception('Test', 5);
        $otherException = new H3Exception('Test', 4);

        $this->assertTrue($invalidCellException->isInvalidCell());
        $this->assertFalse($otherException->isInvalidCell());
    }

    public function testIsInvalidResolution(): void
    {
        $invalidResException = new H3Exception('Test', 4);
        $otherException = new H3Exception('Test', 5);

        $this->assertTrue($invalidResException->isInvalidResolution());
        $this->assertFalse($otherException->isInvalidResolution());
    }

    public function testIsPentagonError(): void
    {
        $pentagonException = new H3Exception('Test', 9);
        $otherException = new H3Exception('Test', 5);

        $this->assertTrue($pentagonException->isPentagonError());
        $this->assertFalse($otherException->isPentagonError());
    }

    public function testIsNotNeighborsError(): void
    {
        $notNeighborsException = new H3Exception('Test', 11);
        $otherException = new H3Exception('Test', 5);

        $this->assertTrue($notNeighborsException->isNotNeighborsError());
        $this->assertFalse($otherException->isNotNeighborsError());
    }

    public function testUnknownErrorCode(): void
    {
        $exception = new H3Exception('Test', 999);

        $this->assertEquals('Unknown error', $exception->getH3ErrorDescription());
        $this->assertStringContainsString('Unknown error', $exception->getMessage());
    }
}
