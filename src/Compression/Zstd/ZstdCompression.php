<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Compression\Zstd;

use Mfonte\Base62x\Exception\CompressionException;

/**
 * Zstandard compression, via ext-zstd.
 *
 * @internal This class is not part of the public API: use Base62x::compress('zstd')
 */
final class ZstdCompression
{
    private const LEVEL = 19;

    /**
     * @throws CompressionException
     */
    public static function encode(string $data): string
    {
        self::assertAvailable();

        $encoded = @zstd_compress($data, self::LEVEL);
        if (!\is_string($encoded)) {
            throw new CompressionException('zstd', 'zstd_compress() failed while compressing the input data.');
        }

        return $encoded;
    }

    /**
     * @throws CompressionException
     */
    public static function decode(string $data): string
    {
        self::assertAvailable();

        $decoded = @zstd_uncompress($data);
        if (!\is_string($decoded)) {
            throw new CompressionException('zstd', 'zstd_uncompress() failed while uncompressing the input data.');
        }

        return $decoded;
    }

    /**
     * Whether the PHP extension needed by this algorithm is loaded.
     */
    public static function isAvailable(): bool
    {
        return \function_exists('zstd_compress') && \function_exists('zstd_uncompress');
    }

    /**
     * @throws CompressionException
     */
    private static function assertAvailable(): void
    {
        if (!self::isAvailable()) {
            throw new CompressionException('zstd', 'Cannot use Zstandard as compression algorithm: the zstd extension is not available in your PHP installation.');
        }
    }
}
