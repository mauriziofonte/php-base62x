<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Exception\CompressionException;
use Mfonte\Base62x\Compression\Huffman\HuffmanCoding;

class HuffmanCodingTest extends TestCase
{
    public function testRoundTripOfEveryByte(): void
    {
        $data = str_repeat(self::allBytes(), 3);

        $encoded = HuffmanCoding::encode($data, HuffmanCoding::createCodeTree($data));

        self::assertSame($data, HuffmanCoding::decode($encoded));
    }

    public function testNumericSymbolsAreHandled(): void
    {
        $data = '0123456789000111';

        self::assertSame($data, HuffmanCoding::decode(HuffmanCoding::encode($data, HuffmanCoding::createCodeTree($data))));
    }

    public function testSymbolsMissingFromTheTreeAreRejected(): void
    {
        $this->expectException(CompressionException::class);

        HuffmanCoding::encode('abcz', HuffmanCoding::createCodeTree('abc'));
    }

    public function testTruncatedDataIsRejected(): void
    {
        $data = 'hello huffman';
        $encoded = HuffmanCoding::encode($data, HuffmanCoding::createCodeTree($data));

        $this->expectException(CompressionException::class);
        HuffmanCoding::decode(substr($encoded, 0, -2));
    }

    public function testSingleLeafTreeIsRejected(): void
    {
        // found by FuzzTest: a tree made of a single leaf used to decode forever
        $this->expectException(CompressionException::class);

        HuffmanCoding::decode('1a'."\xFF\xFF");
    }

    public function testGarbageIsRejected(): void
    {
        $this->expectException(CompressionException::class);

        HuffmanCoding::decode('garbage');
    }
}
