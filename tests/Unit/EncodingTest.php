<?php

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;

class EncodingTest extends TestCase
{
    private const RANDOM_STRING_LENGTH = 16; // 128
    private const PASSWORD_STRING_LENGTH = 16; // 16
    private const SIMPLE_STRING_LINES = 2; // 32
    private const COMPLEX_STRING_LINES = 5; // 512

    public function testEncodingWithoutCompressionSimple()
    {
        $testString = $this->getRandomString(self::RANDOM_STRING_LENGTH);

        $encodedString = Base62x::encode($testString)->get();
        $decodedString = Base62x::decode($encodedString)->get();
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithoutCompressionComplex()
    {
        $testString = $this->getRandomText(self::COMPLEX_STRING_LINES);

        $encodedString = Base62x::encode($testString)->get();
        $decodedString = Base62x::decode($encodedString)->get();
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithEncryptionSimple()
    {
        $testString = $this->getRandomString(self::RANDOM_STRING_LENGTH);
        $password = $this->getRandomString(self::PASSWORD_STRING_LENGTH);

        // \fwrite(\STDERR, 'Normal '.$testString.\chr(10));
        $encodedString = Base62x::encode($testString)->encrypt($password, 'aes-128-ctr')->get();
        // \fwrite(\STDERR, 'Encoded '.$encodedString.\chr(10));
        $decodedString = Base62x::decode($encodedString)->decrypt($password, 'aes-128-ctr')->get();
        // \fwrite(\STDERR, 'Decoded '.$decodedString.\chr(10));
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithEncryptionComplex()
    {
        $testString = $this->getRandomText(self::COMPLEX_STRING_LINES);
        $password = $this->getRandomString(self::PASSWORD_STRING_LENGTH);

        $encodedString = Base62x::encode($testString)->encrypt($password, 'aes-128-ctr')->get();
        $decodedString = Base62x::decode($encodedString)->decrypt($password, 'aes-128-ctr')->get();
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithCompressionSimple()
    {
        $testString = $this->getRandomString(self::RANDOM_STRING_LENGTH);

        $encodedString = Base62x::encode($testString)->compress()->get();
        $decodedString = Base62x::decode($encodedString)->get();
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithCompressionComplex()
    {
        $testString = $this->getRandomText(self::COMPLEX_STRING_LINES);

        $encodedString = Base62x::encode($testString)->compress()->get();
        $decodedString = Base62x::decode($encodedString)->get();
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithCompressionAndEncryptionSimple()
    {
        $testString = $this->getRandomString(self::RANDOM_STRING_LENGTH);
        $password = $this->getRandomString(self::PASSWORD_STRING_LENGTH);

        $encodedString = Base62x::encode($testString)->encrypt($password)->compress()->get();
        $decodedString = Base62x::decode($encodedString)->decrypt($password)->get();
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithCompressionAndEncryptionComplex()
    {
        $testString = $this->getRandomText(self::COMPLEX_STRING_LINES);
        $password = $this->getRandomString(self::PASSWORD_STRING_LENGTH);

        $encodedString = Base62x::encode($testString)->encrypt($password)->compress()->get();
        $decodedString = Base62x::decode($encodedString)->decrypt($password)->get();
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithAllAvailableEncryptionAlgorithms()
    {
        $testString = $this->getRandomText(self::SIMPLE_STRING_LINES);
        $key = $this->getRandomString(16);
        $algos = openssl_get_cipher_methods();

        foreach ($algos as $algo) {
            if (\in_array(mb_strtolower($algo), ['aes-128-ecb', 'aes-192-ecb', 'aes-256-ecb', 'aead'], true)) {
                // unsupported algorithms that would throw an exception
                continue;
            }
            fwrite(\STDERR, 'Testing Encryption algorithm '.$algo.\chr(10));
            $encodedString = Base62x::encode($testString)->encrypt($key, $algo)->get();
            $decodedString = Base62x::decode($encodedString)->decrypt($key, $algo)->get();
            $this->assertEquals($testString, $decodedString);
        }
    }

    public function testEncodingWithFixturesWithoutCompression()
    {
        $filesList = ['11-0.txt'];
        foreach ($filesList as $filename) {
            $testStream = file_get_contents($this->getFixtureFile($filename));
            $encodedStream = Base62x::encode($testStream)->get();
            $decodedStream = Base62x::decode($encodedStream)->get();
            $this->assertEquals($testStream, $decodedStream);
        }
    }

    public function testEncodingWithFixturesWithCompression()
    {
        $filesList = ['1080-0.txt'];
        foreach ($filesList as $filename) {
            $testStream = file_get_contents($this->getFixtureFile($filename));
            $encodedStream = Base62x::encode($testStream)->compress()->get();
            $decodedStream = Base62x::decode($encodedStream)->get();
            $this->assertEquals($testStream, $decodedStream);
        }
    }

    public function testEncodingWithFixturesWithEncryptionAndCompression()
    {
        $filesList = ['1080-0.txt'];
        $password = $this->getRandomString(self::PASSWORD_STRING_LENGTH);

        foreach ($filesList as $filename) {
            $testStream = file_get_contents($this->getFixtureFile($filename));
            $encodedStream = Base62x::encode($testStream)->encrypt($password)->compress()->get();
            $decodedStream = Base62x::decode($encodedStream)->decrypt($password)->get();
            $this->assertEquals($testStream, $decodedStream);
        }
    }
}
