<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;
use Mfonte\Base62x\Exception\InvalidParam;
use Mfonte\Base62x\Exception\DecodeException;
use Mfonte\Base62x\Exception\Base62xException;

class ValidationTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidEncodePayloadProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'empty array' => [[]];
        yield 'object' => [new \stdClass()];
    }

    /**
     * @dataProvider invalidEncodePayloadProvider
     *
     * @param mixed $payload
     */
    public function testInvalidPayloadsAreRejected($payload): void
    {
        $this->expectException(InvalidParam::class);

        Base62x::encode($payload);
    }

    public function testResourcesAreRejected(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        try {
            $this->expectException(InvalidParam::class);
            Base62x::encode($resource);
        } finally {
            fclose($resource);
        }
    }

    public function testArraysCannotBeDecoded(): void
    {
        $this->expectException(InvalidParam::class);

        Base62x::decode(['foo']);
    }

    public function testInvalidModeIsRejected(): void
    {
        $this->expectException(InvalidParam::class);

        new Base62x(3, 'payload');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedInputProvider(): iterable
    {
        yield 'symbols' => ['!!!@@@'];
        yield 'base64 chars' => ['aGVsbG8+/w=='];
        yield 'whitespace' => ['abc def'];
        yield 'dangling x tag' => ['abcx1x'];
        yield 'bad x sequence' => ['x9abcd'];
        yield 'single trailing char' => ['abcde'];
        yield 'lonely tag' => ['x'];
    }

    /**
     * @dataProvider malformedInputProvider
     */
    public function testMalformedInputThrowsDecodeException(string $input): void
    {
        $this->expectException(DecodeException::class);

        Base62x::decode($input)->get();
    }

    public function testTamperedCompressionFootprintIsRejected(): void
    {
        // footprint declaring an unknown algorithm
        $tampered = Base62x::encode('[MFB62X.COMPRESS.'.base64_encode('lzma,').']payload')->get();

        $this->expectException(DecodeException::class);
        Base62x::decode($tampered)->get();
    }

    public function testInvalidCompressionAlgorithmIsRejected(): void
    {
        $this->expectException(InvalidParam::class);

        Base62x::encode('payload')->compress('lzma');
    }

    public function testInvalidCompressionEncodingIsRejected(): void
    {
        $this->expectException(InvalidParam::class);

        Base62x::encode('payload')->compress('gzip', 'brotli');
    }

    public function testHuffmanIgnoresTheEncoding(): void
    {
        $encoded = Base62x::encode('payload')->compress('huffman', 'whatever')->get();

        self::assertSame('payload', Base62x::decode($encoded)->get());
    }

    public function testEveryExceptionImplementsTheMarkerInterface(): void
    {
        try {
            Base62x::decode('!!!')->get();
            self::fail('An exception was expected');
        } catch (Base62xException $e) {
            self::assertInstanceOf(DecodeException::class, $e);
        }
    }
}
