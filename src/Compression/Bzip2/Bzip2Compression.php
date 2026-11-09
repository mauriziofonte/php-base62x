<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Compression\Bzip2;

use Mfonte\Base62x\Exception\CompressionException;

/**
 * Bzip2 compression, via ext-bz2.
 *
 * @internal This class is not part of the public API: use Base62x::compress('bzip2')
 */
final class Bzip2Compression
{
    private const LEVEL = 9;

    /**
     * @throws CompressionException
     */
    public static function encode(string $data): string
    {
        self::assertAvailable();

        $encoded = @bzcompress($data, self::LEVEL);
        if (!\is_string($encoded)) {
            throw new CompressionException('bzip2', 'bzcompress() failed while compressing the input data.');
        }

        return $encoded;
    }

    /**
     * @throws CompressionException
     */
    public static function decode(string $data): string
    {
        self::assertAvailable();

        $decoded = @bzdecompress($data);
        if (!\is_string($decoded)) {
            throw new CompressionException('bzip2', 'bzdecompress() failed while uncompressing the input data.');
        }

        return $decoded;
    }

    /**
     * Whether the PHP extension needed by this algorithm is loaded.
     */
    public static function isAvailable(): bool
    {
        return \function_exists('bzcompress') && \function_exists('bzdecompress');
    }

    /**
     * @throws CompressionException
     */
    private static function assertAvailable(): void
    {
        if (!self::isAvailable()) {
            throw new CompressionException('bzip2', 'Cannot use Bzip2 as compression algorithm: the bz2 extension is not available in your PHP installation.');
        }
    }
}
