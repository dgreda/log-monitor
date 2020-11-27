<?php


namespace App\Service;

use App\Entity\Log\Row;
use App\Entity\Log\RowCollection;
use League\Csv\Reader;
use Traversable;

class LogIterator
{
    private const CHUNK_SIZE = 1000;

    /**
     * Reads log rows from specified file in chunks
     * and yields the corresponding log rows in a RowCollection.
     *
     * @param string $logFilePath
     * @return RowCollection|Traversable
     * @throws \League\Csv\Exception
     */
    public function yieldCollectionFromLogFile(string $logFilePath): Traversable
    {
        $csv = Reader::createFromStream(fopen($logFilePath, 'r'));
        $csv->setHeaderOffset(0);

        $collection = new RowCollection();

        $i = 0;
        foreach ($csv as $logRow) {
            $row = $this->mapRawRowToEntity($logRow);

            // if the current log row's time difference from the first log row in collection
            // is higher than specified amount of seconds
            if ($i % static::CHUNK_SIZE == 0) {
                yield $collection; // yield the current collection of log rows

                // and create a new collection to add the currently iterated log row
                $collection = new RowCollection();
            }

            $collection->addRow($row);
            $i++;
        }
    }

    private function mapRawRowToEntity(array $row): Row
    {
        return new Row(
            $row['remotehost'],
            $row['rfc931'],
            $row['authuser'],
            $row['date'],
            $row['request'],
            $row['status'],
            $row['bytes']
        );
    }
}
