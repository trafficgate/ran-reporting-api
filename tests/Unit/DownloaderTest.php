<?php

namespace Linkshare\Api\RanReporting\Tests\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Linkshare\Api\RanReporting\Downloader;
use Linkshare\Api\RanReporting\Report;
use Linkshare\Api\RanReporting\SignatureOrders;
use Linkshare\Api\RanReporting\Tests\TestCase;

class DownloaderTest extends TestCase
{
    public const SINK_DIR = __DIR__ . '/../../tmp';

    protected $report;
    protected $history;
    protected $mockResponseBody;
    protected $mockHandler;

    public function setUp(): void
    {
        if (! file_exists(self::SINK_DIR)) {
            mkdir(self::SINK_DIR, 0777, true);
        }

        $this->report = new SignatureOrders([
            'language'  => Report::LANGUAGE_EN,
            'startDate' => '2016-05-01',
            'endDate'   => '2016-07-31',
            'network'   => Report::NETWORK_US,
            'timezone'  => Report::TIMEZONE_GMT,
            'dateType'  => Report::DATE_TYPE_PROCESS,
            'token'     => 'TOKEN',
        ]);
        $this->history          = [];
        $this->mockResponseBody = $this->createSampleReportStream();
        $this->mockHandler      = HandlerStack::create(new MockHandler([
            new Response(200, [], $this->mockResponseBody),
        ]));
        $this->mockHandler->push(Middleware::history($this->history));
    }

    public function testDownload(): void
    {
        $downloader = new Downloader($this->report);
        $downloader->download([
            'handler' => $this->mockHandler,
        ]);

        $this->assertCount(1, $this->history);
        $this->assertEquals(
            $this->report->getUri(),
            $this->history[0]['request']->getUri()
        );
        $this->assertEquals(
            $this->mockResponseBody,
            $downloader->getResponse()->getBody()
        );
    }

    public function testDownloadWithSinkDir(): void
    {
        $downloader = new Downloader($this->report, self::SINK_DIR);
        $downloader->download([
            'handler' => $this->mockHandler,
        ]);
        $sinkPath = $downloader->getSinkPath();

        $this->assertStringStartsWith('report-', basename($sinkPath));
        $this->assertFileExists($sinkPath);
        $this->assertFileEquals(self::PATH_TO_SAMPLE_REPORT, $sinkPath);
    }

    public function testDownloadWithSinkPrefix(): void
    {
        $downloader = new Downloader(
            $this->report,
            self::SINK_DIR,
            'prefix-'
        );
        $downloader->download([
            'handler' => $this->mockHandler,
        ]);
        $sinkPath = $downloader->getSinkPath();

        $this->assertStringStartsWith('prefix-', basename($sinkPath));
        $this->assertFileExists($sinkPath);
        $this->assertFileEquals(self::PATH_TO_SAMPLE_REPORT, $sinkPath);
    }

    public function testSinkFileIsDeleted(): void
    {
        $downloader = new Downloader($this->report, self::SINK_DIR);
        $sinkPath   = $downloader->getSinkPath();

        $this->assertFileExists($sinkPath);

        unset($downloader);

        $this->assertFileNotExists($sinkPath);
    }
}
