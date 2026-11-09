<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Stream;

use Mfonte\Base62x\Exception\InvalidParam;
use Mfonte\Base62x\Exception\DecodeException;
use Mfonte\Base62x\Exception\EncodeException;
use Mfonte\Base62x\Encoding\Base62x as Encoder;

/**
 * Chunked Base62x encoding/decoding between streams, with constant memory usage.
 *
 * Streams are always encoded with the "binary" flavor of Base62x (groups of 3 bytes -> 4 symbols), because the
 * "ASCII" flavor can only be chosen after reading the whole input. The output is a regular Base62x string.
 *
 * @internal This class is not part of the public API: use Base62x::encodeStream() / Base62x::decodeStream()
 */
final class StreamCodec
{
    public const DEFAULT_CHUNK_SIZE = 3 * 8192;

    /**
     * @param mixed $input
     * @param mixed $output
     *
     * @throws InvalidParam|EncodeException
     */
    public static function encode($input, $output, int $chunkSize = self::DEFAULT_CHUNK_SIZE): int
    {
        self::assertStreams($input, $output, $chunkSize, __FUNCTION__);
        \assert(\is_resource($input) && \is_resource($output));

        // groups of 3 bytes must never be split across chunks
        $chunkSize = max(3, $chunkSize - ($chunkSize % 3));
        $written = 0;
        $buffer = '';
        while (!feof($input)) {
            $data = fread($input, $chunkSize);
            if ($data === false) {
                throw new EncodeException(null, 'cannot read from the input stream');
            }
            $buffer .= $data;

            $usable = \strlen($buffer) - (\strlen($buffer) % 3);
            if ($usable > 0) {
                $written += self::write($output, Encoder::encodeBinary(substr($buffer, 0, $usable)));
                $buffer = (string) substr($buffer, $usable);
            }
        }
        if ($buffer !== '') {
            $written += self::write($output, Encoder::encodeBinary($buffer));
        }

        return $written;
    }

    /**
     * @param mixed $input
     * @param mixed $output
     *
     * @throws InvalidParam|DecodeException
     */
    public static function decode($input, $output, int $chunkSize = self::DEFAULT_CHUNK_SIZE): int
    {
        self::assertStreams($input, $output, $chunkSize, __FUNCTION__);
        \assert(\is_resource($input) && \is_resource($output));

        $written = 0;
        $buffer = '';
        while (!feof($input)) {
            $data = fread($input, max(1, $chunkSize));
            if ($data === false) {
                throw new DecodeException(null, 'cannot read from the input stream');
            }
            if (preg_match('/[^0-9A-Za-z]/', $data) === 1) {
                throw new DecodeException(null, 'the input stream contains non Base62x characters');
            }
            $buffer .= $data;

            $cut = self::lastCompleteGroupEnd($buffer);
            if ($cut > 0) {
                $written += self::write($output, Encoder::decodeBinary(substr($buffer, 0, $cut)));
                $buffer = (string) substr($buffer, $cut);
            }
        }
        if ($buffer !== '') {
            if (substr($buffer, -1) === Encoder::XTAG) {
                throw new DecodeException(null, 'ASCII-flavored Base62x cannot be stream-decoded: use Base62x::decode()');
            }
            $written += self::write($output, Encoder::decodeBinary($buffer));
        }

        return $written;
    }

    /**
     * Returns the offset right after the last complete group of 4 symbols in the buffer
     * ("x1", "x2" and "x3" count as one symbol).
     *
     * @throws DecodeException
     */
    private static function lastCompleteGroupEnd(string $buffer): int
    {
        $length = \strlen($buffer);
        $symbols = 0;
        $cut = 0;
        for ($i = 0; $i < $length; ++$i) {
            if ($buffer[$i] === Encoder::XTAG) {
                if ($i + 1 >= $length) {
                    break; // the escaped symbol is in the next chunk
                }
                $next = $buffer[++$i];
                if ($next !== '1' && $next !== '2' && $next !== '3') {
                    throw new DecodeException(null, 'ASCII-flavored Base62x cannot be stream-decoded: use Base62x::decode()');
                }
            }
            if (++$symbols % 4 === 0) {
                $cut = $i + 1;
            }
        }

        return $cut;
    }

    /**
     * @param resource $output
     *
     * @throws EncodeException
     */
    private static function write($output, string $data): int
    {
        $length = \strlen($data);
        $offset = 0;
        while ($offset < $length) {
            $bytes = fwrite($output, substr($data, $offset));
            if ($bytes === false || $bytes === 0) {
                throw new EncodeException(null, 'cannot write to the output stream');
            }
            $offset += $bytes;
        }

        return $length;
    }

    /**
     * @param mixed $input
     * @param mixed $output
     *
     * @throws InvalidParam
     */
    private static function assertStreams($input, $output, int $chunkSize, string $method): void
    {
        if (!\is_resource($input) || get_resource_type($input) !== 'stream') {
            throw new InvalidParam('input', $method, __CLASS__, 'a readable stream is required');
        }
        if (!\is_resource($output) || get_resource_type($output) !== 'stream') {
            throw new InvalidParam('output', $method, __CLASS__, 'a writable stream is required');
        }
        if ($chunkSize < 1) {
            throw new InvalidParam('chunkSize', $method, __CLASS__, 'the chunk size must be a positive integer');
        }
    }
}
