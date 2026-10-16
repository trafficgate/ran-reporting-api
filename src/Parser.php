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

    private const DELIMITER = ',';
    private const ENCLOSURE = '"';

    // Where the scanner in rawRecords() is within a field.
    private const AT_FIELD_START = 0;
    private const IN_UNQUOTED    = 1;
    private const IN_QUOTED      = 2;
    private const AFTER_QUOTE    = 3;

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
        $csvReader->setDelimiter(self::DELIMITER);
        $csvReader->setEnclosure(self::ENCLOSURE);
        $csvReader->setEscape('');
        $csvReader->skipInputBOM();

        return $csvReader;
    }

    /**
     * Yields the source text of each CSV record, without line endings or BOM,
     * in the same order as the records League\Csv parses.
     *
     * A record is not always one line: a quoted field may contain newlines,
     * so lines are joined until the quoted field ends. Whether a quote opens
     * a field is decided as PHP's CSV reader does (see advance()). Blank lines
     * are skipped, as League\Csv skips them.
     *
     * @return Generator<int, string>
     */
    protected function rawRecords(string $bom): Generator
    {
        $stream = $this->openStream();

        try {
            $buffer      = '';
            $state       = self::AT_FIELD_START;
            $isFirstLine = true;

            while (($physicalLine = fgets($stream)) !== false) {
                if ($isFirstLine && $bom !== '' && str_starts_with($physicalLine, $bom)) {
                    $physicalLine = substr($physicalLine, strlen($bom));
                }

                $isFirstLine = false;
                $buffer .= $physicalLine;

                // Most lines contain no enclosure and cannot change the state.
                if (str_contains($physicalLine, self::ENCLOSURE)) {
                    $state = $this->advance($physicalLine, $state);
                }

                if ($state === self::IN_QUOTED) {
                    continue;
                }

                $record = $this->stripNewline($buffer);
                $buffer = '';
                $state  = self::AT_FIELD_START;

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

    /**
     * Follows one line of CSV text and returns where the scanner ends up.
     *
     * The rules are PHP's own, which League\Csv uses: a quote opens a quoted
     * field only at the start of a field, after optional spaces or tabs. A
     * quote anywhere else is ordinary text, so `5" pipe` is just text. Inside a
     * quoted field two quotes are one literal quote, and a single quote ends
     * the field; whatever follows it up to the next delimiter is more text.
     */
    private function advance(string $line, int $state): int
    {
        $length = strlen($line);

        for ($i = 0; $i < $length; ++$i) {
            $char = $line[$i];

            switch ($state) {
                case self::AT_FIELD_START:
                    if ($char === self::ENCLOSURE) {
                        $state = self::IN_QUOTED;
                    } elseif ($char !== self::DELIMITER && $char !== ' ' && $char !== "\t") {
                        $state = self::IN_UNQUOTED;
                    }

                    break;

                case self::IN_UNQUOTED:
                case self::AFTER_QUOTE:
                    if ($char === self::DELIMITER) {
                        $state = self::AT_FIELD_START;
                    }

                    break;

                case self::IN_QUOTED:
                    if ($char === self::ENCLOSURE) {
                        if (($line[$i + 1] ?? '') === self::ENCLOSURE) {
                            ++$i;
                        } else {
                            $state = self::AFTER_QUOTE;
                        }
                    }

                    break;
            }
        }

        return $state;
    }
}
