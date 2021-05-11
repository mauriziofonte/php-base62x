<?php

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;

class EncodingTest extends TestCase
{
    public function testEncodingWithoutCompressionSimple()
    {
        $testString = $this->getRandomString(128);

        $encodedString = Base62x::encode($testString)->get();
        $decodedString = Base62x::decode($encodedString)->get();
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithoutCompressionComplex()
    {
        $testString = $this->getRandomText(512);

        $encodedString = Base62x::encode($testString)->get();
        $decodedString = Base62x::decode($encodedString)->get();
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithEncryptionSimple()
    {
        $testString = $this->getRandomString(128);
        $key = $this->getRandomString(16);

        \fwrite(\STDERR, 'Normal '.$testString.\chr(10));
        $encodedString = Base62x::encode($testString)->encrypt($key, 'aes-128-ctr')->get();
        \fwrite(\STDERR, 'Encoded '.$encodedString.\chr(10));
        $decodedString = Base62x::decode($encodedString)->decrypt($key, 'aes-128-ctr')->get();
        \fwrite(\STDERR, 'Decoded '.$decodedString.\chr(10));
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithEncryptionComplex()
    {
        $testString = $this->getRandomText(512);
        $key = $this->getRandomString(16);

        $encodedString = Base62x::encode($testString)->encrypt($key, 'aes-128-ctr')->get();
        $decodedString = Base62x::decode($encodedString)->decrypt($key, 'aes-128-ctr')->get();
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithCompressionSimple()
    {
        $testString = $this->getRandomString(128);

        $encodedString = Base62x::encode($testString)->compress()->get();
        $decodedString = Base62x::decode($encodedString)->get();
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithCompressionComplex()
    {
        $testString = $this->getRandomText(512);

        $encodedString = Base62x::encode($testString)->compress()->get();
        $decodedString = Base62x::decode($encodedString)->get();
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithCompressionAndEncryptionSimple()
    {
        $testString = $this->getRandomString(128);
        $key = $this->getRandomString(16);

        $encodedString = Base62x::encode($testString)->encrypt($key)->compress()->get();
        $decodedString = Base62x::decode($encodedString)->decrypt($key)->get();
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithCompressionAndEncryptionComplex()
    {
        $testString = $this->getRandomText(512);
        $key = $this->getRandomString(16);

        $encodedString = Base62x::encode($testString)->encrypt($key)->compress()->get();
        $decodedString = Base62x::decode($encodedString)->decrypt($key)->get();
        $this->assertEquals($testString, $decodedString);
    }

    public function testEncodingWithAllAvailableEncryptionAlgorithms()
    {
        $testString = $this->getRandomText(128);
        $key = $this->getRandomString(16);
        $algos = \openssl_get_cipher_methods();

        foreach ($algos as $algo) {
            \fwrite(\STDERR, 'Testing Encryption algorithm '.$algo.\chr(10));
            $encodedString = Base62x::encode($testString)->encrypt($key, $algo)->get();
            $decodedString = Base62x::decode($encodedString)->decrypt($key, $algo)->get();
            $this->assertEquals($testString, $decodedString);
        }
    }

    public function testEncodingWithFixturesWithoutCompression()
    {
        $filesList = ['11-0.txt'];
        foreach ($filesList as $filename) {
            $testStream = \file_get_contents($this->getFixtureFile($filename));
            $encodedStream = Base62x::encode($testStream)->get();
            $decodedStream = Base62x::decode($encodedStream)->get();
            $this->assertEquals($testStream, $decodedStream);
        }
    }

    public function testEncodingWithFixturesWithCompression()
    {
        $filesList = ['1080-0.txt'];
        foreach ($filesList as $filename) {
            $testStream = \file_get_contents($this->getFixtureFile($filename));
            $encodedStream = Base62x::encode($testStream)->compress()->get();
            $decodedStream = Base62x::decode($encodedStream)->get();
            $this->assertEquals($testStream, $decodedStream);
        }
    }

    public function testEncodingWithFixturesWithEncryptionAndCompression()
    {
        $filesList = ['1080-0.txt'];
        $key = $this->getRandomString(16);

        foreach ($filesList as $filename) {
            $testStream = \file_get_contents($this->getFixtureFile($filename));
            $encodedStream = Base62x::encode($testStream)->encrypt($key)->compress()->get();
            $decodedStream = Base62x::decode($encodedStream)->decrypt($key)->get();
            $this->assertEquals($testStream, $decodedStream);
        }
    }
}