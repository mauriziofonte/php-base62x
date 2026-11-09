<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit\fixtures;

/**
 * A class whose magic methods record that it was brought to life by unserialize().
 */
final class ObjectInjectionCanary
{
    /**
     * @var bool
     */
    public static $instantiated = false;

    /**
     * @param array<mixed> $data
     */
    public function __unserialize(array $data): void
    {
        self::$instantiated = true;
    }

    public function __destruct()
    {
        // nothing to do: only unserialize() flips the flag
    }
}
