<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Laravel;

use Mfonte\Base62x\Base62x;
use Mfonte\Base62x\Exception\CryptException;
use Mfonte\Base62x\Exception\Base62xException;

/**
 * Instance-based entry point to the library, bound in the Laravel container as "base62x".
 * It also offers one-liners that use the configured key, cipher and compression.
 */
class Base62xFactory
{
    /**
     * @var string|null
     */
    private $key;

    /**
     * @var string
     */
    private $cipher;

    /**
     * @var string|null
     */
    private $compression;

    public function __construct(?string $key = null, string $cipher = Base62x::CIPHER_XCHACHA20_POLY1305, ?string $compression = 'gzip')
    {
        $this->key = $key !== '' ? $key : null;
        $this->cipher = $cipher;
        $this->compression = $compression !== '' ? $compression : null;
    }

    /**
     * @param mixed $payload
     *
     * @throws Base62xException
     */
    public function encode($payload): Base62x
    {
        return Base62x::encode($payload);
    }

    /**
     * @param mixed $payload
     *
     * @throws Base62xException
     */
    public function decode($payload): Base62x
    {
        return Base62x::decode($payload);
    }

    /**
     * @param mixed $encoded
     */
    public function isValid($encoded): bool
    {
        return Base62x::isValid($encoded);
    }

    /**
     * Compresses (as configured), encrypts with the configured key and cipher, and encodes the payload.
     *
     * @param mixed $payload
     *
     * @throws Base62xException
     */
    public function encrypt($payload): string
    {
        $chain = Base62x::encode($payload)->encrypt($this->requireKey(), $this->cipher);
        if ($this->compression !== null) {
            $chain->compress($this->compression);
        }

        return $chain->getString();
    }

    /**
     * Decodes and decrypts a payload produced by self::encrypt().
     *
     * @throws Base62xException
     *
     * @return mixed
     */
    public function decrypt(string $encoded)
    {
        return Base62x::decode($encoded)->decrypt($this->requireKey(), $this->cipher)->get();
    }

    /**
     * Compresses (as configured) and encodes the payload, without encryption.
     *
     * @param mixed $payload
     *
     * @throws Base62xException
     */
    public function pack($payload): string
    {
        $chain = Base62x::encode($payload);
        if ($this->compression !== null) {
            $chain->compress($this->compression);
        }

        return $chain->getString();
    }

    /**
     * Decodes a payload produced by self::pack().
     *
     * @throws Base62xException
     *
     * @return mixed
     */
    public function unpack(string $encoded)
    {
        return Base62x::decode($encoded)->get();
    }

    /**
     * @throws CryptException
     */
    private function requireKey(): string
    {
        if ($this->key === null) {
            throw new CryptException('no encryption key configured: set "base62x.key" (BASE62X_KEY) or APP_KEY');
        }

        return $this->key;
    }
}
