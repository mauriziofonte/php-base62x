<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;

/**
 * Every payload must survive a round-trip byte-for-byte, with every combination of options.
 */
class BinarySafetyTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function payloadProvider(): iterable
    {
        yield 'single ascii char' => ['a'];
        yield 'the "0" string' => ['0'];
        yield 'the "x" tag char' => ['x'];
        yield 'ascii text' => ['The quick brown fox jumps over the lazy dog'];
        yield 'ascii control chars' => ["line1\r\nline2\ttab\x11\x12\x1C\x1F"];
        yield 'utf-8 text' => ['Caffè ☕ naïve — ünïcödé 日本語 🚀'];
        yield 'all bytes' => [self::allBytes()];
        yield 'all bytes reversed' => [strrev(self::allBytes())];
        yield 'null bytes' => [str_repeat("\0", 7)];
        yield 'high bytes' => [str_repeat("\xFF\xFE\x80", 5)];
        yield 'lengths mod 3 = 1' => ["\x00\xFF\x10\xEE"];
        yield 'lengths mod 3 = 2' => ["\x00\xFF\x10\xEE\x7F"];
    }

    /**
     * @dataProvider payloadProvider
     */
    public function testPlainRoundTrip(string $payload): void
    {
        $encoded = Base62x::encode($payload)->get();

        self::assertMatchesRegularExpressionCompat('/^[0-9A-Za-z]+$/', $encoded);
        self::assertSame($payload, Base62x::decode($encoded)->get());
    }

    /**
     * @dataProvider payloadProvider
     */
    public function testCompressedRoundTrip(string $payload): void
    {
        foreach ([['gzip', 'gzip'], ['gzip', 'zlib'], ['gzip', 'deflate'], ['huffman', null]] as [$algo, $encoding]) {
            $encoded = Base62x::encode($payload)->compress($algo, $encoding)->get();

            self::assertMatchesRegularExpressionCompat('/^[0-9A-Za-z]+$/', $encoded);
            self::assertSame($payload, Base62x::decode($encoded)->get(), "Round-trip failed with {$algo}/{$encoding}");
        }
    }

    /**
     * @dataProvider payloadProvider
     */
    public function testEncryptedRoundTrip(string $payload): void
    {
        foreach (['ascii key', "binary\x00\xFFkey", '0'] as $key) {
            $encoded = Base62x::encode($payload)->encrypt($key)->get();
            self::assertSame($payload, Base62x::decode($encoded)->decrypt($key)->get());

            $encoded = Base62x::encode($payload)->encrypt($key, 'aes-256-cbc')->compress('huffman')->get();
            self::assertSame($payload, Base62x::decode($encoded)->decrypt($key, 'aes-256-cbc')->get());
        }
    }

    public function testArraysAreRoundTripped(): void
    {
        $payload = ['foo' => 'bar', 'bar' => ['foo1', 'foo2', 'fooman' => 'foobar'], 'n' => [1, 2.5, true, null], 'bin' => self::allBytes()];

        self::assertSame($payload, Base62x::decode(Base62x::encode($payload)->get())->get());
        self::assertSame($payload, Base62x::decode(Base62x::encode($payload)->compress()->get())->get());
        self::assertSame($payload, Base62x::decode(Base62x::encode($payload)->encrypt('k')->get())->decrypt('k')->get());
    }

    public function testScalarsAreEncodedAsStrings(): void
    {
        self::assertSame('0', Base62x::decode(Base62x::encode(0)->get())->get());
        self::assertSame('42', Base62x::decode(Base62x::encode(42)->get())->get());
        self::assertSame('1.5', Base62x::decode(Base62x::encode(1.5)->get())->get());
        self::assertSame('1', Base62x::decode(Base62x::encode(true)->get())->get());
    }

    /**
     * assertMatchesRegularExpression() does not exist in PHPUnit 8.
     */
    private static function assertMatchesRegularExpressionCompat(string $pattern, string $string): void
    {
        self::assertSame(1, preg_match($pattern, $string), "Failed asserting that '{$string}' matches {$pattern}");
    }
}
