<?php

namespace Linkshare\Api\RanReporting;

use Linkshare\Api\RanReporting\SignatureOrders\TransactionFactory;

class ParserTest extends TestCase
{
    public function testParseToArray()
    {
        $responseBody    = $this->createSampleReportStream();
        $parser          = Parser::createFromString($responseBody);
        $records         = $parser->parse();
        $record          = $records->current();
        $numberOfRecords = iterator_count($records);

        $this->assertEquals('4983949625568', $record[0]);
        $this->assertEquals('7241', $record[1]);
        $this->assertEquals('Wunsch-Tremblay', $record[2]);
        $this->assertEquals('871718356', $record[3]);
        $this->assertEquals('10/17/80', $record[4]);
        $this->assertEquals('06:06:26', $record[5]);
        $this->assertEquals('55238', $record[6]);
        $this->assertEquals('84.98', $record[7]);
        $this->assertEquals('0', $record[8]);
        $this->assertEquals('1.225', $record[9]);
        $this->assertEquals('4/27/70', $record[10]);
        $this->assertEquals('03:54:22', $record[11]);
        $this->assertEquals(1532, $numberOfRecords);
    }

    public function testParseToTransaction()
    {
        $responseBody    = $this->createSampleReportStream();
        $parser          = Parser::createFromString($responseBody, new TransactionFactory());
        $records         = $parser->parse();
        $record          = $records->current();
        $numberOfRecords = iterator_count($records);

        $this->assertEquals('4983949625568', $record->memberId());
        $this->assertEquals('7241', $record->mid());
        $this->assertEquals('Wunsch-Tremblay', $record->advertiserName());
        $this->assertEquals('871718356', $record->orderId());
        $this->assertEquals('10/17/80', $record->transactionDate());
        $this->assertEquals('06:06:26', $record->transactionTime());
        $this->assertEquals('55238', $record->sku());
        $this->assertEquals('84.98', $record->sales());
        $this->assertEquals('0', $record->numberOfItems());
        $this->assertEquals('1.225', $record->totalCommission());
        $this->assertEquals('4/27/70', $record->processDate());
        $this->assertEquals('03:54:22', $record->processTime());
        $this->assertEquals(1532, $numberOfRecords);

        $this->assertEquals(
            '"4983949625568",7241,"Wunsch-Tremblay","871718356",10/17/80,"06:06:26","55238",84.98,0,1.225,4/27/70,"03:54:22"',
            $record->originalString()
        );
    }

    public function testGetHeaderAsArray()
    {
        $responseBody = $this->createSampleReportStream();
        $parser       = Parser::createFromString($responseBody);
        $header       = $parser->getHeader();

        $this->assertEquals('Member ID (U1)', $header[0]);
        $this->assertEquals('MID', $header[1]);
        $this->assertEquals('Advertiser Name', $header[2]);
        $this->assertEquals('Order ID', $header[3]);
        $this->assertEquals('Transaction Date', $header[4]);
        $this->assertEquals('Transaction Time', $header[5]);
        $this->assertEquals('SKU', $header[6]);
        $this->assertEquals('Sales', $header[7]);
        $this->assertEquals('# of Items', $header[8]);
        $this->assertEquals('Total Commission', $header[9]);
        $this->assertEquals('Process Date', $header[10]);
        $this->assertEquals('Process Time', $header[11]);
    }

    public function testGetHeaderAsTransaction()
    {
        $responseBody = $this->createSampleReportStream();
        $parser       = Parser::createFromString($responseBody, new TransactionFactory());
        $header       = $parser->getHeader();

        $this->assertEquals('Member ID (U1)', $header->memberId());
        $this->assertEquals('MID', $header->mid());
        $this->assertEquals('Advertiser Name', $header->advertiserName());
        $this->assertEquals('Order ID', $header->orderId());
        $this->assertEquals('Transaction Date', $header->transactionDate());
        $this->assertEquals('Transaction Time', $header->transactionTime());
        $this->assertEquals('SKU', $header->sku());
        $this->assertEquals('Sales', $header->sales());
        $this->assertEquals('# of Items', $header->numberOfItems());
        $this->assertEquals('Total Commission', $header->totalCommission());
        $this->assertEquals('Process Date', $header->processDate());
        $this->assertEquals('Process Time', $header->processTime());

        $this->assertEquals(
            'Member ID (U1),MID,Advertiser Name,Order ID,Transaction Date,Transaction Time,SKU,Sales,# of Items,Total Commission,Process Date,Process Time',
            $header->originalString()
        );
    }

    public function testCreateParserFromPath()
    {
        $parser = Parser::createFromPath(
            self::PATH_TO_SAMPLE_REPORT,
            new TransactionFactory()
        );
        $numberOfRecords = iterator_count($parser->parse());

        $this->assertEquals(1532, $numberOfRecords);
    }
}
