<?php

namespace Tests\Unit\Time;

use App\Time\SystemClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ClockTest extends TestCase
{
    public function test_system_clock_returns_time_within_range(): void
    {
        // Arrange
        $clock = new SystemClock();
        $beforeTime = new DateTimeImmutable();

        // Act
        $clockTime = $clock->now();
        $afterTime = new DateTimeImmutable();

        // Assert - Time Range Testing
        $this->assertGreaterThanOrEqual($beforeTime->getTimestamp(), $clockTime->getTimestamp());
        $this->assertLessThanOrEqual($afterTime->getTimestamp(), $clockTime->getTimestamp());
    }

    public function test_system_clock_returns_correct_type_and_basic_functionality(): void
    {
        // Arrange
        $clock = new SystemClock();

        // Act
        $clockTime = $clock->now();

        // Assert
        $this->assertInstanceOf(DateTimeImmutable::class, $clockTime);
    }

    public function test_fake_clock_returns_set_time(): void
    {
        // Arrange
        $fixedTime = new DateTimeImmutable('2023-01-01 12:00:00');
        $clock = new FakeClock($fixedTime);

        // Act
        $clockTime = $clock->now();

        // Assert
        $this->assertEquals($fixedTime->getTimestamp(), $clockTime->getTimestamp());
    }

    public function test_fake_clock_time_can_be_changed(): void
    {
        // Arrange
        $initialTime = new DateTimeImmutable('2023-01-01 12:00:00');
        $newTime = new DateTimeImmutable('2023-06-15 18:30:45');
        $clock = new FakeClock($initialTime);

        // Act
        $clock->setTime($newTime);
        $clockTime = $clock->now();

        // Assert
        $this->assertEquals($newTime->getTimestamp(), $clockTime->getTimestamp());
    }
} 