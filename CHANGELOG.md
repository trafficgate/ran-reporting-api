# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.0.0] - 2026-10-16

### Added

- Native parameter, return, and property types throughout, with `declare(strict_types=1)` in every file.
- `ext-mbstring` and `psr/http-message` as explicit requirements.
- Tests for invalid setter values, missing report settings, wrong scalar types, `Parser::createFromResponse()`, and `Downloader` cleanup.

### Changed

- Minimum supported PHP version (`^7.2|^8.0` => `^8.2`).
- Minimum supported versions for dependencies.
  - guzzlehttp/guzzle (`^6.1.1|^7.0` => `^7.8`)
  - league/csv (`^9.4` => `^9.15`)
- Development dependencies: PHPUnit (`^8.5.14|^9.0` => `^11.0`), fakerphp/faker (`^1.23`), friendsofphp/php-cs-fixer (`^3.50`), psy/psysh (`^0.12`).
- `Report` setters now return `static`. Getters return nullable types.
- `Transaction` properties are `readonly`.
- `RecordFactory::create()` has a `mixed` return type, so implementations must declare a compatible return type.
- `Parser::createFromResponse()` now requires a PSR-7 `ResponseInterface`. `Parser::createFromString()` accepts a `string` or `Stringable`.
- `Downloader` throws an `InvalidArgumentException` if the sink directory is not a writable directory. Previously PHP silently fell back to the system temp directory.
- `Downloader` no longer emits a warning if its temporary file was already removed.
- CI runs on PHP 8.2 to 8.5 and uses current GitHub Actions versions.

### Removed

- Support for PHP < 8.2 and Guzzle 6.

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

[Unreleased]: https://github.com/trafficgate/ran-reporting-api/compare/v3.0.0...HEAD
[3.0.0]: https://github.com/trafficgate/ran-reporting-api/compare/v2.0.1...v3.0.0
[2.0.1]: https://github.com/trafficgate/ran-reporting-api/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/trafficgate/ran-reporting-api/compare/v1.0.1...v2.0.0
[1.0.1]: https://github.com/trafficgate/ran-reporting-api/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/trafficgate/ran-reporting-api/releases/tag/v1.0.0
