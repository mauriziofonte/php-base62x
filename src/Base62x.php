<?php

declare(strict_types=1);

namespace Mfonte\Base62x;

use Mfonte\Base62x\Stream\StreamCodec;
use Mfonte\Base62x\Exception\InvalidParam;
use Mfonte\Base62x\Exception\CryptException;
use Mfonte\Base62x\Exception\DecodeException;
use Mfonte\Base62x\Exception\EncodeException;
use Mfonte\Base62x\Exception\Base62xException;
use Mfonte\Base62x\Encoding\Base62x as Encoder;
use Mfonte\Base62x\Encryption\Crypt as Crypter;
use Mfonte\Base62x\Encryption\AuthenticatedCrypt;
use Mfonte\Base62x\Exception\CompressionException;
use Mfonte\Base62x\Compression\Zstd\ZstdCompression;
use Mfonte\Base62x\Compression\Bzip2\Bzip2Compression;
use Mfonte\Base62x\Compression\Brotli\BrotliCompression;
use Mfonte\Base62x\Compression\Gzip\GzipCompression as GzipCompressor;
use Mfonte\Base62x\Compression\Huffman\HuffmanCoding as HuffmanCompressor;

/**
 * Fluent facade around the Base62x encoder, with optional compression and encryption.
 *
 * Encoding pipeline:  payload -> [encrypt] -> [compress] -> base62x
 * Decoding pipeline:  base62x -> [uncompress, auto-detected] -> [decrypt] -> payload
 *
 * @example Base62x::encode('foo')->compress()->encrypt('secret')->get();
 * @example Base62x::decode($encoded)->decrypt('secret')->get();
 */
class Base62x
{
    public const MODE_ENCODE = 1;
    public const MODE_DECODE = 2;

    /**
     * Authenticated encryption (XChaCha20-Poly1305) with a fast BLAKE2b key derivation: use it with high-entropy keys.
     */
    public const CIPHER_XCHACHA20_POLY1305 = 'xchacha20-poly1305';

    /**
     * Authenticated encryption (XChaCha20-Poly1305) with an Argon2id key derivation: use it with human passwords.
     */
    public const CIPHER_XCHACHA20_POLY1305_ARGON2ID = 'xchacha20-poly1305-argon2id';

    /**
     * Prefix of the "magic string" prepended to compressed payloads, used to auto-detect
     * the compression algorithm and encoding on decode.
     */
    private const COMPRESSION_FOOTPRINT_PREFIX = '[MFB62X.COMPRESS.';

    /**
     * Supported compression algorithms, mapped to the list of supported encodings.
     * An empty list means that the algorithm does not support any encoding.
     *
     * @var array<string, list<string>>
     */
    protected $_validCompressionAlgorithms = [
        'gzip' => ['zlib', 'deflate', 'gzip'],
        'huffman' => [],
        'bzip2' => [],
        'zstd' => [],
        'brotli' => [],
    ];

    /**
     * The mode: encode or decode.
     *
     * @var int
     */
    protected $mode;

    /**
     * The payload to be encoded or decoded, normalized to a string.
     *
     * @var string
     */
    protected $payload;

    /**
     * The compression algorithm to apply prior of encoding, if any.
     *
     * @var string|null
     */
    protected $compressAlgorithm;

    /**
     * The compression encoding related to the compression algorithm, if any.
     *
     * @var string|null
     */
    protected $compressEncoding;

    /**
     * The encryption cypher (algorithm) to be used in case of password-protected encoding.
     * This variable *must* be a valid cypher method supported in openssl_get_cipher_methods().
     *
     * @var string
     */
    protected $cryptCypher = 'aes-128-ctr';

    /**
     * The encrypt/decrypt key (password) to be used to protect/unprotect the encoding.
     * When null, no encryption/decryption takes place.
     *
     * @var string|null
     */
    protected $cryptKey;

    /**
     * Kept for backward compatibility: decompression is auto-detected from the payload.
     *
     * @var string|null
     */
    protected $decompressAlgorithm;

    /**
     * Kept for backward compatibility: decompression is auto-detected from the payload.
     *
     * @var string|null
     */
    protected $decompressEncoding;

    /**
     * Starts an encoding chain.
     *
     * @param mixed $payload A string (binary safe), a scalar, or an array (that will be serialized): anything else is rejected
     *
     * @throws InvalidParam if the payload is empty or of an unsupported type
     */
    public static function encode($payload): self
    {
        return new self(self::MODE_ENCODE, $payload);
    }

