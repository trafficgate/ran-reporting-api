<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\Tests\Unit;

use Generator;
use GuzzleHttp\Psr7\Response;
use League\Csv\UnavailableStream;
use Linkshare\Api\RanReporting\Parser;
use Linkshare\Api\RanReporting\RecordFactory;
use Linkshare\Api\RanReporting\SignatureOrders\TransactionFactory;
use Linkshare\Api\RanReporting\Tests\TestCase;
use LogicException;
use RuntimeException;

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

    public function testAQuoteInsideAnUnquotedFieldIsLiteralText(): void
    {
        $records = $this->parseWithLines("h1,h2\nPipes 5\" Inc,1\nc,2\n5\",3\nd,4\n");

        $this->assertSame([
            [['Pipes 5" Inc', '1'], 'Pipes 5" Inc,1'],
            [['c', '2'], 'c,2'],
            [['5"', '3'], '5",3'],
            [['d', '4'], 'd,4'],
        ], $records);
    }

    public function testTextAfterAClosingQuoteIsPartOfTheField(): void
    {
        $records = $this->parseWithLines("h1,h2\n\"ab\"c,1\n\"x\ny\"z,2\nd,3\n");

        $this->assertSame([
            [['abc', '1'], '"ab"c,1'],
            [["x\nyz", '2'], "\"x\ny\"z,2"],
            [['d', '3'], 'd,3'],
        ], $records);
    }

    public function testWhitespaceBeforeAnOpeningQuoteStillOpensTheField(): void
    {
        $records = $this->parseWithLines("h1,h2,h3\na, \"b\nc\",2\nd,\t\"e,f\",3\ng,h,4\n");

        $this->assertSame([
            [['a', "b\nc", '2'], "a, \"b\nc\",2"],
            [['d', 'e,f', '3'], "d,\t\"e,f\",3"],
            [['g', 'h', '4'], 'g,h,4'],
        ], $records);
    }

    public function testQuotedFieldsMixedWithStrayQuotesOnTheSameLine(): void
    {
        $records = $this->parseWithLines("h1,h2,h3\n\"a\nb\",5\" pipe,\"c\"\nd,e,f\n");

        $this->assertSame([
            [["a\nb", '5" pipe', 'c'], "\"a\nb\",5\" pipe,\"c\""],
            [['d', 'e', 'f'], 'd,e,f'],
        ], $records);
    }

    public function testASourceThatEndsInsideAQuotedFieldKeepsTheTextItHas(): void
    {
        $records = $this->parseWithLines("h1,h2\na,1\n\"b,2\n");

        $this->assertSame([
            [['a', '1'], 'a,1'],
            [["b,2\n"], '"b,2'],
        ], $records);
    }

    public function testAnUnknownSourceTypeIsRejected(): void
    {
        $parser = new class ('x', 'bogus') extends Parser {
            public function __construct(string $source, string $sourceType, ?RecordFactory $recordFactory = null)
            {
                parent::__construct($source, $sourceType, $recordFactory);
            }
        };

        $this->expectException(LogicException::class);

        iterator_to_array($parser->parse());
    }

    public function testRecordsThatCannotBePairedWithTheirSourceTextAreReported(): void
    {
        $parser = new class ('h1,h2' . "\n" . 'a,1' . "\n", Parser::SOURCE_STRING) extends Parser {
            public function __construct(string $source, string $sourceType, ?RecordFactory $recordFactory = null)
            {
                parent::__construct($source, $sourceType, $recordFactory);
            }

            protected function rawRecords(string $bom): Generator
            {
                yield from [];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to match a parsed record');

        iterator_to_array($parser->parse());
    }

    /**
     * Random, mostly malformed CSV: quotes in odd places, stray backslashes,
     * blank lines, mixed line endings, multi-byte text, sources that end inside
     * a quote. However strange the input, the source-text side must find
     * exactly the records League\Csv finds, and every record's text must parse
     * back to the row it was paired with.
     */
    public function testSourceTextStaysAlignedWithParsedRowsOnRandomInput(): void
    {
        mt_srand(20261016);

        $pieces = ['a', 'b', '1', ',', ',', '"', '"', ' ', "\t", "\n", "\n", "\r\n", 'é', '日', '\\'];
        $probe  = new class ('', Parser::SOURCE_STRING) extends Parser {
            public function __construct(string $source, string $sourceType, ?RecordFactory $recordFactory = null)
            {
                parent::__construct($source, $sourceType, $recordFactory);
            }

            public function withSource(string $source): self
            {
                return new self($source, self::SOURCE_STRING);
            }

            /**
             * @return array{int, int} Records League\Csv finds, records found in the source text
             */
            public function counts(): array
            {
                $reader = $this->createCsvReader();

                return [
                    iterator_count($reader->getRecords()),
                    iterator_count($this->rawRecords($reader->getInputBOM())),
                ];
            }
        };

        for ($i = 0; $i < 1500; ++$i) {
            $csv = "h1,h2\n";

            for ($j = 0, $length = mt_rand(1, 45); $j < $length; ++$j) {
                $csv .= $pieces[mt_rand(0, count($pieces) - 1)];
            }

            $this->assertSame(
                $probe->withSource($csv)->counts()[0],
                $probe->withSource($csv)->counts()[1],
                'Different record counts for ' . json_encode($csv)
            );

            $records = $this->parseWithLines($csv);
            $last    = count($records) - 1;

            foreach ($records as $index => [$row, $text]) {
                // The last record may end inside an open quote, which loses its trailing newline.
                if ($index !== $last) {
                    $this->assertSame($row, str_getcsv($text, ',', '"', ''), 'Misaligned record in ' . json_encode($csv));
                }
            }
        }
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
        $parser   = Parser::createFromPath(self::PATH_TO_SAMPLE_REPORT, $this->pairFactory());
        $contents = file_get_contents(self::PATH_TO_SAMPLE_REPORT);

        $this->assertIsString($contents);

        $lines = preg_split('/\r?\n/', trim($contents));

        $this->assertIsArray($lines);

        array_shift($lines);

        $count = 0;

        foreach ($parser->parse() as $index => [$row, $line]) {
            $this->assertSame($lines[$index], $line);
            $this->assertSame(
                str_getcsv($lines[$index], ',', '"', ''),
                $row
            );
            ++$count;
        }

        $this->assertSame(1532, $count);
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
    /**
     * @return list<array{array<array-key, mixed>, string}>
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
            /**
             * @param array<array-key, mixed> $row
             *
             * @return array{array<array-key, mixed>, string}
             */
            public function create(array $row, string $line): array
            {
                return [$row, $line];
            }
        };
    }
}
