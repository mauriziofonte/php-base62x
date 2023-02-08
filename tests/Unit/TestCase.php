<?php

namespace Mfonte\Base62x\Test\Unit;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    /** @var string */
    protected $fixturesDir;
    protected $testDir;

    protected function setUp(): void
    {
        $this->testDir = rtrim(__DIR__, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR;
        $this->fixturesDir = rtrim(__DIR__, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR.'fixtures'.\DIRECTORY_SEPARATOR;
    }

    protected function getFixtureFile($fileName): string
    {
        return $this->fixturesDir."{$fileName}";
    }

    protected function getRandomString(int $size): string
    {
        $stream = $this->getFixtureFile('utf8.txt');

        // pick $size random characters from the file
        $randomString = '';
        $fp = fopen($stream, 'r');
        fseek($fp, 0, \SEEK_END);
        $fileSize = ftell($fp);
        fseek($fp, 0, \SEEK_SET);
        $randomPositions = [];
        for ($i = 0; $i < $size; ++$i) {
            $randomPositions[] = rand(0, $fileSize);
        }

        sort($randomPositions);
        foreach ($randomPositions as $position) {
            fseek($fp, $position, \SEEK_SET);
            $randomString .= fread($fp, 1);
        }

        fclose($fp);

        return $randomString;
    }

    protected function getRandomText(int $lines): string
    {
        $ret = [];
        for ($i = 0; $i < $lines; ++$i) {
            $ret[] = $this->getRandomString(rand(32, 64));
        }

        return implode(\PHP_EOL, $ret);
    }
}
