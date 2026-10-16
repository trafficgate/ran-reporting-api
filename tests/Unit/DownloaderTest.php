<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\Tests\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Linkshare\Api\RanReporting\Downloader;
use Linkshare\Api\RanReporting\Report;
use Linkshare\Api\RanReporting\SignatureOrders;
use Linkshare\Api\RanReporting\Tests\TestCase;

class DownloaderTest extends TestCase
{
    protected string $sinkDir;
    protected Report $report;
    protected array $history;
    protected mixed $mockResponseBody;
    protected HandlerStack $mockHandler;

    protected function setUp(): void
    {
        $this->sinkDir = sys_get_temp_dir();
        $this->report  = new SignatureOrders([
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
        $downloader = new Downloader($this->report, $this->sinkDir);
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
            $this->sinkDir,
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
        $downloader = new Downloader($this->report, $this->sinkDir);
        $sinkPath   = $downloader->getSinkPath();

        $this->assertFileExists($sinkPath);

        unset($downloader);

        $this->assertFileDoesNotExist($sinkPath);
    }

    public function testDestructorToleratesMissingSinkFile(): void
    {
        $downloader = new Downloader($this->report, $this->sinkDir);
        $sinkPath   = $downloader->getSinkPath();

        unlink($sinkPath);
        unset($downloader);

        $this->assertFileDoesNotExist($sinkPath);
    }

    public function testSinkDirMustBeAnExistingDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Downloader($this->report, $this->sinkDir . '/does-not-exist');
    }

    public function testSinkPathIsNullWithoutSinkDir(): void
    {
        $downloader = new Downloader($this->report);

        $this->assertNull($downloader->getSinkPath());
        $this->assertNull($downloader->getResponse());
    }
}
