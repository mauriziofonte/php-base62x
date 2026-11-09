<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected function getFixtureFile(string $fileName): string
    {
        return __DIR__.\DIRECTORY_SEPARATOR.'fixtures'.\DIRECTORY_SEPARATOR.$fileName;
    }

    protected function getFixtureContents(string $fileName): string
    {
        $contents = file_get_contents($this->getFixtureFile($fileName));
        self::assertIsString($contents);

        return $contents;
    }

    /**
     * Picks $size random bytes from the UTF-8 fixture file: the result is usually NOT valid UTF-8,
     * which makes it a good sample of "binary" data.
     */
    protected function getRandomString(int $size): string
    {
        $stream = $this->getFixtureContents('utf8.txt');
        $maxPosition = max(0, \strlen($stream) - 1);

        $randomString = '';
        for ($i = 0; $i < $size; ++$i) {
            $randomString .= $stream[random_int(0, $maxPosition)];
        }

        return $randomString;
    }

    protected function getRandomText(int $lines): string
    {
        $ret = [];
        for ($i = 0; $i < $lines; ++$i) {
            $ret[] = $this->getRandomString(random_int(32, 64));
        }

        return implode(\PHP_EOL, $ret);
    }

    /**
     * Every byte from 0x00 to 0xFF.
     */
    protected static function allBytes(): string
    {
        return implode('', array_map('chr', range(0, 255)));
    }
}
