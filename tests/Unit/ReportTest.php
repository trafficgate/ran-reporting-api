<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\Tests\Unit;

use BadMethodCallException;
use InvalidArgumentException;
use Linkshare\Api\RanReporting\Report;
use Linkshare\Api\RanReporting\SignatureOrders;
use Linkshare\Api\RanReporting\Tests\TestCase;
use TypeError;

class ReportTest extends TestCase
{
    public function testConstructor(): void
    {
        $report = new Report([
            'language'       => Report::LANGUAGE_EN,
            'reportName'     => 'signature-orders-report',
            'startDate'      => '2016-08-01',
            'endDate'        => '2016-08-22',
            'includeSummary' => Report::INCLUDE_SUMMARY_NO,
            'network'        => Report::NETWORK_US,
            'timezone'       => Report::TIMEZONE_GMT,
            'dateType'       => Report::DATE_TYPE_PROCESS,
            'token'          => 'TOKEN',
        ]);

        $this->assertEquals(
            'https://ran-reporting.rakutenmarketing.com/en/reports/signature-orders-report/filters?start_date=2016-08-01&end_date=2016-08-22&include_summary=N&network=1&tz=GMT&date_type=process&token=TOKEN',
            $report->getUri()
        );
    }

    public function testSetters(): void
    {
        $report = new Report();

        $report->setLanguage(Report::LANGUAGE_EN)
            ->setReportName('signature-orders-report')
            ->setStartDate('2016-08-01')
            ->setEndDate('2016-08-22')
            ->setIncludeSummary(Report::INCLUDE_SUMMARY_NO)
            ->setNetwork(Report::NETWORK_US)
            ->setTimezone(Report::TIMEZONE_GMT)
            ->setDateType(Report::DATE_TYPE_PROCESS)
            ->setToken('TOKEN');

        $this->assertEquals(
            'https://ran-reporting.rakutenmarketing.com/en/reports/signature-orders-report/filters?start_date=2016-08-01&end_date=2016-08-22&include_summary=N&network=1&tz=GMT&date_type=process&token=TOKEN',
            $report->getUri()
        );
    }

    public function testMixConstructorAndSetters(): void
    {
        $report = new Report([
            'language'       => Report::LANGUAGE_EN,
            'reportName'     => 'signature-orders-report',
            'includeSummary' => Report::INCLUDE_SUMMARY_NO,
            'network'        => Report::NETWORK_US,
            'timezone'       => Report::TIMEZONE_GMT,
            'dateType'       => Report::DATE_TYPE_PROCESS,
            'token'          => 'TOKEN',
        ]);

        $report->setStartDate('2016-08-01')
            ->setEndDate('2016-08-22');

        $this->assertEquals(
            'https://ran-reporting.rakutenmarketing.com/en/reports/signature-orders-report/filters?start_date=2016-08-01&end_date=2016-08-22&include_summary=N&network=1&tz=GMT&date_type=process&token=TOKEN',
            $report->getUri()
        );
    }

    public function testGetUriThrowsWhenPropertyIsNotSet(): void
    {
        $report = new Report([
            'language'   => Report::LANGUAGE_EN,
            'reportName' => 'signature-orders-report',
        ]);

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('startDate is not set.');

        $report->getUri();
    }

