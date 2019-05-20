<?php

namespace Linkshare\Api\RanReporting;

use League\Csv\Reader as CsvReader;
use Psr\Http\Message\ResponseInterface;
use SplFileObject;
use SplTempFileObject;

class Parser
{
    const SOURCE_STRING = 'string';
    const SOURCE_PATH   = 'path';

    /**
     * @var string
     */
    protected $source;

    /**
     * @var string
     */
    protected $sourceType;

    /**
     * @var RecordFactory
     */
    protected $recordFactory;

    /**
     * @param ResponseInterface $response
     */
    public static function createFromResponse(
        ResponseInterface $response,
        ?RecordFactory $recordFactory = null
    ): Parser {
        return static::createFromString(
            $response->getBody(),
            $recordFactory
        );
    }

    public static function createFromString(
        string $string,
        ?RecordFactory $recordFactory = null
    ): Parser {
        return new static($string, self::SOURCE_STRING, $recordFactory);
    }

    public static function createFromPath(
        string $path,
        ?RecordFactory $recordFactory = null
    ): Parser {
        return new static($path, self::SOURCE_PATH, $recordFactory);
    }

    protected function __construct(
        string $source,
        string $sourceType,
        ?RecordFactory $recordFactory = null
    ) {
        if (! isset($recordFactory)) {
            $recordFactory = new ArrayFactory();
        }

        $this->source        = $source;
        $this->sourceType    = $sourceType;
        $this->recordFactory = $recordFactory;
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

        foreach ($csvReader->getRecords() as $offset => $row) {
            $line = $sourceFile->fgets();
            $line = $this->stripNewline($line);

            if ($offset == 0) {
                // Skip the first line (header)
                continue;
            }

            yield $this->recordFactory->create($row, $line);
        }
    }

    protected function createCsvReader(): CsvReader
    {
        $csvReader = null;

        if ($this->sourceType === self::SOURCE_STRING) {
            $csvReader = CsvReader::createFromString($this->source);
        } elseif ($this->sourceType === self::SOURCE_PATH) {
            $csvReader = CsvReader::createFromPath($this->source);
        }

        return $csvReader;
    }

    protected function createFileObject(): SplFileObject
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

    protected function stripNewline(string $line): string
    {
        return rtrim($line, "\n\r");
    }

    protected function stripBom(string $line, string $bom): string
    {
        return mb_substr($line, mb_strlen($bom));
    }
}
