# Contributing

Contributions are welcome! Please open an issue to discuss larger changes before sending a pull request.

## Development setup

```bash
git clone https://github.com/mauriziofonte/php-base62x.git
cd php-base62x
composer install
```

## Quality checks

Every pull request must pass the same checks that run in CI:

```bash
composer test      # PHPUnit test suite
composer analyse   # PHPStan static analysis
composer cs        # coding style check (use `composer fix` to apply it)
composer ci        # all of the above
infection          # mutation testing (requires https://infection.github.io and pcov or xdebug)
```

Optional extensions (`sodium`, `bz2`, `zstd`, `brotli`) enable more tests: the tests of a missing extension are skipped.

## Guidelines

- The library supports **PHP 7.2+**: do not use syntax or functions introduced in later versions.
- The library works on **raw bytes**: never use `mb_*` functions on payloads.
- The encoded format is a public contract: payloads produced by previous releases must keep decoding.
  `tests/Unit/BackwardCompatibilityTest.php` guards it.
- The output must stay identical to the reference implementations: `tests/Unit/fixtures/interop-vectors.json`
  is verified against the PHP, Python and JavaScript implementations of https://github.com/wadelau/Base62x.
- Releases: add the version section to `CHANGELOG.md`, then push a `vX.Y.Z` tag: the GitHub release is created
  automatically with the changelog section as release notes.
- Add tests for every bug fix and new feature, and add an entry to `CHANGELOG.md` under `Unreleased`.
