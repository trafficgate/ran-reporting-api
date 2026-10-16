<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\SignatureOrders;

use Linkshare\Api\RanReporting\RecordFactory;

class TransactionFactory implements RecordFactory
{
    public function create(array $row, string $line): Transaction
    {
        return new Transaction($row, $line);
    }
}
