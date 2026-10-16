<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\Tests\Unit;

use BadMethodCallException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Linkshare\Api\RanReporting\Downloader;
use Linkshare\Api\RanReporting\Report;
use Linkshare\Api\RanReporting\RetryPolicy;
use Linkshare\Api\RanReporting\SignatureOrders;
use Linkshare\Api\RanReporting\Tests\TestCase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class DownloaderTest extends TestCase
{
    public const TOKEN = 'S3CRET-t0ken/with+chars';

    protected string $workDir;
    protected Report $report;
    protected array $history;
    protected array $sleeps;
    protected string $originalIgnoreArgs;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/ran-reporting-api-' . bin2hex(random_bytes(6));
        mkdir($this->workDir);

        $this->report = new SignatureOrders([
            'language'  => Report::LANGUAGE_EN,
            'startDate' => '2016-05-01',
            'endDate'   => '2016-07-31',
            'network'   => Report::NETWORK_US,
            'timezone'  => Report::TIMEZONE_GMT,
            'dateType'  => Report::DATE_TYPE_PROCESS,
            'token'     => self::TOKEN,
        ]);
        $this->history = [];
        $this->sleeps  = [];

        // Make PHP record call arguments in exception traces, as a development
        // setup would, so the redaction tests exercise the worst case.
        $this->originalIgnoreArgs = (string) ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
    }

    protected function tearDown(): void
    {
        ini_set('zend.exception_ignore_args', $this->originalIgnoreArgs);

        foreach (glob($this->workDir . '/{,.}*', GLOB_BRACE) ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($this->workDir);
    }

    public function testDownload(): void
    {
        $downloader = $this->createDownloader([$this->sampleResponse()]);

        $this->assertNull($downloader->getResponse());

        $response = $downloader->download();

        $this->assertCount(1, $this->history);
        $this->assertEquals(
            $this->report->getUri(),
            (string) $this->history[0]['request']->getUri()
        );
        $this->assertSame($response, $downloader->getResponse());
        $this->assertStringEqualsFile(
            self::PATH_TO_SAMPLE_REPORT,
            (string) $response->getBody()
        );
    }

    public function testDefaultOptionsAndPerCallOptionsReachTheClient(): void
    {
        $downloader = $this->createDownloader(
            [$this->sampleResponse()],
            ['proxy' => 'http://proxy.test:3128', 'verify' => '/etc/ssl/ca.pem', 'timeout' => 30]
        );

        $downloader->download(null, ['verify' => false]);

        $options = $this->history[0]['options'];

        $this->assertSame('http://proxy.test:3128', $options['proxy']);
        $this->assertFalse($options['verify']);
        $this->assertSame(30, $options['timeout']);
    }

    public function testDownloadToDestination(): void
    {
        $destination = $this->workDir . '/report.csv';
        $downloader  = $this->createDownloader([$this->sampleResponse()]);

        $downloader->download($destination);

        $this->assertFileEquals(self::PATH_TO_SAMPLE_REPORT, $destination);
        $this->assertSame(['report.csv'], $this->filesIn($this->workDir));
    }

    public function testDownloadedFileGetsNormalPermissions(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('POSIX file modes only.');
        }

        $destination = $this->workDir . '/report.csv';

        $this->createDownloader([$this->sampleResponse()])->download($destination);

        $this->assertSame(0666 & ~umask(), fileperms($destination) & 0777);
    }

    public function testDownloadKeepsFileUntilTheDownloaderIsDestroyed(): void
    {
        $destination = $this->workDir . '/report.csv';
        $downloader  = $this->createDownloader([$this->sampleResponse()]);

        $downloader->download($destination);
        unset($downloader);

        $this->assertFileExists($destination);
    }

    public function testFailedDownloadLeavesExistingFileUntouched(): void
    {
        $destination = $this->workDir . '/report.csv';
        file_put_contents($destination, 'previous report');

        $downloader = $this->createDownloader([new Response(403)]);

        try {
            $downloader->download($destination);
            $this->fail('Expected a ClientException.');
        } catch (ClientException $e) {
            $this->assertSame(403, $e->getResponse()->getStatusCode());
        }

        $this->assertStringEqualsFile($destination, 'previous report');
        $this->assertSame(['report.csv'], $this->filesIn($this->workDir));
    }

    public function testFailedDownloadDoesNotCreateTheDestination(): void
    {
        $destination = $this->workDir . '/report.csv';

        try {
            $this->createDownloader([new Response(403)])->download($destination);
        } catch (ClientException) {
        }

        $this->assertFileDoesNotExist($destination);
        $this->assertSame([], $this->filesIn($this->workDir));
    }

    public function testDestinationDirectoryMustExist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createDownloader([$this->sampleResponse()])
            ->download($this->workDir . '/missing/report.csv');
    }

    public function testDestinationMustNotBeADirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createDownloader([$this->sampleResponse()])
            ->download($this->workDir);
    }

    public function testDestinationDirectoryMustBeWritable(): void
    {
        if (DIRECTORY_SEPARATOR === '\\' || posix_geteuid() === 0) {
            $this->markTestSkipped('Needs a POSIX user who is subject to file permissions.');
        }

        $directory = $this->workDir . '/read-only';
        mkdir($directory, 0555);

        try {
            $this->createDownloader([$this->sampleResponse()])
                ->download($directory . '/report.csv');
            $this->fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('not a writable directory', $e->getMessage());
        } finally {
            chmod($directory, 0755);
            rmdir($directory);
        }
    }

    public function testFailureToMoveTheFileIntoPlaceIsReportedAndCleanedUp(): void
    {
        $destination = $this->workDir . '/report.csv';

        // Something else creates a directory at the destination while the
        // download is in progress, so the final rename() cannot succeed.
        $client = new class ($destination, $this->sampleResponse()) implements ClientInterface {
            public function __construct(private string $destination, private Response $response) {}

            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                throw new LogicException('Not used.');
            }

            public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
            {
                throw new LogicException('Not used.');
            }

            public function request(string $method, $uri, array $options = []): ResponseInterface
            {
                file_put_contents($options['sink'], (string) $this->response->getBody());
                mkdir($this->destination);

                return $this->response;
            }

            public function requestAsync(string $method, $uri, array $options = []): PromiseInterface
            {
                throw new LogicException('Not used.');
            }

            public function getConfig(?string $option = null)
            {
                return null;
            }
        };

        $downloader = new Downloader($this->report, $client);

        try {
            $downloader->download($destination);
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Unable to move', $e->getMessage());
        } finally {
            $remaining = $this->filesIn($this->workDir);
            rmdir($destination);
        }

        $this->assertSame(['report.csv'], $remaining, 'Only the directory should remain, not the temporary file.');
    }

    public function testFailureKeepsThePreviousResponse(): void
    {
        $downloader = $this->createDownloader([$this->sampleResponse(), new Response(403)]);

        $first = $downloader->download();

        try {
            $downloader->download();
        } catch (ClientException) {
        }

        $this->assertSame($first, $downloader->getResponse());
    }

    public function testRetriesTransientFailures(): void
    {
        $downloader = $this->createDownloader([
            new Response(503),
            $this->connectException(),
            new Response(429),
            $this->sampleResponse(),
        ]);

        $response = $downloader->download();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(4, $this->history);
        $this->assertCount(3, $this->sleeps);
    }

    public function testBackoffGrowsAndIsCapped(): void
    {
        $downloader = $this->createDownloader(array_fill(0, 5, new Response(503)));

        try {
            $downloader->download();
        } catch (ServerException) {
        }

        $this->assertCount(4, $this->sleeps);

        foreach ($this->sleeps as $index => $seconds) {
            $this->assertGreaterThanOrEqual(0.0, $seconds);
            $this->assertLessThanOrEqual(min(60.0, 2 ** $index), $seconds);
        }
    }

    public function testGivesUpAfterMaxAttempts(): void
    {
        $downloader = $this->createDownloader(array_fill(0, 5, new Response(503)));

        $this->expectException(ServerException::class);

        try {
            $downloader->download();
        } finally {
            $this->assertCount(5, $this->history);
        }
    }

    public function testDoesNotRetryClientErrors(): void
    {
        $downloader = $this->createDownloader([new Response(403), $this->sampleResponse()]);

        try {
            $downloader->download();
            $this->fail('Expected a ClientException.');
        } catch (ClientException) {
        }

        $this->assertCount(1, $this->history);
        $this->assertSame([], $this->sleeps);
    }

    public function testRetriedDownloadToDestinationStillSucceeds(): void
    {
        $destination = $this->workDir . '/report.csv';
        $downloader  = $this->createDownloader([new Response(503), $this->sampleResponse()]);

        $downloader->download($destination);

        $this->assertFileEquals(self::PATH_TO_SAMPLE_REPORT, $destination);
        $this->assertSame(['report.csv'], $this->filesIn($this->workDir));
    }

    public function testRawGuzzleClientLeaksTheTokenSoTheRedactionTestsMeanSomething(): void
    {
        $stack  = HandlerStack::create(new MockHandler([new Response(403)]));
        $client = new Client(['handler' => $stack]);

        try {
            $client->request('GET', $this->report->getUri());
            $this->fail('Expected a ClientException.');
        } catch (ClientException $e) {
            $this->assertStringContainsString('token=', $e->getMessage());
            $this->assertStringContainsString(
                urlencode(self::TOKEN),
                (string) $e->getRequest()->getUri()
            );
        }
    }

    #[DataProvider('failureProvider')]
    public function testExceptionsNeverContainTheToken(
        callable $failure,
        string $expectedClass
    ): void {
        $downloader = $this->createDownloader(array_fill(0, 5, $failure($this)), [], 2);

        try {
            $downloader->download();
            $this->fail('Expected ' . $expectedClass . '.');
        } catch (Throwable $e) {
            $this->assertInstanceOf($expectedClass, $e);
            $this->assertNull($e->getPrevious());

            foreach ([self::TOKEN, urlencode(self::TOKEN), rawurlencode(self::TOKEN)] as $needle) {
                $this->assertStringNotContainsString($needle, (string) $e);
                $this->assertStringNotContainsString($needle, (string) $e->getRequest()->getUri());
                $this->assertStringNotContainsString($needle, var_export($e->getHandlerContext(), true));
            }

            $this->assertStringContainsString(
                'token=REDACTED',
                (string) $e->getRequest()->getUri()
            );
        }
    }

    public static function failureProvider(): array
    {
        return [
            'HTTP 403' => [
                static fn (self $test) => new Response(403),
                ClientException::class,
            ],
            'HTTP 503' => [
                static fn (self $test) => new Response(503),
                ServerException::class,
            ],
            'connection timeout' => [
                static fn (self $test) => $test->connectException(),
                ConnectException::class,
            ],
        ];
    }

    public function testRedactedExceptionKeepsTheResponse(): void
    {
        $downloader = $this->createDownloader([new Response(403, [], 'denied')]);

        try {
            $downloader->download();
            $this->fail('Expected a ClientException.');
        } catch (ClientException $e) {
            $this->assertSame(403, $e->getResponse()->getStatusCode());
            $this->assertSame('denied', (string) $e->getResponse()->getBody());
        }
    }

    public function testUnrelatedExceptionsPassThroughUnchanged(): void
    {
        $exception  = new LogicException('boom');
        $downloader = $this->createDownloader([$exception]);

        try {
            $downloader->download();
            $this->fail('Expected a LogicException.');
        } catch (LogicException $e) {
            $this->assertSame($exception, $e);
        }
    }

    public function testMissingReportSettingsFailBeforeAnyRequest(): void
    {
        $downloader = new Downloader(
            new SignatureOrders(),
            new Client(['handler' => HandlerStack::create(new MockHandler([]))])
        );

        $this->expectException(BadMethodCallException::class);

        $downloader->download();
    }

    protected function sampleResponse(): Response
    {
        return new Response(200, [], $this->createSampleReportStream());
    }

    protected function createDownloader(
        array $queue,
        array $options = [],
        int $maxAttempts = 5
    ): Downloader {
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        return new Downloader(
            $this->report,
            new Client(['handler' => $stack]),
            $options,
            new RetryPolicy(
                $maxAttempts,
                1.0,
                60.0,
                function (float $seconds): void {
                    $this->sleeps[] = $seconds;
                }
            )
        );
    }

    protected function connectException(): ConnectException
    {
        return new ConnectException(
            'cURL error 28: Operation timed out for ' . $this->report->getUri(),
            new Request('GET', $this->report->getUri())
        );
    }

    protected function filesIn(string $directory): array
    {
        return array_values(array_diff(
            scandir($directory) ?: [],
            ['.', '..']
        ));
    }
}
