<?php

namespace Mfonte\Base62x\Encryption\Cypher;

class Bytes
{
    public static function iv($method)
    {
        return openssl_cipher_iv_length($method);
    }
}