    /**
     * Starts a decoding chain.
     *
     * @param mixed $payload A Base62x encoded string: anything else than a string or a scalar is rejected
     *
     * @throws InvalidParam if the payload is empty or of an unsupported type
     */
    public static function decode($payload): self
    {
        return new self(self::MODE_DECODE, $payload);
    }

    /**
     * @param int   $mode    One of self::MODE_ENCODE or self::MODE_DECODE
     * @param mixed $payload The payload to be encoded or decoded
     *
     * @throws InvalidParam if the mode is not valid, or the payload is empty or of an unsupported type
     */
    public function __construct($mode, $payload)
    {
        if ($mode !== self::MODE_ENCODE && $mode !== self::MODE_DECODE) {
            throw new InvalidParam('mode', __FUNCTION__, __CLASS__, 'The mode must be either Base62x::MODE_ENCODE or Base62x::MODE_DECODE');
        }

        if ($payload === null || $payload === '' || $payload === []) {
            throw new InvalidParam('payload', __FUNCTION__, __CLASS__, 'The payload cannot be empty');
        }
        if (\is_resource($payload)) {
            throw new InvalidParam('payload', __FUNCTION__, __CLASS__, 'The payload cannot be a resource');
        }
        if (\is_object($payload)) {
            throw new InvalidParam('payload', __FUNCTION__, __CLASS__, 'The payload cannot be an object');
        }
        if (\is_array($payload)) {
            if ($mode === self::MODE_DECODE) {
                throw new InvalidParam('payload', __FUNCTION__, __CLASS__, 'The payload to be decoded must be a string');
            }

            // if the payload is an array, perform here and now the translation to a serialized string
            $payload = serialize($payload);
        }

        $this->mode = $mode;
        $this->payload = (string) $payload;
    }

    /**
     * Sets the compression type and encoding.
     *
     * @param string      $algo     A valid compression algorithm: "gzip", "huffman", "bzip2", "zstd" or "brotli"
     * @param string|null $encoding A valid compression encoding for the algorithm: "gzip", "zlib" or "deflate" for Gzip, ignored otherwise
     *
     * @throws InvalidParam         if the algorithm or the encoding is not supported
     * @throws CompressionException if the PHP extension needed by the algorithm is not available
     */
    public function compress($algo = 'gzip', $encoding = 'gzip'): self
    {
        if (!\is_string($algo) || !\array_key_exists($algo, $this->_validCompressionAlgorithms)) {
            throw new InvalidParam('algo', __FUNCTION__, __CLASS__, 'Supported algorithms are: '.implode(', ', array_keys($this->_validCompressionAlgorithms)));
        }

        $validEncodings = $this->_validCompressionAlgorithms[$algo];
        if ($validEncodings === []) {
            // the algorithm does not support any encoding
            $encoding = null;
        } elseif (!\in_array($encoding, $validEncodings, true)) {
            throw new InvalidParam('encoding', __FUNCTION__, __CLASS__, 'Supported encodings for "'.$algo.'" are: '.implode(', ', $validEncodings));
        }

        $available = [
            'bzip2' => [Bzip2Compression::class, 'bz2'],
            'zstd' => [ZstdCompression::class, 'zstd'],
            'brotli' => [BrotliCompression::class, 'brotli'],
        ];
        if (isset($available[$algo]) && !$available[$algo][0]::isAvailable()) {
            throw new CompressionException($algo, 'the '.$available[$algo][1].' extension is not available in your PHP installation');
        }

        $this->compressAlgorithm = $algo;
        $this->compressEncoding = $encoding;

        return $this;
    }

    /**
     * As the decompression is done automagically via the "magic string" at the beginning of the
     * encoded payload, this method is a no-op. It is present only for API symmetry.
     */
    public function decompress(): self
    {
        return $this;
    }

    /**
     * Sets the encryption key (password) and cypher method.
     * This method is only available if the openssl extension is available in your PHP installation.
     *
     * Pass Base62x::CIPHER_XCHACHA20_POLY1305 (or Base62x::CIPHER_XCHACHA20_POLY1305_ARGON2ID for human passwords)
     * to use authenticated encryption via ext-sodium: tampered payloads and wrong keys are then always detected.
     *
     * OpenSSL cypher methods without an IV (e.g. ECB), OpenSSL AEAD methods (GCM, CCM, OCB, Poly1305, SIV) and
     * key-wrap methods are rejected.
     *
     * @param string $key    A password for your encoded base62x output string
     * @param string $cypher A valid openssl cypher method as supported in your environment (openssl_get_cipher_methods)
     *
     * @throws CryptException if openssl is not available, the key is empty, or the cypher is unsupported
     */
    public function encrypt(string $key, string $cypher = 'aes-128-ctr'): self
    {
        if ($key === '') {
            throw new CryptException('the encryption key cannot be empty');
        }

        $normalized = strtolower($cypher);
        if ($normalized === self::CIPHER_XCHACHA20_POLY1305 || $normalized === self::CIPHER_XCHACHA20_POLY1305_ARGON2ID) {
            AuthenticatedCrypt::assertAvailable();
            $this->cryptCypher = $normalized;
        } else {
            if (!\extension_loaded('openssl')) {
                throw new CryptException('the openssl extension is not available in your PHP installation');
            }
            $this->cryptCypher = Crypter::assertSupportedMethod($cypher);
        }

        $this->cryptKey = $key;

        return $this;
    }

