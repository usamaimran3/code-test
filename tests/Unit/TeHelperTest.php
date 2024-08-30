<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use DTApi\Helpers\TeHelper;

class TeHelperTest extends TestCase
{
    public function testWillExpireAtWithDifferenceLessThanOrEqualTo90Hours(): void
    {
        $dueTime = '2024-08-30 12:00:00';
        $createdAt = '2024-08-26 12:00:00';

        $result = TeHelper::willExpireAt($dueTime, $createdAt);

        $this->assertEquals('2024-08-30 12:00:00', $result);
    }

    public function testWillExpireAtWithDifferenceLessThanOrEqualTo24Hours(): void
    {
        $dueTime = '2024-08-30 12:00:00';
        $createdAt = '2024-08-29 10:00:00';

        $result = TeHelper::willExpireAt($dueTime, $createdAt);

        $this->assertEquals('2024-08-29 11:30:00', $result);
    }

    public function testWillExpireAtWithDifferenceBetween24And72Hours(): void
    {
        $dueTime = '2024-08-30 12:00:00';
        $createdAt = '2024-08-27 12:00:00';

        $result = TeHelper::willExpireAt($dueTime, $createdAt);

        $this->assertEquals('2024-08-28 04:00:00', $result);
    }

    public function testWillExpireAtWithDifferenceGreaterThan72Hours(): void
    {
        $dueTime = '2024-09-01 12:00:00';
        $createdAt = '2024-08-26 12:00:00';

        $result = TeHelper::willExpireAt($dueTime, $createdAt);

        $this->assertEquals('2024-08-30 12:00:00', $result);
    }
}
