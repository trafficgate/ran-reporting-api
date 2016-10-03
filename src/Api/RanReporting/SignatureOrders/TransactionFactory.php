<?php

namespace Linkshare\Api\RanReporting\SignatureOrders;

use Linkshare\Api\RanReporting\RecordFactory;

class TransactionFactory implements RecordFactory
{
    public function create(array $row, string $line)
    {
        return new Transaction($row, $line);
    }
}