    public function testSetIncludeSummaryRejectsInvalidValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Report())->setIncludeSummary('X');
    }

    public function testSetDateTypeRejectsInvalidValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Report())->setDateType('invalid');
    }

    public function testSettersRejectWrongScalarTypes(): void
    {
        $this->expectException(TypeError::class);

        (new Report())->setNetwork('1');
    }

    public function testGetRedactedUriMasksTheToken(): void
    {
        $report = $this->createCompleteReport('SECRET-TOKEN');

        $this->assertStringContainsString('token=SECRET-TOKEN', $report->getUri());
        $this->assertStringNotContainsString('SECRET-TOKEN', $report->getRedactedUri());
        $this->assertStringEndsWith('&token=REDACTED', $report->getRedactedUri());
    }

    public function testGetRedactedUriThrowsWhenPropertyIsNotSet(): void
    {
        $this->expectException(BadMethodCallException::class);

        (new Report())->getRedactedUri();
    }

    public function testDumpingAReportDoesNotRevealTheToken(): void
    {
        $report = $this->createCompleteReport('SECRET-TOKEN');

        ob_start();
        var_dump($report);
        print_r($report);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('SECRET-TOKEN', $output);
        $this->assertStringContainsString('REDACTED', $output);
        $this->assertSame('SECRET-TOKEN', $report->getToken());
    }

    public function testSignatureOrdersUsesItsOwnReportName(): void
    {
        $report = new SignatureOrders([
            'language'  => Report::LANGUAGE_JA,
            'startDate' => '2016-08-01',
            'endDate'   => '2016-08-22',
            'network'   => Report::NETWORK_JAPAN,
            'timezone'  => Report::TIMEZONE_GMT,
            'dateType'  => Report::DATE_TYPE_TRANSACTION,
            'token'     => 'TOKEN',
        ]);

        $this->assertSame(
            'https://ran-reporting.rakutenmarketing.com/ja/reports/signature-orders-report/filters?start_date=2016-08-01&end_date=2016-08-22&include_summary=N&network=11&tz=GMT&date_type=transaction&token=TOKEN',
            $report->getUri()
        );
    }

    public function testNewReportHasNoSettingsExceptTheSummaryDefault(): void
    {
        $report = new Report();

        $this->assertNull($report->getLanguage());
        $this->assertNull($report->getReportName());
        $this->assertNull($report->getStartDate());
        $this->assertNull($report->getEndDate());
        $this->assertSame(Report::INCLUDE_SUMMARY_NO, $report->getIncludeSummary());
        $this->assertNull($report->getNetwork());
        $this->assertNull($report->getTimezone());
        $this->assertNull($report->getDateType());
        $this->assertNull($report->getToken());
    }

    public function testGettersReturnWhatWasSet(): void
    {
        $report = $this->createCompleteReport('TOKEN')->setIncludeSummary(Report::INCLUDE_SUMMARY_YES);

        $this->assertSame(Report::LANGUAGE_EN, $report->getLanguage());
        $this->assertSame('signature-orders-report', $report->getReportName());
        $this->assertSame('2016-08-01', $report->getStartDate());
        $this->assertSame('2016-08-22', $report->getEndDate());
        $this->assertSame(Report::INCLUDE_SUMMARY_YES, $report->getIncludeSummary());
        $this->assertSame(Report::NETWORK_US, $report->getNetwork());
        $this->assertSame(Report::TIMEZONE_GMT, $report->getTimezone());
        $this->assertSame(Report::DATE_TYPE_PROCESS, $report->getDateType());
        $this->assertSame('TOKEN', $report->getToken());
    }

    public function testUnknownConstructorPropertiesAreIgnored(): void
    {
        $report = new Report(['bogus' => 'value', 'language' => Report::LANGUAGE_EN]);

        $this->assertSame(Report::LANGUAGE_EN, $report->getLanguage());
    }

    public function testQueryValuesAreUrlEncoded(): void
    {
        $uri = $this->createCompleteReport('a b/c+d&e=f')->getUri();

        $this->assertStringContainsString('token=a+b%2Fc%2Bd%26e%3Df', $uri);
    }

    protected function createCompleteReport(string $token): Report
    {
        return new Report([
            'language'   => Report::LANGUAGE_EN,
            'reportName' => 'signature-orders-report',
            'startDate'  => '2016-08-01',
            'endDate'    => '2016-08-22',
            'network'    => Report::NETWORK_US,
            'timezone'   => Report::TIMEZONE_GMT,
            'dateType'   => Report::DATE_TYPE_PROCESS,
            'token'      => $token,
        ]);
    }
}
