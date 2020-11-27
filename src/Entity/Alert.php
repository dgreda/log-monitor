<?php

namespace App\Entity;


final class Alert
{
    private int $alertStartedTimestamp;

    private int $alertRecoveredTimestamp;

    private int $averageHits;

    private bool $isActive;

    public function __construct(int $alertStartedTimestamp, int $averageHits)
    {
        $this->alertStartedTimestamp = $alertStartedTimestamp;
        $this->averageHits = $averageHits;
        $this->isActive = true;
    }

    public function recover(int $alertRecoveredTimestamp): void
    {
        $this->alertRecoveredTimestamp = $alertRecoveredTimestamp;
        $this->isActive = false;
    }

    public function getAlertStartedTimestamp(): int
    {
        return $this->alertStartedTimestamp;
    }

    public function getAlertRecoveredTimestamp(): int
    {
        return $this->alertRecoveredTimestamp;
    }

    public function getAverageHits(): int
    {
        return $this->averageHits;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getAlertDurationInSeconds(): ?int
    {
        if ($this->isActive()) {
            return null;
        }

        return $this->getAlertRecoveredTimestamp() - $this->getAlertStartedTimestamp();
    }
}