# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Native parameter, return, and property types throughout, with `declare(strict_types=1)` in every file.
- `psr/http-message` and `guzzlehttp/psr7` as explicit requirements, since the library uses them directly.
- `Downloader` accepts an injectable `GuzzleHttp\ClientInterface` and default Guzzle request options (`proxy`, `verify`, `timeout`, ...), so it can be tested with a mock handler.
- `RetryPolicy`: `Downloader` retries connection errors, timeouts, HTTP 5xx and HTTP 429 with exponential backoff and jitter (5 attempts by default). Other failures such as a 403 are not retried.
- The API token is removed from exceptions thrown by `Downloader::download()`. They keep their Guzzle class, response and status code, but the message, request, handler context and previous exception no longer contain the token.
- `Report::getRedactedUri()`, and `var_dump()` / `print_r()` of a `Report` hide the token.
- `Downloader::download($destination)` writes to a temporary file and moves it into place only on success, so a failed download never replaces or truncates an existing file.
- PHPStan static analysis (`composer analyse`): `src/` at the strictest level and `tests/` at level 8, both without a baseline. CI runs it in a new `static-analysis` workflow.
- A `coverage` CI job that measures code coverage with pcov and reports it in the job summary. `composer test`, `composer test-coverage`, `composer analyse` and `composer cs` scripts.
- Tests for invalid setter values, missing report settings, wrong scalar types, `Parser::createFromResponse()`, retries, atomic downloads, token redaction, and parsing of blank lines, multi-line fields, and malformed rows. Integration tests run `Downloader` over real HTTP against PHP's built-in web server.

### Changed

- `Parser` follows RFC 4180 quoting: a backslash is an ordinary character, so `\"` no longer escapes a quote. This keeps parsed rows and their source text aligned.
- `Transaction` throws an `InvalidArgumentException` (without quoting the row) if a row has fewer than 12 columns or a column is not a string, instead of failing on an undefined array key or a `TypeError`.
- `RecordFactory::create()` and the `Parser` document their types for static analysis: `Parser::parse()` is a `Generator<int, mixed>`, and `Downloader::download()` declares the exceptions it throws.
- Exceptions thrown by `Downloader::download()` now carry a copy of the response with the token removed from its headers and body.
- Minimum supported PHP version (`^7.2|^8.0` => `^8.2`).
- Minimum supported versions for dependencies.
  - guzzlehttp/guzzle (`^6.1.1|^7.0` => `^7.8`)
  - league/csv (`^9.4` => `^9.27`)
- Development dependencies: PHPUnit (`^8.5.14|^9.0` => `^11.0`), fakerphp/faker (`^1.23`), friendsofphp/php-cs-fixer (`^3.50`), psy/psysh (`^0.12`).
- `Report` setters now return `static`. Getters return nullable types.
- `Transaction` properties are `readonly`.
- `RecordFactory::create()` has a `mixed` return type, so implementations must declare a compatible return type.
- `Parser::createFromResponse()` now requires a PSR-7 `ResponseInterface`. `Parser::createFromString()` accepts a `string` or `Stringable`.
- `Downloader` constructor: `new Downloader($report, $sinkDir, $sinkPrefix)` is now `new Downloader($report, $client, $options, $retryPolicy)`. Use `download($destination)` to save to a file.
- `Downloader::download()` throws an `InvalidArgumentException` if the destination directory is not a writable directory. Previously PHP silently fell back to the system temp directory.
- `Parser` uses `Reader::fromString()`, `Reader::from()` and `Reader::first()` instead of the methods league/csv deprecated in 9.9 and 9.27.
- CI runs on PHP 8.2 to 8.5 and uses current GitHub Actions versions.

### Removed

- Support for PHP < 8.2 and Guzzle 6.
- `Downloader::getSinkPath()` and the temporary sink file that was deleted when the `Downloader` was destroyed. Pass a destination to `download()` instead.

### Fixed

- `Parser` paired records with the wrong `originalString()` when the source had blank lines or quoted fields containing line breaks, and treated the header as a data record when blank lines came before it. Each record now gets its own complete source text.

## [2.0.1] - 2026-10-16

### Added

- Expanded README with installation and usage documentation for building report URLs, downloading reports, and parsing records.
- `CLAUDE.md` with guidance for working in this repository with Claude Code.

### Changed

- Reformatted Markdown, YAML, and `composer.json` files with Prettier (whitespace only, no functional change).

## [2.0.0] - 2023-09-07

### Added

- Github Actions workflows.

### Changed

- Minimum supported versions for dependencies.
  - guzzlehttp/guzzle (`~6.0` => `^6.1.1|^7.0`)
  - league/csv (`~8.0` => `^9.4`)
- Minimum supported versions for development dependencies.
  - fakerphp/faker (`^1.6` => `^1.9`)
    - Replaces abandoned `fzaninotto/faker`
  - friendsofphp/php-cs-fixer (`^1.12` => `^3.0`)
  - phpunit/phpunit (`~5.5` => `^8.5.14|^9.0`)
  - psy/psysh (`~0.7` => `^0.11.20`)

### Removed

- Support for PHP < 7.2.
- Dependency on `laravel/support`.
- Travis CI workflow.

## [1.0.1] - 2017-09-01

- Please fill me out.

## [1.0.0] - 2016-10-03

- Please fill me out.

[Unreleased]: https://github.com/trafficgate/ran-reporting-api/compare/v2.0.1...HEAD
[2.0.1]: https://github.com/trafficgate/ran-reporting-api/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/trafficgate/ran-reporting-api/compare/v1.0.1...v2.0.0
[1.0.1]: https://github.com/trafficgate/ran-reporting-api/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/trafficgate/ran-reporting-api/releases/tag/v1.0.0
