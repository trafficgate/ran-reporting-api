<?php

declare(strict_types=1);

namespace Linkshare\Api\RanReporting;

use Generator;
use League\Csv\Reader as CsvReader;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Stringable;

/**
 * @phpstan-consistent-constructor
 */
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
        $rawRecords = $this->rawRecords($csvReader->getInputBOM());
        $row        = $csvReader->first();
        $line       = $rawRecords->valid() ? $rawRecords->current() : '';

        return $this->recordFactory->create($row, $line);
    }

    /**
     * @return Generator<int, mixed> One record per row, as built by the RecordFactory
     */
    public function parse(): Generator
    {
        $csvReader  = $this->createCsvReader();
        $rawRecords = $this->rawRecords($csvReader->getInputBOM());
        $isHeader   = true;

        foreach ($csvReader->getRecords() as $row) {
            if (! $rawRecords->valid()) {
                throw new RuntimeException(
                    'Unable to match a parsed record to its source text.'
                );
            }

            $line = $rawRecords->current();
            $rawRecords->next();

            if ($isHeader) {
                // Skip the first record (header)
                $isHeader = false;

                continue;
            }

            yield $this->recordFactory->create($row, $line);
        }
    }

    /**
     * @return CsvReader<array<array-key, mixed>>
     */
    protected function createCsvReader(): CsvReader
    {
        $csvReader = match ($this->sourceType) {
            self::SOURCE_STRING => CsvReader::fromString($this->source),
            self::SOURCE_PATH   => CsvReader::from($this->source),
            default             => throw new LogicException('Unknown source type ' . $this->sourceType . '.'),
        };

        // RFC 4180: a quote inside a field is written as two quotes and a
        // backslash is an ordinary character. rawRecords() relies on this.
        $csvReader->setEscape('');
        $csvReader->skipInputBOM();

        return $csvReader;
    }

    /**
     * Yields the source text of each CSV record, without line endings or BOM,
     * in the same order as the records League\Csv parses.
     *
     * A record is not always one line: a quoted field may contain newlines,
     * so lines are joined until the quotes balance. Blank lines are skipped,
     * as League\Csv skips them.
     *
     * @return Generator<int, string>
     */
    protected function rawRecords(string $bom): Generator
    {
        $stream = $this->openStream();

        try {
            $buffer      = '';
            $isFirstLine = true;

            while (($physicalLine = fgets($stream)) !== false) {
                if ($isFirstLine && $bom !== '' && str_starts_with($physicalLine, $bom)) {
                    $physicalLine = substr($physicalLine, strlen($bom));
                }

                $isFirstLine = false;
                $buffer .= $physicalLine;

                if (substr_count($buffer, '"') % 2 !== 0) {
                    continue;
                }

                $record = $this->stripNewline($buffer);
                $buffer = '';

                if ($record !== '') {
                    yield $record;
                }
            }

            if ($buffer !== '') {
                // The source ends inside a quoted field.
                yield $this->stripNewline($buffer);
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return resource
     */
    protected function openStream()
    {
        $stream = $this->sourceType === self::SOURCE_PATH
            ? @fopen($this->source, 'rb')
            : fopen('php://temp', 'r+b');

        if ($stream === false) {
            throw new RuntimeException('Unable to open ' . $this->source . '.');
        }

        if ($this->sourceType === self::SOURCE_STRING) {
            fwrite($stream, $this->source);
            rewind($stream);
        }

        return $stream;
    }

    protected function stripNewline(string $line): string
    {
        return rtrim($line, "\n\r");
    }
}
