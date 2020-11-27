<?php

namespace App\Entity;

use App\Entity\Log\Row;
use SplDoublyLinkedList;

final class Queue
{
    private SplDoublyLinkedList $queue;

    public function __construct()
    {
        $this->purge();
    }

    public function getQueue(): SplDoublyLinkedList
    {
        return $this->queue;
    }

    /**
     * Pushes the log into the queue in a time sorted manner.
     *
     * @param Row $logRow
     */
    public function pushLog(Row $logRow): void
    {
        if (
            $this->queue->count() == 0
            || $this->queue->top()->getTimestamp() <= $logRow->getTimestamp()
        ) {
            // if the queue is empty or the latest log row's timestamp is lesser or equal to current log timestamp
            // just push the current log at the end of the queue
            $this->queue->push($logRow);
        } else {
            // find the right place for the log in the double linked list
            $i = 0;
            foreach ($this->queue as $logFromQueue) {
                /** @var Row $logFromQueue */
                if ($logFromQueue->getTimestamp() === $logRow->getTimestamp()) {
                    $this->queue->add($i, $logRow);
                    break;
                }

                if ($logFromQueue->getTimestamp() < $logRow->getTimestamp()) {
                    $i++;
                    continue;
                }
            }
        }
    }

    /**
     * Flushes older entries from the queue.
     *
     * @param int $timeWindow
     */
    public function flush(int $timeWindow): void
    {
        /** @var Row $latestLog */
        $latestLog = $this->queue->top();
        $dequeueOldLogs = true;
        $this->queue->rewind();
        while ($this->queue->valid() && $dequeueOldLogs) {
            /** @var Row $logRow */
            $logRow = $this->queue->shift();
            if ($latestLog->getTimestamp() - $logRow->getTimestamp() < $timeWindow) {
                $this->queue->unshift($logRow);
                $dequeueOldLogs = false;
            }
        }
    }

    /**
     * Purges the whole queue.
     */
    public function purge(): void
    {
        $this->queue = new SplDoublyLinkedList();
        $this->queue->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO);
        $this->queue->setIteratorMode(SplDoublyLinkedList::IT_MODE_KEEP);
    }
}