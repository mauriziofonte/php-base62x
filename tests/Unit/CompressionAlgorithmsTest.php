<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;
use Mfonte\Base62x\Exception\CompressionException;

class CompressionAlgorithmsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function algorithmProvider(): iterable
    {
        yield 'bzip2' => ['bzip2', 'bz2'];
        yield 'zstd' => ['zstd', 'zstd'];
        yield 'brotli' => ['brotli', 'brotli'];
    }

    /**
     * @dataProvider algorithmProvider
     */
    public function testRoundTrip(string $algo, string $extension): void
    {
        if (!\extension_loaded($extension)) {
            self::markTestSkipped("ext-{$extension} is not available");
        }

        $text = $this->getFixtureContents('1080-0.txt');
        foreach (['a', self::allBytes(), $text, ['foo' => 'bar']] as $payload) {
            $encoded = Base62x::encode($payload)->compress($algo)->get();
            self::assertSame($payload, Base62x::decode($encoded)->get());
        }

        self::assertLessThan(\strlen(Base62x::encode($text)->get()), \strlen(Base62x::encode($text)->compress($algo)->get()));
    }

    /**
     * @dataProvider algorithmProvider
     */
    public function testRoundTripWithEncryption(string $algo, string $extension): void
    {
        if (!\extension_loaded($extension)) {
            self::markTestSkipped("ext-{$extension} is not available");
        }

        $encoded = Base62x::encode('payload')->encrypt('key')->compress($algo)->get();
        self::assertSame('payload', Base62x::decode($encoded)->decrypt('key')->get());
    }

    /**
     * @dataProvider algorithmProvider
     */
    public function testMissingExtensionIsReportedEarly(string $algo, string $extension): void
    {
        if (\extension_loaded($extension)) {
            self::markTestSkipped("ext-{$extension} is available");
        }

        $this->expectException(CompressionException::class);
        Base62x::encode('payload')->compress($algo);
    }

    /**
     * @dataProvider algorithmProvider
     */
    public function testCorruptedDataIsRejected(string $algo, string $extension): void
    {
        if (!\extension_loaded($extension)) {
            self::markTestSkipped("ext-{$extension} is not available");
        }

        $corrupted = Base62x::encode('[MFB62X.COMPRESS.'.base64_encode($algo.',').']not compressed data')->get();

        $this->expectException(CompressionException::class);
        Base62x::decode($corrupted)->get();
    }
}