    /**
     * Sets the decryption key (password) and cypher method.
     * Payloads encrypted with authenticated encryption are detected automatically: $method is then ignored.
     *
     * @see self::encrypt()
     *
     * @throws CryptException if openssl is not available, the key is empty, or the cypher is unsupported
     */
    public function decrypt(string $key, string $method = 'aes-128-ctr'): self
    {
        return $this->encrypt($key, $method);
    }

    /**
     * Gets the encoded or decoded payload.
     *
     * When decoding, a payload that was originally an array is returned as an array.
     *
     * @throws EncodeException|DecodeException|CompressionException|CryptException
     *
     * @return string|array<mixed>|mixed
     */
    public function get()
    {
        if ($this->mode === self::MODE_ENCODE) {
            return $this->_encode($this->payload);
        }

        $decoded = $this->_decode($this->payload);
        $unserialized = $this->_unserialize($decoded);

        return $unserialized !== null ? $unserialized : $decoded;
    }

    /**
     * Gets the encoded string, or the decoded payload as a raw string, without the automatic unserialization of get().
     *
     * @throws EncodeException|DecodeException|CompressionException|CryptException
     */
    public function getString(): string
    {
        return $this->mode === self::MODE_ENCODE ? $this->_encode($this->payload) : $this->_decode($this->payload);
    }

    /**
     * Gets the decoded payload, that must have been an array when it was encoded.
     *
     * @throws InvalidParam                        if called while encoding
     * @throws DecodeException                     if the decoded payload is not an array
     * @throws CompressionException|CryptException
     *
     * @return array<mixed>
     */
    public function getArray(): array
    {
        if ($this->mode !== self::MODE_DECODE) {
            throw new InvalidParam('mode', __FUNCTION__, __CLASS__, 'getArray() is only available when decoding');
        }

        $unserialized = $this->_unserialize($this->_decode($this->payload));
        if (!\is_array($unserialized)) {
            throw new DecodeException(null, 'the decoded payload is not an array');
        }

        return $unserialized;
    }

    /**
     * Whether the given string is a well-formed Base62x payload: valid alphabet and structure, and valid compression,
     * if any. Encrypted payloads are not decrypted: use decode()->decrypt() to check their authenticity.
     *
     * @param mixed $encoded
     */
    public static function isValid($encoded): bool
    {
        if (!\is_string($encoded) || $encoded === '') {
            return false;
        }

        try {
            (new self(self::MODE_DECODE, $encoded))->_decode($encoded, false);

            return true;
        } catch (Base62xException $e) {
            return false;
        }
    }

    /**
     * Encodes a stream (e.g. a big file) to another stream, chunk by chunk, with constant memory usage.
     * The output can be decoded with decodeStream(), or with decode() if it fits in memory.
     * Compression and encryption are not available for streams.
     *
     * @param resource $input  A readable stream
     * @param resource $output A writable stream
     *
     * @throws InvalidParam|EncodeException
     *
     * @return int The number of bytes written to $output
     */
    public static function encodeStream($input, $output, int $chunkSize = StreamCodec::DEFAULT_CHUNK_SIZE): int
    {
        return StreamCodec::encode($input, $output, $chunkSize);
    }

    /**
     * Decodes a stream produced by encodeStream() to another stream, chunk by chunk, with constant memory usage.
     *
     * @param resource $input  A readable stream
     * @param resource $output A writable stream
     *
     * @throws InvalidParam|DecodeException
     *
     * @return int The number of bytes written to $output
     */
    public static function decodeStream($input, $output, int $chunkSize = StreamCodec::DEFAULT_CHUNK_SIZE): int
    {
        return StreamCodec::decode($input, $output, $chunkSize);
    }

