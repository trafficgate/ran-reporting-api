<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting\Tests;

use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public const PATH_TO_SAMPLE_REPORT = __DIR__ . '/data/signature-orders.csv';

    protected function createSampleReportStream(): Stream
    {
        $handle = fopen(self::PATH_TO_SAMPLE_REPORT, 'r');

        if ($handle === false) {
            throw new RuntimeException('Cannot open ' . self::PATH_TO_SAMPLE_REPORT);
        }

        return new Stream($handle);
    }
}
