<?php

namespace Linkshare\Api\RanReporting;

interface RecordFactory
{
    public function create(array $row, string $line);
}
