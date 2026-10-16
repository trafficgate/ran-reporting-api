<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting;

use Generator;
use League\Csv\Reader as CsvReader;
use Psr\Http\Message\ResponseInterface;
use SplFileObject;
use SplTempFileObject;
use Stringable;

class Parser
{
    public const SOURCE_STRING = 'string';
    public const SOURCE_PATH   = 'path';

    protected RecordFactory $recordFactory;

    protected function __construct(
        protected string $source,
        protected string $sourceType,
        ?RecordFactory $recordFactory = null
    ) {
        $this->recordFactory = $recordFactory ?? new ArrayFactory();
    }

    public static function createFromResponse(
        ResponseInterface $response,
        ?RecordFactory $recordFactory = null
    ): static {
        return static::createFromString(
            (string) $response->getBody(),
            $recordFactory
        );
    }

    public static function createFromString(
        string|Stringable $string,
        ?RecordFactory $recordFactory = null
    ): static {
        return new static((string) $string, self::SOURCE_STRING, $recordFactory);
    }

    public static function createFromPath(
        string $path,
        ?RecordFactory $recordFactory = null
    ): static {
        return new static($path, self::SOURCE_PATH, $recordFactory);
    }

    public function getHeader(): mixed
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

    public function parse(): Generator
    {
        $csvReader  = $this->createCsvReader();
        $sourceFile = $this->createFileObject();

        foreach ($csvReader->getRecords() as $offset => $row) {
            $line = $sourceFile->fgets();
            $line = $this->stripNewline($line);

            if ($offset === 0) {
                // Skip the first line (header)
                continue;
            }

            yield $this->recordFactory->create($row, $line);
        }
    }

    protected function createCsvReader(): CsvReader
    {
        $csvReader = match ($this->sourceType) {
            self::SOURCE_STRING => CsvReader::createFromString($this->source),
            self::SOURCE_PATH   => CsvReader::createFromPath($this->source),
        };

        $csvReader->skipInputBOM();

        return $csvReader;
    }

    protected function createFileObject(): SplFileObject
    {
        return match ($this->sourceType) {
            self::SOURCE_STRING => $this->createTempFileObject(),
            self::SOURCE_PATH   => new SplFileObject($this->source),
        };
    }

    protected function createTempFileObject(): SplTempFileObject
    {
        $sourceFile = new SplTempFileObject();

        $sourceFile->fwrite($this->source);
        $sourceFile->rewind();

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
