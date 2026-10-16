<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use League\Csv\UnavailableStream;
use Linkshare\Api\RanReporting\Parser;
use Linkshare\Api\RanReporting\RecordFactory;
use Linkshare\Api\RanReporting\SignatureOrders\TransactionFactory;
use Linkshare\Api\RanReporting\Tests\TestCase;

class ParserTest extends TestCase
{
    public function testParseToArray(): void
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

    public function testParseToTransaction(): void
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

    public function testGetHeaderAsArray(): void
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

    public function testGetHeaderAsTransaction(): void
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

    public function testMultiByteUtf8WithABom(): void
    {
        $csv = "\xEF\xBB\xBF名前,広告主\r\n\"楽天市場\",1\r\n\"café ☕ 😀\",2\r\n";

        $parser = Parser::createFromString($csv, $this->pairFactory());

        $this->assertSame([['名前', '広告主'], '名前,広告主'], $parser->getHeader());
        $this->assertSame([
            [['楽天市場', '1'], '"楽天市場",1'],
            [['café ☕ 😀', '2'], '"café ☕ 😀",2'],
        ], iterator_to_array($parser->parse(), false));
    }

    public function testMultiByteTextInsideMultilineQuotedFields(): void
    {
        $records = $this->parseWithLines("h1,h2\n\"日本\n東京 😀\",1\n\n\"é\",2\n");

        $this->assertSame([
            [["日本\n東京 😀", '1'], "\"日本\n東京 😀\",1"],
            [['é', '2'], '"é",2'],
        ], $records);
    }

    public function testLinesMadeOfMultiByteCharactersThatContainQuoteOrNewlineBytes(): void
    {
        // U+0122 is C4 A2 and U+22xx / U+0A xx style code points never share a
        // byte with an ASCII quote or newline in UTF-8, which is what makes
        // counting quote bytes safe.
        $csv = "h1,h2\nĢ∀≈,1\n\"∀\n≈\",2\n";

        $this->assertSame([
            [['Ģ∀≈', '1'], 'Ģ∀≈,1'],
            [["∀\n≈", '2'], "\"∀\n≈\",2"],
        ], $this->parseWithLines($csv));
    }

    public function testShiftJisBytesPassThroughUnchanged(): void
    {
        // Shift_JIS 表 is 95 5C: its second byte is an ASCII backslash. With
        // the default escape character that backslash would swallow the closing
        // quote.
        $csv = "\x96\xBC\x91\x4F,\x92\x6C\r\n\"\x95\x5C\",1\r\n\"\x95\x5C\x95\x5C\",2\r\n";

        $this->assertSame([
            [["\x95\x5C", '1'], "\"\x95\x5C\",1"],
            [["\x95\x5C\x95\x5C", '2'], "\"\x95\x5C\x95\x5C\",2"],
        ], $this->parseWithLines($csv));
    }

    public function testMultiByteValuesReachTheTransaction(): void
    {
        $line = '"ﾒﾝﾊﾞｰ",1,"楽天市場","ｵｰﾀﾞｰ",4/5/20,"10:11:12","ＳＫＵ",1000,1,50,4/6/20,"01:02:03"';
        $csv  = "\xEF\xBB\xBFh\r\n" . $line . "\r\n";

        $records = iterator_to_array(
            Parser::createFromString($csv, new TransactionFactory())->parse(),
            false
        );

        $this->assertSame('ﾒﾝﾊﾞｰ', $records[0]->memberId());
        $this->assertSame('楽天市場', $records[0]->advertiserName());
        $this->assertSame('ＳＫＵ', $records[0]->sku());
        $this->assertSame($line, $records[0]->originalString());
    }

    public function testCreateParserFromResponse(): void
    {
        $response = new Response(200, [], $this->createSampleReportStream());
        $parser   = Parser::createFromResponse(
            $response,
            new TransactionFactory()
        );
        $records         = $parser->parse();
        $record          = $records->current();
        $numberOfRecords = iterator_count($records);

        $this->assertEquals('4983949625568', $record->memberId());
        $this->assertEquals(1532, $numberOfRecords);
    }

    public function testBlankLinesDoNotShiftRawLines(): void
    {
        $records = $this->parseWithLines("h1,h2\na,1\n\nb,2\n\n\nc,3\n");

        $this->assertSame([
            [['a', '1'], 'a,1'],
            [['b', '2'], 'b,2'],
            [['c', '3'], 'c,3'],
        ], $records);
    }

    public function testQuotedFieldsContainingNewlinesKeepTheirWholeRawRecord(): void
    {
        $records = $this->parseWithLines("h1,h2\n\"x\ny\",1\r\n\"a\r\n\r\nb\",2\r\nc,3\r\n");

        $this->assertSame([
            [["x\ny", '1'], "\"x\ny\",1"],
            [["a\r\n\r\nb", '2'], "\"a\r\n\r\nb\",2"],
            [['c', '3'], 'c,3'],
        ], $records);
    }

    public function testBlankLinesBeforeTheHeaderDoNotLeakTheHeaderIntoTheRecords(): void
    {
        $records = $this->parseWithLines("\n\nh1,h2\na,1\n");

        $this->assertSame([[['a', '1'], 'a,1']], $records);
    }

