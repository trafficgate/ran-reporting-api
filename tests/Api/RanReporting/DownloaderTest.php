<?php

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Linkshare\Api\RanReporting\Downloader;
use Linkshare\Api\RanReporting\Report;
use Linkshare\Api\RanReporting\SignatureOrders;

class DownloaderTest extends TestCase
{
    const SINK_DIR = __DIR__.'/../../tmp';

    protected $report;
    protected $history;
    protected $mockResponseBody;
    protected $mockHandler;

    public function setUp()
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

    public function testDownload()
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

    public function testDownloadWithSinkDir()
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

    public function testDownloadWithSinkPrefix()
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

    public function testSinkFileIsDeleted()
    {
        $downloader = new Downloader($this->report, self::SINK_DIR);
        $sinkPath   = $downloader->getSinkPath();

        $this->assertFileExists($sinkPath);

        unset($downloader);

        $this->assertFileNotExists($sinkPath);
    }

    public function testDownloadRetry()
    {
        $history      = [];
        $responseBody = $this->createSampleReportStream();
        $handler      = HandlerStack::create(new MockHandler([
            new Response(403),
            new Response(500),
            new Response(200, [], $responseBody),
        ]));
        $handler->push(Middleware::history($history));

        $downloader = new Downloader($this->report);
        $downloader->download([
            'handler'                => $handler,
            'retry_attempts'         => 10,
            'retry_delay_multiplier' => 5000,
        ]);

        $this->assertCount(3, $history);
        $this->assertEquals($this->report->getUri(), $history[0]['request']->getUri());
        $this->assertEquals($responseBody, $downloader->getResponse()->getBody());
    }

    /**
     * @expectedException GuzzleHttp\Exception\RequestException
     */
    public function testDownloadRetryFail()
    {
        $history      = [];
        $responseBody = $this->createSampleReportStream();
        $handler      = HandlerStack::create(new MockHandler([
            new Response(403),
            new Response(500),
            new Response(500),
            new Response(500),
            new Response(200, [], $responseBody),
        ]));
        $handler->push(Middleware::history($history));

        $downloader = new Downloader($this->report);
        $downloader->download([
            'handler'        => $handler,
            'retry_attempts' => 3,
        ]);
    }
}
