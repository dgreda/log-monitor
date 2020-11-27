<?php


namespace App\Service;


use App\Entity\Log\Row;
use App\Entity\Log\Stats;
use App\Entity\Queue;
use SplDoublyLinkedList;

class LogAnalyzer
{
    public const METRIC_HITS = 'hits';
    public const METRIC_404s = '404s';
    public const METRIC_SUCCESSFUL = 'successful';
    public const METRIC_REDIRECTIONS = 'redirections';
    public const METRIC_CLIENT_ERROR = 'client_errors';
    public const METRIC_SERVER_ERROR = 'server_errors';

    private Queue $logsQueue;

    private int $statsTimespan;

    public function __construct(int $statsTimespan)
    {
        $this->logsQueue = new Queue();
        $this->statsTimespan = $statsTimespan;
    }

    public function pushLog(Row $logRow): void
    {
        $this->logsQueue->pushLog($logRow);
    }

    public function canCalculateStats(): bool
    {
        $queue = $this->logsQueue->getQueue();
        if ($queue->top()->getTimestamp() - $queue->bottom()->getTimestamp() >= $this->statsTimespan) {
            return true;
        }

        return false;
    }

    public function calculateStats(): ?Stats
    {
        $queue = $this->logsQueue->getQueue();
        if ($queue->count() === 0) {
            return null;
        }

        $stats = new Stats(
            $queue->bottom()->getTimestamp(),
            $queue->top()->getTimestamp()
        );

        foreach ($queue as $logRow) {
            /** @var Row $logRow */

            $section = $this->extractRequestSection($logRow);
            $this->initializeStatsSection($stats, $section);

            $stats->incrementSectionMetric($section, static::METRIC_HITS);

            $status = $logRow->getStatus();

            if ($status === 404) {
                $stats->incrementSectionMetric($section, static::METRIC_404s);
            }

            if ($status >= 200 && $status < 300) {
                $stats->incrementSectionMetric($section, static::METRIC_SUCCESSFUL);
            } else if ($status >= 300 && $status < 400) {
                $stats->incrementSectionMetric($section, static::METRIC_REDIRECTIONS);
            } else if ($status >= 400 && $status < 500) {
                $stats->incrementSectionMetric($section, static::METRIC_CLIENT_ERROR);
            } else if ($status >= 500 && $status < 600) {
                $stats->incrementSectionMetric($section, static::METRIC_SERVER_ERROR);
            }
        }

        return $stats;
    }

    public function purge(): void
    {
        $this->logsQueue->purge();
    }

    /**
     * Parses and returns the 'section' of the request.
     * If the request is to the following path `/api/user` the section will be `/api`.
     *
     * @param Row $logRow
     * @return string
     */
    private function extractRequestSection(Row $logRow): string
    {
        preg_match('/^(GET|POST|PUT|PATCH|DELETE)\s(\/.*)\s/', $logRow->getRequest(), $matches);
        $path = $matches[2];

        return '/' . explode('/', $path)[1];
    }

    private function initializeStatsSection(Stats $stats, string $section): void
    {
        if (!$stats->hasSectionMetric($section, static::METRIC_HITS)) {
            $stats->addSectionMetric($section, static::METRIC_HITS, 0);
        }

        if (!$stats->hasSectionMetric($section, static::METRIC_404s)) {
            $stats->addSectionMetric($section, static::METRIC_404s, 0);
        }

        if (!$stats->hasSectionMetric($section, static::METRIC_SUCCESSFUL)) {
            $stats->addSectionMetric($section, static::METRIC_SUCCESSFUL, 0);
        }

        if (!$stats->hasSectionMetric($section, static::METRIC_REDIRECTIONS)) {
            $stats->addSectionMetric($section, static::METRIC_REDIRECTIONS, 0);
        }

        if (!$stats->hasSectionMetric($section, static::METRIC_CLIENT_ERROR)) {
            $stats->addSectionMetric($section, static::METRIC_CLIENT_ERROR, 0);
        }

        if (!$stats->hasSectionMetric($section, static::METRIC_SERVER_ERROR)) {
            $stats->addSectionMetric($section, static::METRIC_SERVER_ERROR, 0);
        }
    }
}