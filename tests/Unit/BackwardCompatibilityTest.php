<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;

/**
 * Payloads produced by previous releases of the library must keep decoding to the same value:
 * the wire format is a public contract.
 *
 * All the vectors below were generated with v1.x (before the binary-safety fixes).
 */
class BackwardCompatibilityTest extends TestCase
{
    private const PAYLOAD = 'The quick brown fox jumps over the lazy dog';
    private const KEY = 'legacy-secret';

    /**
     * @return iterable<string, array{string}>
     */
    public static function legacyVectorProvider(): iterable
    {
        yield 'plain' => ['ThexRquickxRbrownxRfoxxxRjumpsxRoverxRthexRlazyxRdogx'];
        yield 'gzip/gzip' => ['xoMFB62XxfCOMPRESSxfZ3ppcCxxnemlwxqH4sIAAAAAAACAwvJSFUoLM1MzlZIKsovz1NIy69QyCrNLShWyC9LLVIoAUrnJFZVKqTkpwMAOaNPQSsAAAAxkx'];
        yield 'gzip/zlib' => ['xoMFB62XxfCOMPRESSxfZ3ppcCxx6bGlixqeNoLyUhVKCzNTM5WSCrKL89TSMuvUMgqzS0oVsgvSy1SKAFK5yRWVSqk5KcDAFvcD9oxkx'];
        yield 'gzip/deflate' => ['xoMFB62XxfCOMPRESSxfZ3ppcCxxkZWZsYXRlxqC8lIVSgszUzOVkgqyixgPU0jLr1DIKs0tKFbIL0stUigBSuckVlUqpOSnAwAxkx'];
    }

    /**
     * @dataProvider legacyVectorProvider
     */
    public function testLegacyPayloadsStillDecode(string $encoded): void
    {
        self::assertSame(self::PAYLOAD, Base62x::decode($encoded)->get());
    }

    /**
     * ASCII payloads were not affected by the binary-safety fixes: they must still produce the very same output.
     */
    public function testAsciiOutputIsUnchanged(): void
    {
        self::assertSame('ThexRquickxRbrownxRfoxxxRjumpsxRoverxRthexRlazyxRdogx', Base62x::encode(self::PAYLOAD)->get());
        self::assertSame('HelloxdxRBase62xxxSx', Base62x::encode('Hello, Base62x!')->get());
    }

    /**
     * Binary and UTF-8 output must match v1.2.x and the reference implementation
     * (v1.3.0 to v1.4.0 produced corrupted output for these payloads).
     */
    public function testBinaryOutputMatchesTheReferenceImplementation(): void
    {
        $allBytes = '00420mG51WS82GeB30qE3n0H4XCK5HON61aQ6nmT7XzW8I8Z92Kc9oWfAYiiBIulC34oCpGrDZSuEJeyF3qx2Fq11GaD4HKP7I4bAIqnDJax1GKL9JL5LMLrXPMbjSNLvVO65YOsHbPcTeQMfhR6rkRt1nSdDqTNPtU7bwUtnx1Vdx20WOA3X8M6XuY9YekCZOwFa96IavILbfUOcPgRd9sUdw2XegEafQQdgAcggwojhgx2miRApjBMsjyYvkhkzlRwx3mC72mzJ5niV8oShBpCtEpx13HqjFKrTRNsDdQsx1pTtjx3WuUBZvENcvx2ZfwkliyUylzF7ozx3Jrx1lVux2Vhyx3Ftx2x33';
        self::assertSame($allBytes, Base62x::encode(self::allBytes())->get());
        self::assertSame(self::allBytes(), Base62x::decode($allBytes)->get());

        $utf8 = 'Gs5cPiEe8EAObI1kOSElTcKWue2K8EQNfUQShEYgd2';
        self::assertSame($utf8, Base62x::encode('Caffè ☕ naïve — 日本語')->get());
        self::assertSame('Caffè ☕ naïve — 日本語', Base62x::decode($utf8)->get());
    }

    public function testLegacyEncryptedPayloadsStillDecrypt(): void
    {
        $aes128ctr = '4fdbe5e43dfb9b7e611882310804e258qP9MTbaXKr8uxgCHAfrPXEcWnHxgPKTEtSHKQW231JOjxgQxcW9jkNOHpJCY5gxkxkx';
        self::assertSame(self::PAYLOAD, Base62x::decode($aes128ctr)->decrypt(self::KEY)->get());

        $aes256cbcGzip = 'xoMFB62XxfCOMPRESSxfZ3ppcCxxnemlwxqH4sIAAAAAAACA0tLSTGxxMDWzSDE3SDRItjQwSkxxJTDIxxSLIwNTE1SkpK9C2L8rLMSw7OKg80Sk42cM4rLE921i8L83TLyfGPMk4PKQlw83HMLM4qNy9IdzR1DU9PMzQyM0z1CHVJ9yxg0NgcAxcJKqxcmAAAAAxkx';
        self::assertSame(self::PAYLOAD, Base62x::decode($aes256cbcGzip)->decrypt(self::KEY, 'aes-256-cbc')->get());
    }

    public function testLegacyArrayPayloadStillDecodes(): void
    {
        $encoded = 'xoMFB62XxfCOMPRESSxfZ3ppcCxxnemlwxqH4sIAAAAAAACA0u0MrKqLrYytVIqyi8tSVWyLraysFLKyM9NLUhMB3MNDayUgOzMvMSSzPw8JetEiA6gquTSoqIAsKpMK0OIxxtzECpBIMUjIyLq2FgCWA4RnXwAAAAxkxkx';

        self::assertSame(
            ['route' => 'homepage', 'pagination' => ['currPage' => 1, 'maxPages' => 2]],
            Base62x::decode($encoded)->get()
        );
    }
}
