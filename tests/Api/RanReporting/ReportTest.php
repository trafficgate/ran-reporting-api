<?php

use Linkshare\Api\RanReporting\Report;

class ReportTest extends TestCase
{
    public function testConstructor()
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

    public function testSetters()
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

    public function testMixConstructorAndSetters()
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
}
