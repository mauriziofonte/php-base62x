<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Laravel\Casts;

use Mfonte\Base62x\Laravel\Base62xFactory;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Eloquent cast that stores an attribute as a Base62x string.
 *
 *     protected $casts = [
 *         'token'   => Base62xCast::class,                        // plain Base62x
 *         'context' => Base62xCast::class.':compressed',          // + configured compression
 *         'secret'  => Base62xCast::class.':encrypted',           // + configured compression and encryption
 *     ];
 *
 * Arrays are supported: they are restored as arrays.
 *
 * @implements CastsAttributes<mixed, mixed>
 */
class Base62xCast implements CastsAttributes
{
    /**
     * Resolves the factory configured by the service provider.
     *
     * @var (callable(): Base62xFactory)|null
     */
    public static $factoryResolver;

    /**
     * @var string
     */
    private $mode;

    public function __construct(string $mode = 'plain')
    {
        if (!\in_array($mode, ['plain', 'compressed', 'encrypted'], true)) {
            throw new \InvalidArgumentException('Base62xCast mode must be one of: plain, compressed, encrypted');
        }
        $this->mode = $mode;
    }

    /**
     * @param mixed                $model
     * @param mixed                $value
     * @param array<string, mixed> $attributes
     *
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $factory = self::factory();
        switch ($this->mode) {
            case 'encrypted':
                return $factory->decrypt((string) $value);
            case 'compressed':
                return $factory->unpack((string) $value);
            default:
                return $factory->decode((string) $value)->get();
        }
    }

    /**
     * @param mixed                $model
     * @param mixed                $value
     * @param array<string, mixed> $attributes
     */
    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $factory = self::factory();
        switch ($this->mode) {
            case 'encrypted':
                return $factory->encrypt($value);
            case 'compressed':
                return $factory->pack($value);
            default:
                return $factory->encode($value)->getString();
        }
    }

    private static function factory(): Base62xFactory
    {
        if (self::$factoryResolver !== null) {
            return (self::$factoryResolver)();
        }

        return new Base62xFactory();
    }
}