    public function testNoTrailingNewlineAndTrailingBlankLines(): void
    {
        $this->assertSame(
            [[['a', '1'], 'a,1'], [['b', '2'], 'b,2']],
            $this->parseWithLines("h1,h2\na,1\nb,2")
        );
        $this->assertSame(
            [[['a', '1'], 'a,1']],
            $this->parseWithLines("h1,h2\na,1\n\n\n")
        );
    }

    public function testBothLineEndingStylesAreStrippedFromRawLines(): void
    {
        $this->assertSame(
            [[['a', '1'], 'a,1'], [['b', '2'], 'b,2']],
            $this->parseWithLines("h1,h2\r\na,1\r\nb,2\r\n")
        );
        $this->assertSame(
            [[['a', '1'], 'a,1'], [['b', '2'], 'b,2']],
            $this->parseWithLines("h1,h2\na,1\nb,2\n")
        );
    }

    public function testWhitespaceOnlyLineIsARecordNotABlankLine(): void
    {
        $this->assertSame(
            [[['a', '1'], 'a,1'], [[' '], ' '], [['b', '2'], 'b,2']],
            $this->parseWithLines("h1,h2\na,1\n \nb,2\n")
        );
    }

    public function testDoubledQuotesAndBackslashesFollowRfc4180(): void
    {
        $records = $this->parseWithLines("h1,h2\n\"say \"\"hi\"\"\",1\n\"C:\\dir\\\",2\nc,3\n");

        $this->assertSame([
            [['say "hi"', '1'], '"say ""hi""",1'],
            [['C:\dir\\', '2'], '"C:\dir\",2'],
            [['c', '3'], 'c,3'],
        ], $records);
    }

    public function testFileWithoutBomIsParsed(): void
    {
        $parser = Parser::createFromString("h1,h2\na,1\n", $this->pairFactory());

        $this->assertSame([['h1', 'h2'], 'h1,h2'], $parser->getHeader());
    }

    public function testBomIsStrippedFromTheRawHeaderLineOnly(): void
    {
        $parser = Parser::createFromString("\xEF\xBB\xBFh1,h2\r\na,1\r\n", $this->pairFactory());

        $this->assertSame([['h1', 'h2'], 'h1,h2'], $parser->getHeader());
        $this->assertSame([[['a', '1'], 'a,1']], iterator_to_array($parser->parse(), false));
    }

    public function testHeaderMayContainQuotedNewlines(): void
    {
        $parser = Parser::createFromString("\"h\n1\",h2\na,1\n", $this->pairFactory());

        $this->assertSame([["h\n1", 'h2'], "\"h\n1\",h2"], $parser->getHeader());
        $this->assertSame([[['a', '1'], 'a,1']], iterator_to_array($parser->parse(), false));
    }

    public function testHeaderOnlyAndEmptySources(): void
    {
        $this->assertSame([], $this->parseWithLines("h1,h2\n"));
        $this->assertSame([], $this->parseWithLines(''));
        $this->assertSame([], Parser::createFromString('')->getHeader());
        $this->assertSame([[], ''], Parser::createFromString('', $this->pairFactory())->getHeader());
    }

    public function testCreateFromStringAcceptsAPlainString(): void
    {
        $records = iterator_to_array(
            Parser::createFromString("h1,h2\na,1\nb,2\n")->parse(),
            false
        );

        $this->assertSame([['a', '1'], ['b', '2']], $records);
    }

    public function testCreateFromPathHandlesBlankLinesAndMultilineFields(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'parser-');
        file_put_contents($path, "h1,h2\n\na,1\n\"x\ny\",2\n\nc,3\n");

        try {
            $records = iterator_to_array(
                Parser::createFromPath($path, $this->pairFactory())->parse(),
                false
            );
        } finally {
            unlink($path);
        }

        $this->assertSame([
            [['a', '1'], 'a,1'],
            [["x\ny", '2'], "\"x\ny\",2"],
            [['c', '3'], 'c,3'],
        ], $records);
    }

    public function testMissingFileThrows(): void
    {
        $this->expectException(UnavailableStream::class);

        iterator_to_array(Parser::createFromPath('/no/such/file.csv')->parse());
    }

    public function testRecordsFromAFullDownloadAlignWithTheirRawLines(): void
    {
        $parser = Parser::createFromPath(self::PATH_TO_SAMPLE_REPORT, $this->pairFactory());
        $lines  = preg_split('/\r?\n/', trim(file_get_contents(self::PATH_TO_SAMPLE_REPORT)));

        array_shift($lines);

        foreach ($parser->parse() as $index => [$row, $line]) {
            $this->assertSame($lines[$index], $line);
            $this->assertSame(
                str_getcsv($lines[$index], ',', '"', ''),
                $row
            );
        }

        $this->assertSame(1532, $index + 1);
    }

    public function testCreateParserFromPath(): void
    {
        $parser = Parser::createFromPath(
            self::PATH_TO_SAMPLE_REPORT,
            new TransactionFactory()
        );
        $numberOfRecords = iterator_count($parser->parse());

        $this->assertEquals(1532, $numberOfRecords);
    }

    /**
     * Parses with a factory that returns [row, raw line] so a test can check
     * that every record is paired with its own source text.
     */
    protected function parseWithLines(string $csv): array
    {
        return iterator_to_array(
            Parser::createFromString($csv, $this->pairFactory())->parse(),
            false
        );
    }

    protected function pairFactory(): RecordFactory
    {
        return new class implements RecordFactory {
            public function create(array $row, string $line): array
            {
                return [$row, $line];
            }
        };
    }
}
