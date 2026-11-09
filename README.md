# PHP Base62x

[![Latest Stable Version](https://poser.pugx.org/mfonte/base62x/v/stable)](https://packagist.org/packages/mfonte/base62x)
[![Total Downloads](https://poser.pugx.org/mfonte/base62x/downloads)](https://packagist.org/packages/mfonte/base62x)
[![CI](https://github.com/mauriziofonte/php-base62x/actions/workflows/ci.yml/badge.svg)](https://github.com/mauriziofonte/php-base62x/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/packagist/dependency-v/mfonte/base62x/php)](https://packagist.org/packages/mfonte/base62x)
[![License](https://img.shields.io/packagist/l/mfonte/base62x)](LICENSE.txt)

A PHP implementation of **Base62x**, a non-symbolic alternative to Base64: the output only contains `[0-9A-Za-z]`,
so it can be used safely in URLs, file names, HTML attributes, JavaScript strings and any other place where `+`,
`/` and `=` cause trouble.

On top of the encoding, the library can **compress** (Gzip, Huffman, Bzip2, Zstandard, Brotli) and **encrypt** the
payload (authenticated XChaCha20-Poly1305 via libsodium, or any OpenSSL cipher), with a fluent API. It also offers
streaming for big files and a first-class Laravel integration.

The encoding follows the reference implementation by Wade Lau: [wadelau/Base62x](https://github.com/wadelau/Base62x)
([paper](https://ieeexplore.ieee.org/document/6020065)).

## Installation

```bash
composer require mfonte/base62x
```

Requirements:

- PHP **7.2** or newer (tested up to 8.5)
- optional extensions, only for the features that need them:
  `ext-sodium` (authenticated encryption), `ext-openssl` (OpenSSL ciphers), `ext-zlib` (Gzip), `ext-bz2` (Bzip2),
  `ext-zstd` (Zstandard), `ext-brotli` (Brotli)

The output is byte-for-byte compatible with the reference implementations of Base62x in PHP, Python and JavaScript
(see `tests/Unit/fixtures/interop-vectors.json`).

## Usage

```php
use Mfonte\Base62x\Base62x;

$encoded = Base62x::encode('this is some string that needs to be encoded')->get();
$decoded = Base62x::decode($encoded)->get();
```

The encoder is **binary safe**: strings with any byte (binary files, UTF-8 text...) survive the round-trip unchanged.

```php
$image = file_get_contents('a_nice_image.jpg');
$encoded = Base62x::encode($image)->get();

// ... on another machine
file_put_contents('a_nice_image_clone.jpg', Base62x::decode($encoded)->get());
```

### Supported payloads

| Payload                    | Behavior                                                       |
|----------------------------|----------------------------------------------------------------|
| `string`                   | Encoded as-is (binary safe)                                    |
| `int`, `float`, `bool`     | Cast to `string`                                               |
| `array`                    | Serialized on encode, and returned as an `array` on decode    |
| `null`, `''`, `[]`         | Rejected with an `InvalidParam` exception                      |
| objects, resources         | Rejected with an `InvalidParam` exception                      |

> Objects nested inside arrays are **never** instantiated on decode (they come back as `__PHP_Incomplete_Class`),
> to protect you from PHP Object Injection when decoding untrusted input.

### Getting the result

| Method                     | Returns                                                                        |
|----------------------------|--------------------------------------------------------------------------------|
| `get()`                    | the encoded string, or the decoded payload (arrays are restored automatically) |
| `getString()`              | the encoded string, or the decoded payload as a raw string, never unserialized |
| `getArray()`               | the decoded payload as an array, or a `DecodeException` if it is not an array  |
| `Base62x::isValid($input)` | whether `$input` is a well-formed Base62x payload (no exception thrown)        |

```php
$encoded = Base62x::encode(['foo' => 'bar'])->get();

Base62x::decode($encoded)->getArray();  // ['foo' => 'bar']
Base62x::decode($encoded)->getString(); // 'a:1:{s:3:"foo";s:3:"bar";}'

if (!Base62x::isValid($_GET['token'] ?? null)) {
    // reject the request
}
```

`isValid()` checks the alphabet, the structure and the compression, but does not decrypt: use
`decode()->decrypt()` to verify the authenticity of an encrypted payload.

### Compression

```php
$payload = ['foo' => 'bar', 'barfoo' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]];

$gzip    = Base62x::encode($payload)->compress()->get();                   // same as compress('gzip', 'gzip')
$zlib    = Base62x::encode($payload)->compress('gzip', 'zlib')->get();
$deflate = Base62x::encode($payload)->compress('gzip', 'deflate')->get();
$huffman = Base62x::encode($payload)->compress('huffman')->get();
$bzip2   = Base62x::encode($payload)->compress('bzip2')->get();    // needs ext-bz2
$zstd    = Base62x::encode($payload)->compress('zstd')->get();     // needs ext-zstd
$brotli  = Base62x::encode($payload)->compress('brotli')->get();   // needs ext-brotli

// no need to tell decode() which compression was used: it is auto-detected
$decoded = Base62x::decode($gzip)->get(); // the original array
```

Available methods:

| Algorithm   | Encodings                          | Requires     |
|-------------|------------------------------------|--------------|
| `gzip`      | `gzip` (default), `zlib`, `deflate`| `ext-zlib`   |
| `huffman`   | -                                  | nothing (pure PHP) |
| `bzip2`     | -                                  | `ext-bz2`    |
| `zstd`      | -                                  | `ext-zstd`   |
| `brotli`    | -                                  | `ext-brotli` |

`compress()` throws a `CompressionException` right away if the needed extension is missing.

The compression algorithm is stored in a small header at the beginning of the encoded payload, so the receiving side
does not need to know it. Compression only pays off with reasonably large and redundant payloads.

### Authenticated encryption (recommended)

```php
$key = config('app.key'); // a long, random key

$encrypted = Base62x::encode($payload)->encrypt($key, Base62x::CIPHER_XCHACHA20_POLY1305)->compress()->get();

// the cipher is detected automatically on decrypt
$decrypted = Base62x::decode($encrypted)->decrypt($key)->get();
```

`Base62x::CIPHER_XCHACHA20_POLY1305` uses XChaCha20-Poly1305 (libsodium) with a fresh random salt and nonce for every
payload: a wrong key or a single tampered character always makes `decrypt()` throw a `CryptException`, and decoding an
encrypted payload without calling `decrypt()` throws too.

The key is derived with keyed BLAKE2b, which is fast and meant for high-entropy keys. If the key is a **human password**,
use `Base62x::CIPHER_XCHACHA20_POLY1305_ARGON2ID` instead: the key is then derived with Argon2id, which is slow on purpose
(about 64 MiB of memory per call) to resist brute force.

### OpenSSL encryption

```php
$key = 'a-long-random-secret-key';

$encrypted = Base62x::encode('my_payload')->encrypt($key)->get();
$encryptedAndCompressed = Base62x::encode('my_payload')->encrypt($key)->compress()->get();

// to decrypt, you must pass the same key (and the same cipher method, if not the default)
$decrypted = Base62x::decode($encrypted)->decrypt($key)->get();

// any cipher method supported by your OpenSSL installation can be used
$encrypted = Base62x::encode('my_payload')->encrypt($key, 'aes-256-cbc')->get();
$decrypted = Base62x::decode($encrypted)->decrypt($key, 'aes-256-cbc')->get();
```

The default cipher method is `aes-128-ctr`. Methods without an IV (ECB), AEAD methods (GCM, CCM, OCB, Poly1305, SIV),
key-wrap methods and TLS-only `*-hmac-*` methods are rejected with a `CryptException`.

The payload is always encrypted first and compressed afterwards, whatever the order of the calls in the chain.

> **Security note**: OpenSSL ciphers are kept for backward compatibility. They do not authenticate the cipher text, and
> do not use a password-based key derivation function: prefer authenticated encryption for new code, and read
> [SECURITY.md](SECURITY.md).

### Streams

Big files can be encoded and decoded chunk by chunk, with constant memory usage:

```php
$in = fopen('backup.tar', 'rb');
$out = fopen('backup.tar.b62x', 'wb');
Base62x::encodeStream($in, $out);

$in = fopen('backup.tar.b62x', 'rb');
$out = fopen('backup-restored.tar', 'wb');
Base62x::decodeStream($in, $out);
```

Streams always use the "binary" flavor of Base62x: the output of `encodeStream()` can also be decoded with `decode()`,
but strings produced by `encode()` for ASCII payloads (ending with `x`) can only be decoded with `decode()`.
Compression and encryption are not available for streams.

## Laravel

The service provider and the `Base62x` facade alias are auto-discovered. Optionally publish the configuration:

```bash
php artisan vendor:publish --tag=base62x-config
```

| Key                   | Env                   | Default                         |
|-----------------------|-----------------------|---------------------------------|
| `base62x.key`         | `BASE62X_KEY`         | `APP_KEY`                       |
| `base62x.cipher`      | `BASE62X_CIPHER`      | `xchacha20-poly1305`            |
| `base62x.compression` | `BASE62X_COMPRESSION` | `gzip` (empty to disable)       |

```php
use Mfonte\Base62x\Laravel\Facades\Base62x;

$token = Base62x::encrypt(['user' => 42]);   // compress + encrypt with the configured key, cipher and compression
$data  = Base62x::decrypt($token);           // ['user' => 42]

$packed = Base62x::pack($bigArray);          // compress only
$data   = Base62x::unpack($packed);

Base62x::encode('foo')->get();               // the fluent API is available too
```

Eloquent cast:

```php
use Mfonte\Base62x\Laravel\Casts\Base62xCast;

protected $casts = [
    'token'   => Base62xCast::class,               // plain Base62x
    'context' => Base62xCast::class.':compressed', // + configured compression
    'secret'  => Base62xCast::class.':encrypted',  // + configured compression and encryption
];
```

Validation:

```php
use Mfonte\Base62x\Laravel\Rules\Base62xRule;

$request->validate(['token' => ['required', new Base62xRule()]]);
$request->validate(['token' => 'required|base62x']);
```

### Real use case: passing context data to an Ajax endpoint

```php
// render the page
$context = ['route' => 'homepage', 'pagination' => ['currPage' => 1, 'maxPages' => 2]];
$encryptedContext = Base62x::encode($context)
    ->encrypt(config('app.key'), Base62x::CIPHER_XCHACHA20_POLY1305)
    ->compress()
    ->get();

echo <<<HTML
<script>
const ajaxConfig = { url: 'https://www.example.com/ajaxRequest', context: '{$encryptedContext}' };
</script>
HTML;
```

```php
// in the Ajax endpoint
use Mfonte\Base62x\Base62x;
use Mfonte\Base62x\Exception\Base62xException;

try {
    $context = Base62x::decode($_POST['context'] ?? '')->decrypt(config('app.key'))->getArray();
} catch (Base62xException $e) {
    $context = null;
}

if ($context === null) {
    http_response_code(400);
    exit;
}

// proceed with your logic
```

### Error handling

Every exception thrown by the library implements `Mfonte\Base62x\Exception\Base62xException`:

| Exception              | Thrown when                                                         |
|------------------------|---------------------------------------------------------------------|
| `InvalidParam`         | an invalid payload, mode, stream, compression algorithm or encoding is given |
| `EncodeException`      | the encoding fails                                                  |
| `DecodeException`      | the input is not a valid Base62x string, or its header is corrupted |
| `CompressionException` | the compression or decompression fails                              |
| `CryptException`       | the key or cipher method is invalid, encryption/decryption fails, or an authenticated payload was tampered with |

## Development

```bash
composer install
composer test      # PHPUnit
composer analyse   # PHPStan
composer cs        # coding style check (composer fix to apply)
composer ci        # everything
infection          # mutation testing (https://infection.github.io), also run in CI
```

See [CONTRIBUTING.md](CONTRIBUTING.md) and [CHANGELOG.md](CHANGELOG.md).

## Credits

A big thank you goes to Wade Lau ([wadelau](https://github.com/wadelau), ufqi.com) for designing the Base62x encoding.

## License

Apache License 2.0. See [LICENSE.txt](LICENSE.txt).
