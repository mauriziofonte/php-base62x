<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;
use Mfonte\Base62x\Exception\InvalidParam;
use Mfonte\Base62x\Exception\DecodeException;

class StreamTest extends TestCase
{
    /**
     * @return resource
     */
    private static function memoryStream(string $contents = '')
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private static function contents($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function payloadProvider(): iterable
    {
        foreach ([1, 2, 3, 4, 5, 7, 64, 1000] as $chunkSize) {
            yield "all bytes, chunk {$chunkSize}" => [str_repeat(self::allBytes(), 4), $chunkSize];
            yield "one byte, chunk {$chunkSize}" => ["\xFF", $chunkSize];
            yield "two bytes, chunk {$chunkSize}" => ["\xFF\xFE", $chunkSize];
        }
    }

    /**
     * @dataProvider payloadProvider
     */
    public function testRoundTrip(string $payload, int $chunkSize): void
    {
        $input = self::memoryStream($payload);
        $encoded = self::memoryStream();
        $written = Base62x::encodeStream($input, $encoded, $chunkSize);

        $encodedString = self::contents($encoded);
        self::assertSame(\strlen($encodedString), $written);
        self::assertSame(1, preg_match('/^[0-9A-Za-z]+$/', $encodedString));

        rewind($encoded);
        $decoded = self::memoryStream();
        self::assertSame(\strlen($payload), Base62x::decodeStream($encoded, $decoded, $chunkSize));
        self::assertSame($payload, self::contents($decoded));
    }

    public function testStreamOutputIsDecodableInMemory(): void
    {
        $payload = $this->getFixtureContents('11-0.txt');
        $encoded = self::memoryStream();
        Base62x::encodeStream(self::memoryStream($payload), $encoded);

        self::assertSame($payload, Base62x::decode(self::contents($encoded))->getString());
    }

    public function testBinaryFlavoredStringsAreStreamDecodable(): void
    {
        $encoded = Base62x::encode(self::allBytes())->get();
        $decoded = self::memoryStream();
        Base62x::decodeStream(self::memoryStream($encoded), $decoded, 5);

        self::assertSame(self::allBytes(), self::contents($decoded));
    }

    public function testAsciiFlavoredStringsAreRejected(): void
    {
        $this->expectException(DecodeException::class);

        Base62x::decodeStream(self::memoryStream(Base62x::encode('Hello, world!')->get()), self::memoryStream());
    }

    public function testInvalidCharactersAreRejected(): void
    {
        $this->expectException(DecodeException::class);

        Base62x::decodeStream(self::memoryStream('abc!def'), self::memoryStream());
    }

    public function testEmptyStream(): void
    {
        self::assertSame(0, Base62x::encodeStream(self::memoryStream(), self::memoryStream()));
        self::assertSame(0, Base62x::decodeStream(self::memoryStream(), self::memoryStream()));
    }

    public function testNonStreamsAreRejected(): void
    {
        $this->expectException(InvalidParam::class);

        Base62x::encodeStream('not a stream', self::memoryStream()); // @phpstan-ignore-line deliberately invalid
    }

    public function testInvalidChunkSizeIsRejected(): void
    {
        $this->expectException(InvalidParam::class);

        Base62x::encodeStream(self::memoryStream('foo'), self::memoryStream(), 0);
    }
}