    /**
     * Performs the actual Base62x encoding.
     */
    private function _encode(string $payload): string
    {
        if ($this->cryptKey !== null) {
            $payload = $this->_performEncryption($payload);
        }
        if ($this->compressAlgorithm !== null) {
            $payload = $this->_performCompress($payload);
        }

        $encoded = Encoder::encode($payload);
        if (!\is_string($encoded) || $encoded === '') {
            throw new EncodeException();
        }

        return $encoded;
    }

    /**
     * Performs the actual Base62x decoding.
     */
    private function _decode(string $payload, bool $decrypt = true): string
    {
        // Base62x output only contains alphanumeric characters: anything else is not a valid input
        if (preg_match('/^[0-9A-Za-z]+$/', $payload) !== 1) {
            throw new DecodeException();
        }

        $decoded = Encoder::decode($payload);
        if (!\is_string($decoded) || $decoded === '') {
            throw new DecodeException();
        }

        // remove the magic string for Compression
        $data = $this->_getCompressionFootprintAndSanitizePayload($decoded);

        if ($data['compression_algo'] !== null) {
            $decoded = $this->_performUncompress($data['payload'], $data['compression_algo'], $data['compression_encoding']);
        }

        if (!$decrypt) {
            return $decoded;
        }

        // authenticated encryption is detected automatically
        if (AuthenticatedCrypt::isEncrypted($decoded)) {
            if ($this->cryptKey === null) {
                throw new CryptException('the payload is encrypted: call decrypt() with the encryption key');
            }

            return AuthenticatedCrypt::decrypt($decoded, $this->cryptKey);
        }

        // eventually perform (legacy) decryption
        if ($this->cryptKey !== null) {
            $decoded = $this->_performDecryption($decoded);
        }

        return $decoded;
    }

    /**
     * Performs the actual compress before chaining it into the Base62x encoder.
     */
    private function _performCompress(string $payload): string
    {
        switch ($this->compressAlgorithm) {
            case 'gzip':
                $compressed = GzipCompressor::encode($payload, $this->compressEncoding);
                break;
            case 'huffman':
                $compressed = HuffmanCompressor::encode($payload, HuffmanCompressor::createCodeTree($payload));
                break;
            case 'bzip2':
                $compressed = Bzip2Compression::encode($payload);
                break;
            case 'zstd':
                $compressed = ZstdCompression::encode($payload);
                break;
            case 'brotli':
                $compressed = BrotliCompression::encode($payload);
                break;
            default:
                throw new EncodeException();
        }

        // create the compression footprint, so that Base62x::decode() can auto-detect the compression
        return $this->_createCompressionFootprint().$compressed;
    }

    /**
     * Decompresses the payload, that was prior compressed using one of the available compression types.
     */
    private function _performUncompress(string $compressedPayload, string $compressionAlgo, ?string $compressionEncoding): string
    {
        switch ($compressionAlgo) {
            case 'gzip':
                return GzipCompressor::decode($compressedPayload, $compressionEncoding);
            case 'huffman':
                return HuffmanCompressor::decode($compressedPayload);
            case 'bzip2':
                return Bzip2Compression::decode($compressedPayload);
            case 'zstd':
                return ZstdCompression::decode($compressedPayload);
            case 'brotli':
                return BrotliCompression::decode($compressedPayload);
            default:
                throw new DecodeException();
        }
    }

    /**
     * Performs the actual encryption before chaining it into the Base62x encoder.
     */
    private function _performEncryption(string $payload): string
    {
        if ($this->cryptCypher === self::CIPHER_XCHACHA20_POLY1305 || $this->cryptCypher === self::CIPHER_XCHACHA20_POLY1305_ARGON2ID) {
            return AuthenticatedCrypt::encrypt($payload, (string) $this->cryptKey, $this->cryptCypher === self::CIPHER_XCHACHA20_POLY1305_ARGON2ID);
        }

        try {
            $crypt = new Crypter([
                'key' => $this->cryptKey,
                'method' => $this->cryptCypher,
            ]);

            return $crypt->cipher($payload)->encrypt();
        } catch (CryptException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw new CryptException('Cannot encrypt the payload: '.$ex->getMessage(), $ex);
        }
    }

    /**
     * Decrypts the payload, that was prior encrypted using the on-board encrypter.
     */
    private function _performDecryption(string $payload): string
    {
        try {
            $crypt = new Crypter([
                'key' => $this->cryptKey,
                'method' => $this->cryptCypher,
            ]);

            $decrypted = $crypt->cipher($payload)->decrypt();
        } catch (CryptException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw new CryptException('Cannot decrypt the payload: '.$ex->getMessage(), $ex);
        }

        if ($decrypted === false) {
            throw new CryptException('Cannot decrypt the payload: result from cipher()->decrypt() is false');
        }

        return $decrypted;
    }

