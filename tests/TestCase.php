<?php

use GuzzleHttp\Psr7\Stream;

abstract class TestCase extends PHPUnit_Framework_TestCase
{
    const PATH_TO_SAMPLE_REPORT = __DIR__.'/data/signature-orders.csv';

    protected function createSampleReportStream()
    {
        return new Stream(fopen(self::PATH_TO_SAMPLE_REPORT, 'r'));
    }
}
