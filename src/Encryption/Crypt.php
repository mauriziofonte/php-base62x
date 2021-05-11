<?php

namespace Mfonte\Base62x\Encryption;

use Mfonte\Base62x\Exception\CryptException;
use Mfonte\Base62x\Encryption\Cipher\Decrypt;
use Mfonte\Base62x\Encryption\Cipher\Encrypt;

class Crypt
{
    protected $method = 'aes-128-ctr'; // default cipher method if none supplied. see: http://php.net/openssl_get_cipher_methods for more.

    private $key;

    private $data;

    public function __construct($options = [])
    {
        //Set default encryption key if none supplied
        $key = isset($options['key']) ? $options['key'] : \php_uname();

        $method = isset($options['method']) ? $options['method'] : false;

        // convert ASCII keys to binary format
        $this->key = \ctype_print($key) ? \openssl_digest($key, 'SHA256', true) : $key;

        if ($method) {
            if (\in_array(\mb_strtolower($method), \openssl_get_cipher_methods(), true)) {
                $this->method = $method;
            } else {
                throw new CryptException("unrecognised cipher method: {$method}");
            }
        }
    }

    public function cipher($data)
    {
        $this->data = $data;

        return $this;
    }

    public function encrypt()
    {
        return Encrypt::token(
            $this->data,
            $this->method,
            $this->key
        );
    }

    public function decrypt()
    {
        return Decrypt::token(
            $this->data,
            $this->method,
            $this->key
        );
    }
}