<?php

namespace Mfonte\Base62x\Encryption\Cipher;

class Bytes
{
    public static function iv($method)
    {
        return \openssl_cipher_iv_length($method);
    }
}