<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting;

use GuzzleHttp\Client;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class Downloader
{
    public const DEFAULT_SINK_PREFIX = 'report-';

    protected ?string $sinkPath = null;

    protected ?ResponseInterface $response = null;

    public function __construct(
        protected Report $report,
        ?string $sinkDir = null,
        string $sinkPrefix = self::DEFAULT_SINK_PREFIX
    ) {
        if ($sinkDir === null) {
            return;
        }

        if (! is_dir($sinkDir) || ! is_writable($sinkDir)) {
            throw new InvalidArgumentException(
                $sinkDir . ' is not a writable directory.'
            );
        }

        $sinkPath = tempnam($sinkDir, $sinkPrefix);

        if ($sinkPath === false) {
            throw new RuntimeException(
                'Unable to create a temporary file in ' . $sinkDir . '.'
            );
        }

        $this->sinkPath = $sinkPath;
    }

    public function __destruct()
    {
        if (isset($this->sinkPath) && file_exists($this->sinkPath)) {
            unlink($this->sinkPath);
        }
    }

    public function download(array $options = []): ResponseInterface
    {
        $defaultOptions = isset($this->sinkPath)
            ? ['sink' => $this->sinkPath]
            : [];

        $client = new Client(array_merge($defaultOptions, $options));

        $this->response = $client->get($this->report->getUri());

        return $this->response;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    public function getSinkPath(): ?string
    {
        return $this->sinkPath;
    }
}
