<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting;

class ArrayFactory implements RecordFactory
{
    public function create(array $row, string $line): array
    {
        return $row;
    }
}
