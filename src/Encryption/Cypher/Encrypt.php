<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Encryption\Cypher;

use Mfonte\Base62x\Exception\CryptException;

/**
 * @internal
 */
class Encrypt
{
    /**
     * Encrypts the data, and returns the hex-encoded IV followed by the base64 cipher text.
     *
     * @throws CryptException
     */
    public static function token(string $data, string $method, string $key): string
    {
        $ivLength = Bytes::iv($method);
        $iv = $ivLength > 0 ? random_bytes($ivLength) : '';

        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        if ($encrypted === false) {
            throw new CryptException('openssl_encrypt() failed with cipher method "'.$method.'"');
        }

        return bin2hex($iv).$encrypted;
    }
}
