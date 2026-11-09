<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Compression\Gzip;

use Mfonte\Base62x\Exception\CompressionException;

/**
 * Gzip compression, with "gzip" (RFC 1952), "zlib" (RFC 1950) or "deflate" (RFC 1951) encoding.
 *
 * @internal This class is not part of the public API: use Base62x::compress()
 */
class GzipCompression
{
    private const LEVEL = 9;

    /**
     * Compresses the data. The compressed output is base64 encoded.
     *
     * NOTE: the base64 step is not strictly needed (the Base62x encoder is binary safe),
     * but it is part of the wire format and kept for backward compatibility.
     *
     * @param string|null $encoding One of "gzip" (default), "zlib" or "deflate"
     *
     * @throws CompressionException
     */
    public static function encode(string $data, ?string $encoding = null): string
    {
        self::assertAvailable();

        switch ($encoding) {
            case 'zlib':
                $encoded = @gzcompress($data, self::LEVEL);
                break;
            case 'deflate':
                $encoded = @gzdeflate($data, self::LEVEL);
                break;
            case 'gzip':
            case null:
                $encoded = @gzencode($data, self::LEVEL);
                break;
            default:
                throw new CompressionException('gzip', 'Unsupported encoding "'.$encoding.'".');
        }

        if ($encoded === false) {
            throw new CompressionException('gzip', 'The gz compression function returned a false state while compressing the input data.');
        }

        return base64_encode($encoded);
    }

    /**
     * Decompresses data that was compressed with self::encode().
     *
     * @param string|null $encoding One of "gzip" (default), "zlib" or "deflate"
     *
     * @throws CompressionException
     */
    public static function decode(string $data, ?string $encoding = null): string
    {
        self::assertAvailable();

        // the data comes encoded in base64: see above
        $data = base64_decode($data, true);
        if ($data === false) {
            throw new CompressionException('gzip', 'The compressed data is not valid base64.');
        }

        switch ($encoding) {
            case 'zlib':
                $fn = 'gzuncompress';
                $decoded = @gzuncompress($data);
                break;
            case 'deflate':
                $fn = 'gzinflate';
                $decoded = @gzinflate($data);
                break;
            case 'gzip':
            case null:
                $fn = 'gzdecode';
                $decoded = @gzdecode($data);
                break;
            default:
                throw new CompressionException('gzip', 'Unsupported encoding "'.$encoding.'".');
        }

        if ($decoded === false) {
            throw new CompressionException('gzip', "The {$fn}() function returned a false state while uncompressing the input data.");
        }

        return $decoded;
    }

    /**
     * @throws CompressionException
     */
    private static function assertAvailable(): void
    {
        if (!\extension_loaded('zlib')) {
            throw new CompressionException('gzip', 'Cannot use Gzip as compression algorithm: the current PHP installation does not support the zlib extension.');
        }
    }
}
