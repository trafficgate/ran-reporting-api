<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Linkshare\Api\RanReporting\Downloader;
use Linkshare\Api\RanReporting\Parser;
use Linkshare\Api\RanReporting\Report;
use Linkshare\Api\RanReporting\RetryPolicy;
use Linkshare\Api\RanReporting\SignatureOrders;
use Linkshare\Api\RanReporting\SignatureOrders\TransactionFactory;
use Linkshare\Api\RanReporting\Tests\TestCase;
use ReflectionProperty;
use RuntimeException;
use Throwable;

/**
 * Exercises Downloader over real HTTP against PHP's built-in web server
 * (tests/Integration/router.php), so the real cURL handler produces the
 * exceptions that the unit tests can only imitate. Needs no network access.
 */
class DownloaderHttpTest extends TestCase
{
    public const TOKEN = 'live-S3CRET/t0ken+value';

    private const PROXY_VARIABLES = [
        'HTTP_PROXY', 'http_proxy', 'HTTPS_PROXY', 'https_proxy',
        'ALL_PROXY', 'all_proxy', 'NO_PROXY', 'no_proxy',
    ];

    /**
     * @var resource|null
     */
    private static $server;
    private static int $port;
    private static string $stateDir;

    /**
     * @var array<string, array{false|string, mixed}>
     */
    private static array $savedEnvironment = [];

    private string $workDir;

