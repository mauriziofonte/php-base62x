# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.5.0] - 2026-11-09

### Security

- **PHP Object Injection**: `Base62x::decode()->get()` called `unserialize()` on the decoded payload without
  restrictions, so decoding untrusted input (e.g. a `$_GET` parameter) could instantiate arbitrary classes and
  trigger their magic methods. Objects are now never instantiated (`allowed_classes => false`), and a payload that
  looks like a serialized object is returned as a plain string.
- `encrypt('0')` (or any key that PHP considers "empty") silently produced an **unencrypted** payload. Any non-empty
  key is now honored, and an empty key throws a `CryptException`.
- Cipher methods without an IV (every ECB mode, `des-ede`, `des-ede3`, `null`...), AEAD modes (GCM, CCM, OCB,
  Poly1305, SIV), key-wrap modes and TLS-only stitched `*-hmac-*` modes are now rejected. Previously only
  `aes-*-ecb` was rejected, and e.g. `des-ecb` or `bf-ecb` were silently accepted.
- IVs are generated with `random_bytes()` instead of `openssl_random_pseudo_bytes()`.
- `Crypt` no longer falls back to `php_uname()` as encryption key when none is given.

### Fixed

- **Binary and UTF-8 payloads were corrupted** since v1.3.0: the `mb_str_functions` php-cs-fixer rule replaced
  byte functions with multibyte ones, so any byte sequence forming a multibyte UTF-8 character lost all but its
  first byte when encoded without compression. All string handling is byte-based again, and the output is the
  same as v1.2.x and the reference implementation.
- Huffman compression works again (it was broken by the same multibyte issue).
- Decoding was **O(n²)**: a ~170 KB payload took ~26 seconds to decode, it now takes a few milliseconds.
- The payload `"0"` (and the integer `0`) was rejected as "empty".
- Malformed input now throws a `DecodeException` instead of emitting PHP warnings, writing to `error_log()`, and
  returning garbage.
- Malformed compression footprints now throw a `DecodeException`.
- The `CompressionException` message contained a stray `\` character.

### Added

- **Authenticated encryption**: `encrypt($key, Base62x::CIPHER_XCHACHA20_POLY1305)` (XChaCha20-Poly1305 via
  ext-sodium, BLAKE2b key derivation, random salt and nonce per payload) and
  `Base62x::CIPHER_XCHACHA20_POLY1305_ARGON2ID` (Argon2id key derivation, for human passwords). Tampering and wrong
  keys are always detected; the cipher is auto-detected on `decrypt()`; legacy OpenSSL payloads keep working.
- `getString()` (raw result, never unserialized), `getArray()` (strictly an array) and `Base62x::isValid()`.
- Compression algorithms `bzip2` (ext-bz2), `zstd` (ext-zstd) and `brotli` (ext-brotli). `compress()` reports a
  missing extension right away.
- `Base62x::encodeStream()` / `Base62x::decodeStream()`: chunked encoding between streams with constant memory usage.
- Laravel integration (auto-discovered): service provider, `Base62x` facade with `encrypt()`/`decrypt()`/`pack()`/
  `unpack()` one-liners, publishable `config/base62x.php`, `Base62xCast` Eloquent cast, `Base62xRule` and the
  `base62x` validation rule.
- Interoperability vectors verified against the PHP, Python and JavaScript reference implementations of Base62x.
- Deterministic fuzz tests, mutation testing with Infection in CI, Dependabot, and automated GitHub releases from
  `CHANGELOG.md` on tag push.
- `DecodeException` and `EncodeException` accept an optional detailed reason.
- `Mfonte\Base62x\Exception\Base62xException` marker interface, implemented by every exception of the library.
- Exceptions accept an optional `$previous` throwable, and wrapped errors keep the original exception.
- GitHub Actions CI on PHP 7.2 → 8.5 (plus lowest dependencies), PHPStan, PHP CS Fixer and code coverage.
- Test suite covering binary safety, backward compatibility of the wire format, security, validation and Huffman.
- `CHANGELOG.md`, `SECURITY.md`, `CONTRIBUTING.md`, `.editorconfig`, `.gitattributes`.

### Changed

- `declare(strict_types=1)` and native parameter/return types where possible, without breaking the public API.
- Classes other than the `Base62x` facade, the Laravel integration and the exceptions are marked `@internal`.
- The low-level encoder was rewritten with native types (output is byte-identical).
- A decoded payload equal to the string `"N;"` is now returned as-is, instead of `null`.
- Arrays can no longer be passed to `Base62x::decode()` (they used to be serialized and decoded as garbage).
- Dev dependencies: dropped the unused `orchestra/testbench` and `php-coveralls/php-coveralls`; PHPUnit `^8.5.21 || ^9.6`.
- `composer.json`: added `homepage`, `support`, `suggest` (`ext-openssl`, `ext-zlib`), `ci`/`cs` scripts.
- The development archive no longer ships tests and tooling files (`.gitattributes` `export-ignore`).

## [1.4.0] - 2023-02-08

- Updated dev dependencies, php-cs-fixer configuration, README.

## [1.3.2] and earlier

See the [Git history](https://github.com/mauriziofonte/php-base62x/commits/master).

[1.5.0]: https://github.com/mauriziofonte/php-base62x/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/mauriziofonte/php-base62x/compare/v1.3.2...v1.4.0
[1.3.2]: https://github.com/mauriziofonte/php-base62x/releases/tag/v1.3.2
