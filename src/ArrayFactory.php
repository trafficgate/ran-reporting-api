<?php

namespace Linkshare\Api\RanReporting;

class ArrayFactory implements RecordFactory
{
    public function create(array $row, string $line)
    {
        return $row;
    }
}
