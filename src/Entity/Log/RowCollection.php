<?php


namespace App\Entity\Log;


final class RowCollection
{
    /**
     * @var Row[]
     */
    private array $rows = [];

    public function addRow(Row $row): void
    {
        $this->rows[$row->getId()] = $row;
    }

    public function findRow(string $rowId): ?Row
    {
        if (!empty($this->rows[$rowId])) {
            return $this->rows[$rowId];
        }

        return null;
    }

    /**
     * @return Row[]
     */
    public function all(): array
    {
        return $this->rows;
    }
}
