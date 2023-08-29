<?php

namespace Linkshare\Api\RanReporting;

use GuzzleHttp\Client;

class Downloader
{
    public const DEFAULT_SINK_PREFIX = 'report-';

    /**
     * @var Report
     */
    protected $report;

    /**
     * @var string
     */
    protected $sinkPath;

    /**
     * @var Psr\Http\Message\ResponseInterface
     */
    protected $response;

    public function __construct(
        Report $report,
        ?string $sinkDir = null,
        string $sinkPrefix = self::DEFAULT_SINK_PREFIX
    ) {
        $this->report = $report;

        if (isset($sinkDir)) {
            $this->sinkPath = tempnam($sinkDir, $sinkPrefix);
        }
    }

    public function __destruct()
    {
        if (isset($this->sinkPath)) {
            unlink($this->sinkPath);
        }
    }

    public function download(array $options = [])
    {
        $defaultOptions = [
            'sink' => $this->sinkPath,
        ];

        $client = new Client(array_merge($defaultOptions, $options));

        $this->response = $client->get($this->report->getUri());

        return $this->response;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getSinkPath()
    {
        return $this->sinkPath;
    }
}
