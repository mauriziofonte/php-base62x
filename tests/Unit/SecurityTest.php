<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;
use Mfonte\Base62x\Test\Unit\fixtures\ObjectInjectionCanary;

/**
 * Decoding untrusted input must never instantiate objects (PHP Object Injection).
 */
class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        ObjectInjectionCanary::$instantiated = false;
    }

    public function testSerializedObjectsAreNotUnserialized(): void
    {
        $payload = serialize(new ObjectInjectionCanary());
        $decoded = Base62x::decode(Base62x::encode($payload)->get())->get();

        self::assertSame($payload, $decoded);
        self::assertFalse(ObjectInjectionCanary::$instantiated);
    }

    public function testObjectsNestedInArraysAreNotInstantiated(): void
    {
        $payload = 'a:1:{s:3:"foo";'.serialize(new ObjectInjectionCanary()).'}';
        $decoded = Base62x::decode(Base62x::encode($payload)->compress()->get())->get();

        self::assertIsArray($decoded);
        self::assertInstanceOf(\__PHP_Incomplete_Class::class, $decoded['foo']);
        self::assertFalse(ObjectInjectionCanary::$instantiated);
    }
}
