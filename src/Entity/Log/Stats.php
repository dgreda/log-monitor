<?php


namespace App\Entity\Log;


use App\Exception\Stats\SectionMetricAlreadyExistsException;
use App\Exception\Stats\TriedToIncrementNonIntegerMetricException;

final class Stats
{
    private array $stats = [];

    private int $firstTimestamp;

    private int $lastTimestamp;

    private bool $sorted = false;

    public function __construct(int $firstTimestamp, int $lastTimestamp)
    {
        $this->firstTimestamp = $firstTimestamp;
        $this->lastTimestamp = $lastTimestamp;
    }

    public function getFirstTimestamp(): int
    {
        return $this->firstTimestamp;
    }

    public function getLastTimestamp(): int
    {
        return $this->lastTimestamp;
    }

    /**
     * @param string $sectionName
     * @param string $metricName
     * @param mixed $metricValue
     * @throws SectionMetricAlreadyExistsException
     */
    public function addSectionMetric(string $sectionName, string $metricName, $metricValue): void
    {
        if (!isset($this->stats[$sectionName])) {
            $this->stats[$sectionName] = [];
        }

        if (isset($this->stats[$sectionName][$metricName])) {
            throw new SectionMetricAlreadyExistsException();
        }

        $this->stats[$sectionName][$metricName] = $metricValue;
    }

    /**
     * @param string $sectionName
     * @param string $metricName
     * @throws TriedToIncrementNonIntegerMetricException
     */
    public function incrementSectionMetric(string $sectionName, string $metricName): void
    {
        if (!isset($this->stats[$sectionName])) {
            $this->stats[$sectionName] = [];
        }

        if (!isset($this->stats[$sectionName][$metricName])) {
            $this->stats[$sectionName][$metricName] = 1;
        } else if (!is_int($this->stats[$sectionName][$metricName])) {
            throw new TriedToIncrementNonIntegerMetricException();
        } else {
            ++$this->stats[$sectionName][$metricName];
        }
    }

    public function getStats(): array
    {
        if ($this->sorted === false) {
            uasort($this->stats, [$this, 'compare']);
            $this->sorted = true;
        }

        return $this->stats;
    }

    public function hasSectionMetric(string $sectionName, string $metricName): bool
    {
        if (isset($this->stats[$sectionName]) && isset($this->stats[$sectionName][$metricName])) {
            return true;
        }

        return false;
    }

    /**
     * Used for sorting the stats by the amount of hits (traffic) to the section
     * in a descending order.
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    private function compare(array $a, array $b): int
    {
        if ($a['hits'] == $b['hits']) {
            return 0;
        }

        return ($a['hits'] > $b['hits']) ? -1 : 1;
    }
}