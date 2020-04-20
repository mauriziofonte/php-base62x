<?php

namespace Mfonte\Base62x\Test\Unit;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    /** @var string */
    protected $fixturesDir;
    protected $testDir;

    public function setUp(): void
    {
        $this->testDir = \rtrim(__DIR__, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR;
        $this->fixturesDir = \rtrim(__DIR__, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR.'fixtures'.\DIRECTORY_SEPARATOR;
    }

    protected function getFixtureFile($fileName): string
    {
        return $this->fixturesDir."{$fileName}";
    }

    protected function getRandomString(int $size): string
    {
        $characters = \str_replace([\chr(13).\chr(10), \chr(10), \chr(9)], '', \preg_replace('/\s\s+/', '', \implode('', \explode(\chr(10), \file_get_contents($this->fixturesDir.'utf8.txt')))));
        $charactersLength = \mb_strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $size; ++$i) {
            $randomString .= $characters[\rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    protected function getRandomText(int $lines): string
    {
        $ret = '';
        for ($i = 0; $i < $lines; ++$i) {
            for ($k = 0; $k < 10; ++$k) {
                $ret .= $this->getRandomString(\rand(5, 20)).' ';
            }
            $ret = \trim($ret).\chr(10);
        }

        return \trim($ret, \chr(10));
    }
}