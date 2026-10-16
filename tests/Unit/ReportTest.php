<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\Tests\Unit;

use BadMethodCallException;
use InvalidArgumentException;
use Linkshare\Api\RanReporting\Report;
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
}
