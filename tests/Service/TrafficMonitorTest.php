<?php

namespace App\Tests\Service;

use App\Entity\Alert;
use App\Entity\Log\Row;
use App\Service\TrafficMonitor;
use PHPUnit\Framework\TestCase;

class TrafficMonitorTest extends TestCase
{
    private TrafficMonitor $sut;

    protected function setUp()
    {
        $this->sut = new TrafficMonitor(2, 10);
    }

    public function testPushLog()
    {
        static::assertEquals(0, $this->sut->getCountOfLogsInQueue());
        $this->sut->pushLog($this->createNewLogRow(time()));
        static::assertEquals(1, $this->sut->getCountOfLogsInQueue());
    }

    public function testMonitorTriggersAndRecoversFromAlert()
    {
        static::assertEquals(0, $this->sut->getCountOfLogsInQueue());
        $time = time();
        for ($secondsOffset = 0; $secondsOffset < 2; $secondsOffset++) {
            for ($i = 0; $i < 12; $i++) {
                $this->sut->pushLog($this->createNewLogRow($time + $secondsOffset));
            }
        }

        static::assertEquals(24, $this->sut->getCountOfLogsInQueue());
        $alert = $this->sut->monitor();

        static::assertInstanceOf(Alert::class, $alert);
        static::assertEquals(12, $alert->getAverageHits());
        static::assertTrue($alert->isActive());

        $time = time() + 2;
        for ($secondsOffset = 0; $secondsOffset < 2; $secondsOffset++) {
            for ($i = 0; $i < 5; $i++) {
                $this->sut->pushLog($this->createNewLogRow($time + $secondsOffset));
            }
        }

        $alert = $this->sut->monitor();
        static::assertInstanceOf(Alert::class, $alert);
        static::assertFalse($alert->isActive());
    }

    public function testMonitorDoesNotTriggerAlert()
    {
        static::assertEquals(0, $this->sut->getCountOfLogsInQueue());
        $time = time();
        for ($secondsOffset = 0; $secondsOffset < 2; $secondsOffset++) {
            for ($i = 0; $i < 6; $i++) {
                $this->sut->pushLog($this->createNewLogRow($time + $secondsOffset));
            }
        }

        static::assertEquals(12, $this->sut->getCountOfLogsInQueue());
        $alert = $this->sut->monitor();

        static::assertNull($alert);
    }

    private function createNewLogRow(int $time): Row
    {
        return new Row(
            '10.0.0.2',
            '-',
            'apache',
            $time,
            'GET /api/user HTTP/1.0',
            200,
            123
        );
    }
}
