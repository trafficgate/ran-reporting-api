<?php

namespace Linkshare\Api\RanReporting\SignatureOrders;

class Transaction
{
    const MEMBER_ID        = 0;
    const MID              = 1;
    const ADVERTISER_NAME  = 2;
    const ORDER_ID         = 3;
    const TRANSACTION_DATE = 4;
    const TRANSACTION_TIME = 5;
    const SKU              = 6;
    const SALES            = 7;
    const NUMBER_OF_ITEMS  = 8;
    const TOTAL_COMMISSION = 9;
    const PROCESS_DATE     = 10;
    const PROCESS_TIME     = 11;

    /**
     * @var string
     */
    protected $memberId;

    /**
     * @var string
     */
    protected $mid;

    /**
     * @var string
     */
    protected $advertiserName;

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var string
     */
    protected $transactionDate;

    /**
     * @var string
     */
    protected $transactionTime;

    /**
     * @var string
     */
    protected $sku;

    /**
     * @var string
     */
    protected $sales;

    /**
     * @var string
     */
    protected $numberOfItems;

    /**
     * @var string
     */
    protected $totalCommission;

    /**
     * @var string
     */
    protected $processDate;

    /**
     * @var string
     */
    protected $processTime;

    /**
     * @var string
     */
    protected $originalString;

    public function __construct(array $data, string $originalString)
    {
        $this->memberId        = $data[self::MEMBER_ID];
        $this->mid             = $data[self::MID];
        $this->advertiserName  = $data[self::ADVERTISER_NAME];
        $this->orderId         = $data[self::ORDER_ID];
        $this->transactionDate = $data[self::TRANSACTION_DATE];
        $this->transactionTime = $data[self::TRANSACTION_TIME];
        $this->sku             = $data[self::SKU];
        $this->sales           = $data[self::SALES];
        $this->numberOfItems   = $data[self::NUMBER_OF_ITEMS];
        $this->totalCommission = $data[self::TOTAL_COMMISSION];
        $this->processDate     = $data[self::PROCESS_DATE];
        $this->processTime     = $data[self::PROCESS_TIME];
        $this->originalString  = $originalString;
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
}
