<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;
use Mfonte\Base62x\Exception\Base62xException;

/**
 * Deterministic fuzzing: whatever the input, decoding must either succeed or throw a library exception.
 * PHP warnings, notices and errors are failures (PHPUnit converts them to exceptions).
 */
class FuzzTest extends TestCase
{
    private const ITERATIONS = 2000;
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function testRandomAlphanumericInput(): void
    {
        mt_srand(1);
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $input = '';
            $length = mt_rand(1, 64);
            for ($k = 0; $k < $length; ++$k) {
                $input .= self::ALPHABET[mt_rand(0, 61)];
            }
            $this->assertDecodesOrThrows($input);
        }
    }

    public function testRandomBytes(): void
    {
        mt_srand(2);
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $input = '';
            $length = mt_rand(1, 64);
            for ($k = 0; $k < $length; ++$k) {
                $input .= \chr(mt_rand(0, 255));
            }
            $this->assertDecodesOrThrows($input);
        }
    }

    public function testMutatedValidPayloads(): void
    {
        mt_srand(3);
        $payloads = [
            Base62x::encode('The quick brown fox')->get(),
            Base62x::encode(self::allBytes())->get(),
            Base62x::encode(['foo' => 'bar'])->compress()->get(),
            Base62x::encode(self::allBytes())->compress('huffman')->get(),
            Base62x::encode('secret')->encrypt('key')->compress()->get(),
        ];
        if (\extension_loaded('sodium')) {
            $payloads[] = Base62x::encode('secret')->encrypt('key', Base62x::CIPHER_XCHACHA20_POLY1305)->compress('huffman')->get();
        }

        foreach ($payloads as $payload) {
            for ($i = 0; $i < 300; ++$i) {
                $mutated = $payload;
                $position = mt_rand(0, \strlen($mutated) - 1);
                switch (mt_rand(0, 2)) {
                    case 0: // replace a char
                        $mutated[$position] = self::ALPHABET[mt_rand(0, 61)];
                        break;
                    case 1: // delete a char
                        $mutated = substr($mutated, 0, $position).substr($mutated, $position + 1);
                        break;
                    default: // insert a char
                        $mutated = substr($mutated, 0, $position).self::ALPHABET[mt_rand(0, 61)].substr($mutated, $position);
                }
                $this->assertDecodesOrThrows($mutated, 'key');
            }
        }
    }

    private function assertDecodesOrThrows(string $input, ?string $key = null): void
    {
        try {
            $decoder = Base62x::decode($input);
            if ($key !== null) {
                $decoder->decrypt($key);
            }
            $decoder->get();
            Base62x::isValid($input);
            $this->addToAssertionCount(1);
        } catch (Base62xException $e) {
            $this->addToAssertionCount(1);
        }
    }
}
