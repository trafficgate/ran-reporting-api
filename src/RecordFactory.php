<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting;

interface RecordFactory
{
    /**
     * @param array<array-key, mixed> $row  The parsed CSV record
     * @param string                  $line The record's source text, without line ending
     */
    public function create(array $row, string $line): mixed;
}
