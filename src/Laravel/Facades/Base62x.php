<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Mfonte\Base62x\Base62x encode(mixed $payload)
 * @method static \Mfonte\Base62x\Base62x decode(mixed $payload)
 * @method static bool                    isValid(mixed $encoded)
 * @method static string                  encrypt(mixed $payload)
 * @method static mixed                   decrypt(string $encoded)
 * @method static string                  pack(mixed $payload)
 * @method static mixed                   unpack(string $encoded)
 *
 * @see \Mfonte\Base62x\Laravel\Base62xFactory
 */
class Base62x extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'base62x';
    }
}
