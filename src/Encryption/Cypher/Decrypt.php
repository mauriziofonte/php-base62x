<?php

namespace Mfonte\Base62x\Encryption\Cipher;

class Decrypt
{
    public static function token($data, $method, $key)
    {
        $iv_strlen = 2 * Bytes::iv($method);
        if (\preg_match('/^(.{'.$iv_strlen.'})(.+)$/', $data, $regs)) {
            list(, $iv, $crypted_string) = $regs;
            if (\ctype_xdigit($iv) && \mb_strlen($iv) % 2 == 0) {
                return \openssl_decrypt($crypted_string, $method, $key, 0, \hex2bin($iv));
            }
        }

        return false; // failed to decrypt
    }
}