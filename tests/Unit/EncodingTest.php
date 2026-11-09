<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;

/**
 * Round-trip tests with random payloads and the bundled text fixtures.
 */
class EncodingTest extends TestCase
{
    private const RANDOM_STRING_LENGTH = 16;
    private const PASSWORD_STRING_LENGTH = 16;
    private const SIMPLE_STRING_LINES = 2;
    private const COMPLEX_STRING_LINES = 5;

    public function testEncodingWithoutCompressionSimple(): void
    {
        $testString = $this->getRandomString(self::RANDOM_STRING_LENGTH);

        $encodedString = Base62x::encode($testString)->get();
        $decodedString = Base62x::decode($encodedString)->get();
        self::assertSame($testString, $decodedString);
    }

    public function testEncodingWithoutCompressionComplex(): void
    {
        $testString = $this->getRandomText(self::COMPLEX_STRING_LINES);

        $encodedString = Base62x::encode($testString)->get();
        $decodedString = Base62x::decode($encodedString)->get();
        self::assertSame($testString, $decodedString);
    }

    public function testEncodingWithEncryptionSimple(): void
    {
        $testString = $this->getRandomString(self::RANDOM_STRING_LENGTH);
        $password = $this->getRandomString(self::PASSWORD_STRING_LENGTH);

        $encodedString = Base62x::encode($testString)->encrypt($password, 'aes-128-ctr')->get();
        $decodedString = Base62x::decode($encodedString)->decrypt($password, 'aes-128-ctr')->get();
        self::assertSame($testString, $decodedString);
    }

    public function testEncodingWithEncryptionComplex(): void
    {
        $testString = $this->getRandomText(self::COMPLEX_STRING_LINES);
        $password = $this->getRandomString(self::PASSWORD_STRING_LENGTH);

        $encodedString = Base62x::encode($testString)->encrypt($password, 'aes-128-ctr')->get();
        $decodedString = Base62x::decode($encodedString)->decrypt($password, 'aes-128-ctr')->get();
        self::assertSame($testString, $decodedString);
    }

    public function testEncodingWithCompressionSimple(): void
    {
        $testString = $this->getRandomString(self::RANDOM_STRING_LENGTH);

        $encodedString = Base62x::encode($testString)->compress()->get();
        $decodedString = Base62x::decode($encodedString)->get();
        self::assertSame($testString, $decodedString);
    }

    public function testEncodingWithCompressionComplex(): void
    {
        $testString = $this->getRandomText(self::COMPLEX_STRING_LINES);

        $encodedString = Base62x::encode($testString)->compress()->get();
        $decodedString = Base62x::decode($encodedString)->get();
        self::assertSame($testString, $decodedString);
    }

    public function testEncodingWithCompressionAndEncryptionSimple(): void
    {
        $testString = $this->getRandomString(self::RANDOM_STRING_LENGTH);
        $password = $this->getRandomString(self::PASSWORD_STRING_LENGTH);

        $encodedString = Base62x::encode($testString)->encrypt($password)->compress()->get();
        $decodedString = Base62x::decode($encodedString)->decrypt($password)->get();
        self::assertSame($testString, $decodedString);
    }

    public function testEncodingWithCompressionAndEncryptionComplex(): void
    {
        $testString = $this->getRandomText(self::COMPLEX_STRING_LINES);
        $password = $this->getRandomString(self::PASSWORD_STRING_LENGTH);

        $encodedString = Base62x::encode($testString)->encrypt($password)->compress()->get();
        $decodedString = Base62x::decode($encodedString)->decrypt($password)->get();
        self::assertSame($testString, $decodedString);
    }

    public function testEncodingWithAllSupportedEncryptionAlgorithms(): void
    {
        $testString = $this->getRandomText(self::SIMPLE_STRING_LINES);
        $key = $this->getRandomString(16);

        $tested = 0;
        foreach (CryptTest::supportedCipherMethods() as $algo) {
            $encodedString = Base62x::encode($testString)->encrypt($key, $algo)->get();
            $decodedString = Base62x::decode($encodedString)->decrypt($key, $algo)->get();
            self::assertSame($testString, $decodedString, "Round-trip failed with cipher method {$algo}");
            ++$tested;
        }

        self::assertGreaterThan(0, $tested);
    }

    public function testEncodingWithFixturesWithoutCompression(): void
    {
        $testStream = $this->getFixtureContents('11-0.txt');

        $encodedStream = Base62x::encode($testStream)->get();
        $decodedStream = Base62x::decode($encodedStream)->get();
        self::assertSame($testStream, $decodedStream);
    }

    public function testEncodingWithFixturesWithCompression(): void
    {
        $testStream = $this->getFixtureContents('1080-0.txt');

        $encodedStream = Base62x::encode($testStream)->compress()->get();
        $decodedStream = Base62x::decode($encodedStream)->get();
        self::assertSame($testStream, $decodedStream);
    }

    public function testEncodingWithFixturesWithHuffmanCompression(): void
    {
        $testStream = $this->getFixtureContents('1080-0.txt');

        $encodedStream = Base62x::encode($testStream)->compress('huffman')->get();
        $decodedStream = Base62x::decode($encodedStream)->get();
        self::assertSame($testStream, $decodedStream);
    }

    public function testEncodingWithFixturesWithEncryptionAndCompression(): void
    {
        $password = $this->getRandomString(self::PASSWORD_STRING_LENGTH);
        $testStream = $this->getFixtureContents('1080-0.txt');

        $encodedStream = Base62x::encode($testStream)->encrypt($password)->compress()->get();
        $decodedStream = Base62x::decode($encodedStream)->decrypt($password)->get();
        self::assertSame($testStream, $decodedStream);
    }

    public function testCompressedOutputIsShorterForRedundantPayloads(): void
    {
        $testStream = $this->getFixtureContents('1080-0.txt');

        self::assertLessThan(
            \strlen(Base62x::encode($testStream)->get()),
            \strlen(Base62x::encode($testStream)->compress()->get())
        );
    }
}
