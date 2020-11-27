<?php

namespace App\Service;

use App\Entity\Alert;
use App\Entity\Log\Row;
use App\Entity\Queue;

final class TrafficMonitor
{
    private Queue $logsQueue;

    private ?Alert $currentAlert = null;

    private int $alertTimeWindow;

    private int $threshold;

    /**
     * @param int $alertTimeWindow The time expressed as seconds at which logs are taken into account
     * @param int $threshold Average amount of requests per second - once exceeded during alertTimeWindow, it will trigger the alert
     */
    public function __construct(int $alertTimeWindow, int $threshold)
    {
        $this->logsQueue = new Queue();
        $this->alertTimeWindow = $alertTimeWindow;
        $this->threshold = $threshold;
    }

    /**
     * Pushes the log into the HighTrafficAlertMonitor's queue in a time sorted manner.
     * Dequeues logs older than configured alert time window.
     *
     * @param Row $logRow
     */
    public function pushLog(Row $logRow): void
    {
        $this->logsQueue->pushLog($logRow);
        $this->logsQueue->flush($this->alertTimeWindow);
    }

    public function monitor(): ?Alert
    {
        $queue = $this->logsQueue->getQueue();
        /** @var Row $latestLog */
        $latestLog = $queue->top();
        /** @var Row $oldestLog */
        $oldestLog = $queue->bottom();
        $secondsOfLogsInQueue = $latestLog->getTimestamp() - $oldestLog->getTimestamp();
        $averageTraffic = $queue->count() / $this->alertTimeWindow;

        if (
            $this->currentAlert === null
            && $secondsOfLogsInQueue >= $this->alertTimeWindow - 1
            && $averageTraffic > $this->threshold
        ) {
            $this->currentAlert = new Alert($latestLog->getTimestamp(), $averageTraffic);

            return $this->currentAlert;
        }

        if (
            $this->currentAlert !== null
            && $this->currentAlert->isActive()
            && $secondsOfLogsInQueue >= $this->alertTimeWindow - 1
            && $averageTraffic < $this->threshold
        ) {
            $this->currentAlert->recover($latestLog->getTimestamp());

            return $this->currentAlert;
        }

        return null;
    }

    public function getCountOfLogsInQueue(): int
    {
        return $this->logsQueue->getQueue()->count();
    }
}