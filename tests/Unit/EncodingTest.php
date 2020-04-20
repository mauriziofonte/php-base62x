<?php

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;

class EncodingTest extends TestCase
{
    /** @test */
    public function encoding_without_compression_simple()
    {
        $testString = $this->getRandomString(256);

        $encodedString = Base62x::encode($testString)->get();
        $decodedString = Base62x::decode($encodedString)->get();
        $this->assertEquals($testString, $decodedString);
    }

    /** @test */
    public function encoding_without_compression_complex()
    {
        $testString = $this->getRandomText(256);

        $encodedString = Base62x::encode($testString)->get();
        $decodedString = Base62x::decode($encodedString)->get();
        $this->assertEquals($testString, $decodedString);
    }

    /** @test */
    public function encoding_with_compression_simple()
    {
        $testString = $this->getRandomString(256);

        $encodedString = Base62x::encode($testString)->compress()->get();
        $decodedString = Base62x::decode($encodedString)->get();
        $this->assertEquals($testString, $decodedString);
    }

    /** @test */
    public function encoding_with_compression_complex()
    {
        $testString = $this->getRandomText(256);

        $encodedString = Base62x::encode($testString)->compress()->get();
        $decodedString = Base62x::decode($encodedString)->get();
        $this->assertEquals($testString, $decodedString);
    }

    /** @test */
    public function encoding_with_fixtures_without_compression()
    {
        $filesList = ['11-0.txt'];
        foreach ($filesList as $filename) {
            $testStream = \file_get_contents($this->getFixtureFile($filename));
            $encodedStream = Base62x::encode($testStream)->get();
            $decodedStream = Base62x::decode($encodedStream)->get();
            $this->assertEquals($testStream, $decodedStream);
        }
    }

    /** @test */
    public function encoding_with_fixtures_with_compression()
    {
        $filesList = ['1080-0.txt'];
        foreach ($filesList as $filename) {
            $testStream = \file_get_contents($this->getFixtureFile($filename));
            $encodedStream = Base62x::encode($testStream)->compress()->get();
            $decodedStream = Base62x::decode($encodedStream)->get();
            $this->assertEquals($testStream, $decodedStream);
        }
    }
}
