<?php

namespace Linkshare\Api\RanReporting;

use League\Csv\Reader as CsvReader;
use SplFileObject;
use SplTempFileObject;

class Parser
{
    public const SOURCE_STRING = 'string';
    public const SOURCE_PATH   = 'path';

    protected $source;
    protected $sourceType;
    protected $recordFactory;

    protected function __construct(
        $source,
        $sourceType,
        ?RecordFactory $recordFactory = null
    ) {
        if (! isset($recordFactory)) {
            $recordFactory = new ArrayFactory();
        }

        $this->source        = $source;
        $this->sourceType    = $sourceType;
        $this->recordFactory = $recordFactory;
    }

    public static function createFromResponse(
        $response,
        ?RecordFactory $recordFactory = null
    ) {
        return static::createFromString(
            $response->getBody(),
            $recordFactory
        );
    }

    public static function createFromString(
        $string,
        ?RecordFactory $recordFactory = null
    ) {
        return new static($string, self::SOURCE_STRING, $recordFactory);
    }

    public static function createFromPath(
        $path,
        ?RecordFactory $recordFactory = null
    ) {
        return new static($path, self::SOURCE_PATH, $recordFactory);
    }

    public function getHeader()
    {
        $csvReader  = $this->createCsvReader();
        $sourceFile = $this->createFileObject();
        $inputBom   = $csvReader->getInputBOM();
        $row        = $csvReader->fetchOne();
        $line       = $sourceFile->fgets();
        $line       = $this->stripNewline($line);

        if (! empty($inputBom)) {
            $line = $this->stripBom($line, $inputBom);
        }

        return $this->recordFactory->create($row, $line);
    }

    public function parse()
    {
        $csvReader  = $this->createCsvReader();
        $sourceFile = $this->createFileObject();

        foreach ($csvReader->fetch() as $offset => $row) {
            $line = $sourceFile->fgets();
            $line = $this->stripNewline($line);

            if ($offset == 0) {
                // Skip the first line (header)
                continue;
            }

            yield $this->recordFactory->create($row, $line);
        }
    }

    protected function createCsvReader()
    {
        $csvReader = null;

        if ($this->sourceType === self::SOURCE_STRING) {
            $csvReader = CsvReader::createFromString($this->source);
        } elseif ($this->sourceType === self::SOURCE_PATH) {
            $csvReader = CsvReader::createFromPath($this->source);
        }

        $csvReader->stripBom(true);

        return $csvReader;
    }

    protected function createFileObject()
    {
        $sourceFile = null;

        if ($this->sourceType === self::SOURCE_STRING) {
            $sourceFile = new SplTempFileObject();

            $sourceFile->fwrite($this->source);
            $sourceFile->rewind();
        } elseif ($this->sourceType === self::SOURCE_PATH) {
            $sourceFile = new SplFileObject($this->source);
        }

        return $sourceFile;
    }

    protected function stripNewline(string $line)
    {
        return rtrim($line, "\n\r");
    }

    protected function stripBom(string $line, string $bom)
    {
        return mb_substr($line, mb_strlen($bom));
    }
}
