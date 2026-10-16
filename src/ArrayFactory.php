<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting;

class ArrayFactory implements RecordFactory
{
    /**
     * @param array<array-key, mixed> $row
     *
     * @return array<array-key, mixed>
     */
    public function create(array $row, string $line): array
    {
        return $row;
    }
}
