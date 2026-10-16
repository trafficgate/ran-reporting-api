<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\Tests\Unit;

use Error;
use InvalidArgumentException;
use Linkshare\Api\RanReporting\SignatureOrders\Transaction;
use Linkshare\Api\RanReporting\SignatureOrders\TransactionFactory;
use Linkshare\Api\RanReporting\Tests\TestCase;
use ReflectionProperty;

class TransactionTest extends TestCase
{
    public function testExposesEveryColumn(): void
    {
        $transaction = new Transaction($this->columns(), 'raw line');

        $this->assertSame('1', $transaction->memberId());
        $this->assertSame('2', $transaction->mid());
        $this->assertSame('Advertiser', $transaction->advertiserName());
        $this->assertSame('4', $transaction->orderId());
        $this->assertSame('5/6/70', $transaction->transactionDate());
        $this->assertSame('07:08:09', $transaction->transactionTime());
        $this->assertSame('7', $transaction->sku());
        $this->assertSame('8.90', $transaction->sales());
        $this->assertSame('1', $transaction->numberOfItems());
        $this->assertSame('0.5', $transaction->totalCommission());
        $this->assertSame('5/7/70', $transaction->processDate());
        $this->assertSame('10:11:12', $transaction->processTime());
        $this->assertSame('raw line', $transaction->originalString());
    }

    public function testExtraColumnsAreIgnored(): void
    {
        $transaction = new Transaction([...$this->columns(), 'extra'], 'raw');

        $this->assertSame('10:11:12', $transaction->processTime());
    }

    public function testTooFewColumnsThrowsAClearException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected at least 12 columns, got 3.');

        new Transaction(['a', 'b', 'c'], 'a,b,c');
    }

    public function testTheExceptionDoesNotQuoteTheRecord(): void
    {
        try {
            new Transaction(['member-1234', 'b'], 'member-1234,b');
            $this->fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringNotContainsString('member-1234', $e->getMessage());
        }
    }

    public function testFactoryRejectsAShortRow(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TransactionFactory())->create(['only', 'two'], 'only,two');
    }

    public function testPropertiesCannotBeModified(): void
    {
        $transaction = new Transaction($this->columns(), 'raw');

        $this->expectException(Error::class);

        (new ReflectionProperty(Transaction::class, 'memberId'))->setValue($transaction, 'changed');
    }

    /**
     * @return list<string>
     */
    protected function columns(): array
    {
        return ['1', '2', 'Advertiser', '4', '5/6/70', '07:08:09', '7', '8.90', '1', '0.5', '5/7/70', '10:11:12'];
    }
}
