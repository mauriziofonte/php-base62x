<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;
use Mfonte\Base62x\Exception\InvalidParam;
use Mfonte\Base62x\Exception\DecodeException;

class TypedGettersTest extends TestCase
{
    public function testGetStringDoesNotUnserialize(): void
    {
        $looksSerialized = 'a:1:{i:0;s:3:"foo";}';
        $encoded = Base62x::encode($looksSerialized)->getString();

        self::assertSame(['foo'], Base62x::decode($encoded)->get());
        self::assertSame($looksSerialized, Base62x::decode($encoded)->getString());
    }

    public function testGetStringWhileEncodingEqualsGet(): void
    {
        self::assertSame(Base62x::encode('foo')->get(), Base62x::encode('foo')->getString());
    }

    public function testGetArray(): void
    {
        $payload = ['foo' => 'bar', 'list' => [1, 2, 3]];

        self::assertSame($payload, Base62x::decode(Base62x::encode($payload)->compress()->get())->getArray());
    }

    public function testGetArrayRejectsNonArrays(): void
    {
        $this->expectException(DecodeException::class);

        Base62x::decode(Base62x::encode('not an array')->get())->getArray();
    }

    public function testGetArrayIsOnlyAvailableWhileDecoding(): void
    {
        $this->expectException(InvalidParam::class);

        Base62x::encode(['foo'])->getArray();
    }

    public function testIsValid(): void
    {
        self::assertTrue(Base62x::isValid(Base62x::encode('foo')->get()));
        self::assertTrue(Base62x::isValid(Base62x::encode(self::allBytes())->compress('huffman')->get()));
        self::assertTrue(Base62x::isValid(Base62x::encode('foo')->encrypt('key')->get()));

        self::assertFalse(Base62x::isValid(''));
        self::assertFalse(Base62x::isValid(null));
        self::assertFalse(Base62x::isValid(['foo']));
        self::assertFalse(Base62x::isValid('not valid!'));
        self::assertFalse(Base62x::isValid('x9abcd'));
        self::assertFalse(Base62x::isValid(Base62x::encode('[MFB62X.COMPRESS.'.base64_encode('gzip,gzip').']garbage')->get()));
    }

    /**
     * @requires extension sodium
     */
    public function testIsValidDoesNotNeedTheKey(): void
    {
        self::assertTrue(Base62x::isValid(Base62x::encode('foo')->encrypt('key', Base62x::CIPHER_XCHACHA20_POLY1305)->get()));
    }
}
