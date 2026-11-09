<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;

/**
 * The output must be identical to the reference implementations of Base62x (PHP, Python, JavaScript):
 * see tests/Unit/fixtures/interop-vectors.json.
 */
class InteropVectorsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function vectorProvider(): iterable
    {
        $json = file_get_contents(__DIR__.'/fixtures/interop-vectors.json');
        $data = json_decode((string) $json, true);
        self::assertIsArray($data);

        foreach ($data['vectors'] as $i => $vector) {
            $input = hex2bin($vector['input_hex']);
            self::assertIsString($input);
            yield "#{$i} ".substr($vector['encoded'], 0, 24) => [$input, $vector['encoded']];
        }
    }

    /**
     * @dataProvider vectorProvider
     */
    public function testEncodeMatchesTheReferenceImplementation(string $input, string $encoded): void
    {
        self::assertSame($encoded, Base62x::encode($input)->getString());
    }

    /**
     * @dataProvider vectorProvider
     */
    public function testDecodeMatchesTheReferenceImplementation(string $input, string $encoded): void
    {
        self::assertSame($input, Base62x::decode($encoded)->getString());
    }
}