    /**
     * Prepares a "magic string" that will be prepended to the compressed payload,
     * prior of chaining it into the Base62x encoder.
     * Doing so, the decode method can understand which compression algo+encoding was originally used.
     */
    private function _createCompressionFootprint(): string
    {
        return self::COMPRESSION_FOOTPRINT_PREFIX.base64_encode(implode(',', [$this->compressAlgorithm, $this->compressEncoding])).']';
    }

    /**
     * Gets the decoded Base62x string, and checks if it needs decompression,
     * by analyzing its "compression footprint" placed at the very beginning of the payload.
     *
     * @return array{payload: string, compression_algo: string|null, compression_encoding: string|null}
     */
    private function _getCompressionFootprintAndSanitizePayload(string $payload): array
    {
        $compressionAlgo = $compressionEncoding = null;

        if (strpos($payload, self::COMPRESSION_FOOTPRINT_PREFIX) === 0) {
            $prefixLength = \strlen(self::COMPRESSION_FOOTPRINT_PREFIX);
            $posEnd = strpos($payload, ']', $prefixLength);
            if ($posEnd === false) {
                throw new DecodeException();
            }

            $compressionParams = base64_decode(substr($payload, $prefixLength, $posEnd - $prefixLength), true);
            $compressionParams = \is_string($compressionParams) ? explode(',', $compressionParams) : [];
            if (\count($compressionParams) !== 2) {
                throw new DecodeException();
            }

            [$compressionAlgo, $compressionEncoding] = $compressionParams;
            $compressionEncoding = $compressionEncoding !== '' ? $compressionEncoding : null;

            // some sanity checks to avoid tampering with the payload and cause bad behaviour or worse
            if (!\array_key_exists($compressionAlgo, $this->_validCompressionAlgorithms)) {
                throw new DecodeException();
            }
            $validEncodings = $this->_validCompressionAlgorithms[$compressionAlgo];
            if ($validEncodings === []) {
                $compressionEncoding = null;
            } elseif ($compressionEncoding !== null && !\in_array($compressionEncoding, $validEncodings, true)) {
                throw new DecodeException();
            }

            // clean the payload, removing the compression footprint
            $payload = (string) substr($payload, $posEnd + 1);
        }

        return [
            'payload' => $payload,
            'compression_algo' => $compressionAlgo,
            'compression_encoding' => $compressionEncoding,
        ];
    }

    /**
     * Unserializes the decoded payload, if it is a serialized value. Returns null otherwise.
     * Objects are NEVER instantiated, to prevent PHP Object Injection with untrusted input.
     *
     * @return mixed
     */
    private function _unserialize(string $decoded)
    {
        if (!$this->_isSerializedString($decoded)) {
            return null;
        }

        $unserialized = @unserialize($decoded, ['allowed_classes' => false]);

        return $unserialized !== false ? $unserialized : null;
    }

    /**
     * Checks whether the $data argument is a serialized string, i.e. a value serialized with native PHP's serialize().
     * Serialized objects are deliberately NOT recognized.
     *
     * @param mixed $data   (should always be a string)
     * @param bool  $strict Whether to perform a strict analysis or not
     */
    private function _isSerializedString($data, bool $strict = true): bool
    {
        // If it isn't a string, it isn't serialized.
        if (!\is_string($data)) {
            return false;
        }
        $data = trim($data);
        if ($data === 'N;') {
            return true;
        }
        if (\strlen($data) < 4) {
            return false;
        }
        if ($data[1] !== ':') {
            return false;
        }
        if ($strict) {
            $lastc = substr($data, -1);
            if ($lastc !== ';' && $lastc !== '}') {
                return false;
            }
        } else {
            $semicolon = strpos($data, ';');
            $brace = strpos($data, '}');
            // Either ; or } must exist.
            if ($semicolon === false && $brace === false) {
                return false;
            }
            // But neither must be in the first X characters.
            if ($semicolon !== false && $semicolon < 3) {
                return false;
            }
            if ($brace !== false && $brace < 4) {
                return false;
            }
        }
        $token = $data[0];
        switch ($token) {
            case 's':
                if ($strict) {
                    if (substr($data, -2, 1) !== '"') {
                        return false;
                    }
                } elseif (strpos($data, '"') === false) {
                    return false;
                }
                // Or else fall through.
                // no break
            case 'a':
                return preg_match("/^{$token}:[0-9]+:/s", $data) === 1;
            case 'b':
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';

                return preg_match("/^{$token}:[0-9.E+-]+;{$end}/", $data) === 1;
        }

        return false;
    }
}
