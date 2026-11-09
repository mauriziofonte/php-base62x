<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Encryption\Cypher;

use Mfonte\Base62x\Exception\CryptException;

/**
 * @internal
 */
class Bytes
{
    /**
     * Returns the IV length, in bytes, of the given cipher method.
     *
     * @throws CryptException
     */
    public static function iv(string $method): int
    {
        $length = @openssl_cipher_iv_length($method);
        if ($length === false) {
            throw new CryptException('cannot determine the IV length of cipher method "'.$method.'"');
        }

        return $length;
    }
}
