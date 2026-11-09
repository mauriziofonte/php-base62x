<?php

declare(strict_types=1);

use Mfonte\Base62x\Base62x;

return [
    /*
    |--------------------------------------------------------------------------
    | Encryption key
    |--------------------------------------------------------------------------
    |
    | The key used by Base62x::encrypt() / Base62x::decrypt() (the facade helpers), by the "encrypted" cast option
    | and by the validation rule. Defaults to the application key.
    |
    */
    'key' => env('BASE62X_KEY', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Cipher
    |--------------------------------------------------------------------------
    |
    | Authenticated encryption via ext-sodium is the default. Any OpenSSL cipher method supported by
    | Base62x::encrypt() can be used as well, e.g. "aes-256-cbc".
    |
    */
    'cipher' => env('BASE62X_CIPHER', Base62x::CIPHER_XCHACHA20_POLY1305),

    /*
    |--------------------------------------------------------------------------
    | Compression
    |--------------------------------------------------------------------------
    |
    | The compression applied by the facade helpers and by the "compressed" cast option:
    | "gzip", "huffman", "bzip2", "zstd", "brotli", or null to disable it.
    |
    */
    'compression' => env('BASE62X_COMPRESSION', 'gzip'),
];
