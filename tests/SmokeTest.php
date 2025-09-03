<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tns\Epic\Epic;

final class SmokeTest extends TestCase
{
    public function testFactory(): void
    {
        $this->assertTrue(class_exists(Epic::class));
    }
}
