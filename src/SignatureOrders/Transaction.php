<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\SignatureOrders;

use InvalidArgumentException;

class Transaction
{
    public const MEMBER_ID        = 0;
    public const MID              = 1;
    public const ADVERTISER_NAME  = 2;
    public const ORDER_ID         = 3;
    public const TRANSACTION_DATE = 4;
    public const TRANSACTION_TIME = 5;
    public const SKU              = 6;
    public const SALES            = 7;
    public const NUMBER_OF_ITEMS  = 8;
    public const TOTAL_COMMISSION = 9;
    public const PROCESS_DATE     = 10;
    public const PROCESS_TIME     = 11;

    public const COLUMN_COUNT = 12;

    protected readonly string $memberId;
    protected readonly string $mid;
    protected readonly string $advertiserName;
    protected readonly string $orderId;
    protected readonly string $transactionDate;
    protected readonly string $transactionTime;
    protected readonly string $sku;
    protected readonly string $sales;
    protected readonly string $numberOfItems;
    protected readonly string $totalCommission;
    protected readonly string $processDate;
    protected readonly string $processTime;

    /**
     * @param array<array-key, mixed> $data The CSV columns, in the report's order
     *
     * @throws InvalidArgumentException If there are too few columns or a column is not a string
     */
    public function __construct(array $data, protected readonly string $originalString)
    {
        if (count($data) < self::COLUMN_COUNT) {
            // Deliberately leaves the record out: it may contain member data.
            throw new InvalidArgumentException(sprintf(
                'Expected at least %d columns, got %d.',
                self::COLUMN_COUNT,
                count($data)
            ));
        }

        $this->memberId        = self::column($data, self::MEMBER_ID);
        $this->mid             = self::column($data, self::MID);
        $this->advertiserName  = self::column($data, self::ADVERTISER_NAME);
        $this->orderId         = self::column($data, self::ORDER_ID);
        $this->transactionDate = self::column($data, self::TRANSACTION_DATE);
        $this->transactionTime = self::column($data, self::TRANSACTION_TIME);
        $this->sku             = self::column($data, self::SKU);
        $this->sales           = self::column($data, self::SALES);
        $this->numberOfItems   = self::column($data, self::NUMBER_OF_ITEMS);
        $this->totalCommission = self::column($data, self::TOTAL_COMMISSION);
        $this->processDate     = self::column($data, self::PROCESS_DATE);
        $this->processTime     = self::column($data, self::PROCESS_TIME);
    }

    public function memberId(): string
    {
        return $this->memberId;
    }

    public function mid(): string
    {
        return $this->mid;
    }

    public function advertiserName(): string
    {
        return $this->advertiserName;
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function transactionDate(): string
    {
        return $this->transactionDate;
    }

    public function transactionTime(): string
    {
        return $this->transactionTime;
    }

    public function sku(): string
    {
        return $this->sku;
    }

    public function sales(): string
    {
        return $this->sales;
    }

    public function numberOfItems(): string
    {
        return $this->numberOfItems;
    }

    public function totalCommission(): string
    {
        return $this->totalCommission;
    }

    public function processDate(): string
    {
        return $this->processDate;
    }

    public function processTime(): string
    {
        return $this->processTime;
    }

    public function originalString(): string
    {
        return $this->originalString;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function column(array $data, int $index): string
    {
        $value = $data[$index] ?? null;

        if (! is_string($value)) {
            // Names the column, never its value: it may be member data.
            throw new InvalidArgumentException(sprintf('Column %d is missing or not a string.', $index));
        }

        return $value;
    }
}
