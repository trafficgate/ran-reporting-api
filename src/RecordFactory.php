<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting;

interface RecordFactory
{
    public function create(array $row, string $line): mixed;
}
