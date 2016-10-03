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

    protected $memberId;
    protected $mid;
    protected $advertiserName;
    protected $orderId;
    protected $transactionDate;
    protected $transactionTime;
    protected $sku;
    protected $sales;
    protected $numberOfItems;
    protected $totalCommission;
    protected $processDate;
    protected $processTime;
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

    public function memberId()
    {
        return $this->memberId;
    }

    public function mid()
    {
        return $this->mid;
    }

    public function advertiserName()
    {
        return $this->advertiserName;
    }

    public function orderId()
    {
        return $this->orderId;
    }

    public function transactionDate()
    {
        return $this->transactionDate;
    }

    public function transactionTime()
    {
        return $this->transactionTime;
    }

    public function sku()
    {
        return $this->sku;
    }

    public function sales()
    {
        return $this->sales;
    }

    public function numberOfItems()
    {
        return $this->numberOfItems;
    }

    public function totalCommission()
    {
        return $this->totalCommission;
    }

    public function processDate()
    {
        return $this->processDate;
    }

    public function processTime()
    {
        return $this->processTime;
    }

    public function originalString()
    {
        return $this->originalString;
    }
}
