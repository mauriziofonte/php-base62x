<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Compression\Brotli;

use Mfonte\Base62x\Exception\CompressionException;

/**
 * Brotli compression, via ext-brotli.
 *
 * @internal This class is not part of the public API: use Base62x::compress('brotli')
 */
final class BrotliCompression
{
    private const LEVEL = 11;

    /**
     * @throws CompressionException
     */
    public static function encode(string $data): string
    {
        self::assertAvailable();

        $encoded = @brotli_compress($data, self::LEVEL);
        if (!\is_string($encoded)) {
            throw new CompressionException('brotli', 'brotli_compress() failed while compressing the input data.');
        }

        return $encoded;
    }

    /**
     * @throws CompressionException
     */
    public static function decode(string $data): string
    {
        self::assertAvailable();

        $decoded = @brotli_uncompress($data);
        if (!\is_string($decoded)) {
            throw new CompressionException('brotli', 'brotli_uncompress() failed while uncompressing the input data.');
        }

        return $decoded;
    }

    /**
     * Whether the PHP extension needed by this algorithm is loaded.
     */
    public static function isAvailable(): bool
    {
        return \function_exists('brotli_compress') && \function_exists('brotli_uncompress');
    }

    /**
     * @throws CompressionException
     */
    private static function assertAvailable(): void
    {
        if (!self::isAvailable()) {
            throw new CompressionException('brotli', 'Cannot use Brotli as compression algorithm: the brotli extension is not available in your PHP installation.');
        }
    }
}
