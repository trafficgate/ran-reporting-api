<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\SignatureOrders;

use InvalidArgumentException;
use Linkshare\Api\RanReporting\RecordFactory;

class TransactionFactory implements RecordFactory
{
    /**
     * @param array<array-key, mixed> $row
     *
     * @throws InvalidArgumentException If the row has too few columns
     */
    public function create(array $row, string $line): Transaction
    {
        return new Transaction($row, $line);
    }
}