    public static function setUpBeforeClass(): void
    {
        self::$stateDir = sys_get_temp_dir() . '/ran-reporting-api-http-' . bin2hex(random_bytes(6));
        mkdir(self::$stateDir);

        try {
            self::$port = self::freePort();
        } catch (RuntimeException $e) {
            self::markTestSkipped($e->getMessage());
        }

        $process = @proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, __DIR__ . '/router.php'],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            ['RAN_TEST_STATE_DIR' => self::$stateDir, 'PATH' => (string) getenv('PATH')]
        );

        if (! is_resource($process)) {
            self::markTestSkipped('Cannot start the PHP built-in web server.');
        }

        self::$server = $process;

        $deadline = microtime(true) + 5;

        while (microtime(true) < $deadline) {
            $connection = @fsockopen('127.0.0.1', self::$port, $errorCode, $errorMessage, 0.2);

            if ($connection !== false) {
                fclose($connection);

                break;
            }

            usleep(50_000);
        }

        if (! isset($connection) || $connection === false) {
            self::stopServer();
            self::markTestSkipped('The PHP built-in web server did not start.');
        }

        // A developer's proxy settings would otherwise route requests to
        // 127.0.0.1 through that proxy.
        foreach (self::PROXY_VARIABLES as $name) {
            self::$savedEnvironment[$name] = [getenv($name), $_SERVER[$name] ?? null];
            putenv($name);
            unset($_SERVER[$name]);
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();

        foreach (self::$savedEnvironment as $name => [$environment, $server]) {
            if ($environment !== false) {
                putenv($name . '=' . $environment);
            }

            if ($server !== null) {
                $_SERVER[$name] = $server;
            }
        }

        foreach (glob(self::$stateDir . '/*') ?: [] as $file) {
            unlink($file);
        }

        @rmdir(self::$stateDir);
    }

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/ran-reporting-api-dl-' . bin2hex(random_bytes(6));
        mkdir($this->workDir);

        if (file_exists(self::$stateDir . '/flaky-counter')) {
            unlink(self::$stateDir . '/flaky-counter');
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workDir . '/{,.}*', GLOB_BRACE) ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($this->workDir);
    }

    public function testDownloadsAndParsesAReport(): void
    {
        $destination = $this->workDir . '/report.csv';
        $downloader  = new Downloader($this->report($this->server('ok')));

        $response = $downloader->download($destination);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFileEquals(self::PATH_TO_SAMPLE_REPORT, $destination);

        $records = iterator_to_array(
            Parser::createFromPath($destination, new TransactionFactory())->parse(),
            false
        );

        $this->assertCount(1532, $records);
        $this->assertSame('4983949625568', $records[0]->memberId());
    }

    public function testDownloadsIntoMemoryAndParsesTheResponse(): void
    {
        $downloader = new Downloader($this->report($this->server('ok')));

        $parser = Parser::createFromResponse($downloader->download(), new TransactionFactory());

        $this->assertSame(1532, iterator_count($parser->parse()));
    }

    public function testDefaultClientHasA600SecondTimeout(): void
    {
        $downloader = new Downloader($this->report($this->server('ok')));
        $client     = (new ReflectionProperty($downloader, 'client'))->getValue($downloader);
        $config     = (new ReflectionProperty(Client::class, 'config'))->getValue($client);

        $this->assertSame(600, $config['timeout']);
    }

    public function testRetriesRealServerFailuresThenSucceeds(): void
    {
        $destination = $this->workDir . '/report.csv';
        $downloader  = new Downloader(
            $this->report($this->server('flaky/2')),
            null,
            [],
            $this->noSleep()
        );

        $downloader->download($destination);

        $this->assertSame('3', file_get_contents(self::$stateDir . '/flaky-counter'));
        $this->assertFileEquals(self::PATH_TO_SAMPLE_REPORT, $destination);
    }

    public function testGivesUpOnPersistentServerFailures(): void
    {
        $destination = $this->workDir . '/report.csv';
        $downloader  = new Downloader(
            $this->report($this->server('flaky/99')),
            null,
            [],
            $this->noSleep(3)
        );

        try {
            $downloader->download($destination);
            $this->fail('Expected a ServerException.');
        } catch (ServerException $e) {
            $this->assertSame(503, $e->getResponse()->getStatusCode());
        }

        $this->assertSame('3', file_get_contents(self::$stateDir . '/flaky-counter'));
        $this->assertFileDoesNotExist($destination);
        $this->assertSame([], array_values(array_diff(scandir($this->workDir), ['.', '..'])));
    }

    public function testRealHttpErrorsNeverRevealTheToken(): void
    {
        foreach ([403 => ClientException::class, 503 => ServerException::class] as $status => $class) {
            $report = $this->report($this->server('status/' . $status));

            // The test only means something if a plain client does leak it.
            try {
                (new Client())->request('GET', $report->getUri());
                $this->fail('Expected an exception.');
            } catch (RequestException $raw) {
                $this->assertStringContainsString('token=', $raw->getMessage());
                $this->assertNotNull($raw->getResponse());
                $this->assertStringContainsString(
                    urlencode(self::TOKEN),
                    (string) $raw->getResponse()->getBody(),
                    'The test server should echo the token in the error body.'
                );
            }

            try {
                (new Downloader($report, null, [], $this->noSleep(2)))->download();
                $this->fail('Expected ' . $class . '.');
            } catch (RequestException $e) {
                $this->assertInstanceOf($class, $e);
                $this->assertSame($status, $e->getResponse()->getStatusCode());
                $this->assertNull($e->getPrevious());
                $this->assertNoToken($e);
                $this->assertStringNotContainsString(urlencode(self::TOKEN), (string) $e->getRequest()->getUri());
                $this->assertStringNotContainsString(urlencode(self::TOKEN), (string) $e->getResponse()->getBody());
                $this->assertStringNotContainsString(self::TOKEN, var_export($e->getHandlerContext(), true));
                $this->assertStringContainsString('token=REDACTED', (string) $e->getResponse()->getBody());
            }
        }
    }

    public function testRefusedConnectionNeverRevealsTheToken(): void
    {
        $report = $this->report('http://127.0.0.1:' . self::freePort());

        try {
            (new Client())->request('GET', $report->getUri());
            $this->fail('Expected a ConnectException.');
        } catch (ConnectException $raw) {
            $leaks = str_contains($raw->getMessage(), 'token=')
                || str_contains(var_export($raw->getHandlerContext(), true), urlencode(self::TOKEN));

            $this->assertTrue($leaks, 'A plain Guzzle client should expose the token for this test to mean anything.');
        }

        try {
            (new Downloader($report, null, [], $this->noSleep(2)))->download();
            $this->fail('Expected a ConnectException.');
        } catch (ConnectException $e) {
            $this->assertNull($e->getPrevious());
            $this->assertNoToken($e);
            $this->assertStringNotContainsString(urlencode(self::TOKEN), (string) $e->getRequest()->getUri());
            $this->assertStringNotContainsString(urlencode(self::TOKEN), var_export($e->getHandlerContext(), true));
            $this->assertStringNotContainsString(self::TOKEN, var_export($e->getHandlerContext(), true));
        }
    }

    public function testTimeoutIsRetriedAndNeverRevealsTheToken(): void
    {
        $downloader = new Downloader(
            $this->report($this->server('slow')),
            null,
            ['timeout' => 0.5],
            $this->noSleep(2)
        );

        try {
            $downloader->download();
            $this->fail('Expected a ConnectException.');
        } catch (ConnectException $e) {
            $this->assertStringContainsString('cURL error 28', $e->getMessage());
            $this->assertNoToken($e);
            $this->assertStringNotContainsString(urlencode(self::TOKEN), var_export($e->getHandlerContext(), true));
        }
    }

    public function testProxyOptionIsHonoured(): void
    {
        $proxy = 'http://127.0.0.1:' . self::$port;

        $downloader = new Downloader(
            $this->report('http://ran.invalid'),
            null,
            ['proxy' => $proxy],
            $this->noSleep(1)
        );

        $response = $downloader->download();

        $this->assertSame(
            'http://ran.invalid/en/reports/signature-orders-report/filters',
            $response->getHeaderLine('X-Proxied-For')
        );
        $this->assertSame(
            file_get_contents(self::PATH_TO_SAMPLE_REPORT),
            (string) $response->getBody()
        );
    }

    /**
     * A signature orders report whose URL points at the local test server.
     */
    protected function report(string $baseUrl): SignatureOrders
    {
        return new class ($baseUrl, ['language' => Report::LANGUAGE_EN, 'startDate' => '2016-05-01', 'endDate' => '2016-07-31', 'network' => Report::NETWORK_US, 'timezone' => Report::TIMEZONE_GMT, 'dateType' => Report::DATE_TYPE_PROCESS, 'token' => DownloaderHttpTest::TOKEN]) extends SignatureOrders {
            public function __construct(private string $baseUrl, array $props)
            {
                parent::__construct($props);
            }

            protected function buildUri(?string $token): string
            {
                return str_replace(
                    'https://ran-reporting.rakutenmarketing.com',
                    $this->baseUrl,
                    parent::buildUri($token)
                );
            }
        };
    }

    protected function server(string $scenario): string
    {
        return 'http://127.0.0.1:' . self::$port . '/' . $scenario;
    }

    protected function noSleep(int $maxAttempts = 5): RetryPolicy
    {
        return new RetryPolicy($maxAttempts, 1.0, 60.0, static function (float $seconds): void {});
    }

    protected function assertNoToken(Throwable $e): void
    {
        foreach ([self::TOKEN, urlencode(self::TOKEN), rawurlencode(self::TOKEN)] as $needle) {
            $this->assertStringNotContainsString($needle, (string) $e);
            $this->assertStringNotContainsString($needle, $e->getMessage());
        }
    }

    /**
     * Asks the operating system for an unused TCP port on the loopback address.
     */
    private static function freePort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($socket === false) {
            throw new RuntimeException('Cannot open a local socket: ' . $errorMessage);
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        $colon = $name === false ? false : strrpos($name, ':');

        if ($name === false || $colon === false) {
            throw new RuntimeException('Cannot determine the local port.');
        }

        return (int) substr($name, $colon + 1);
    }

    private static function stopServer(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }
}
