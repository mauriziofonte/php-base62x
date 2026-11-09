<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Encryption;

use Mfonte\Base62x\Exception\CryptException;
use Mfonte\Base62x\Encryption\Cypher\Decrypt;
use Mfonte\Base62x\Encryption\Cypher\Encrypt;

/**
 * Symmetric encryption helper built on top of ext-openssl.
 *
 * @internal This class is not part of the public API: use Base62x::encrypt() / Base62x::decrypt()
 */
class Crypt
{
    /**
     * Default cipher method if none supplied. See: https://www.php.net/openssl_get_cipher_methods.
     *
     * @var string
     */
    protected $method = 'aes-128-ctr';

    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $data = '';

    /**
     * @param array{key?: string|null, method?: string|null} $options
     *
     * @throws CryptException if the key is missing or empty, or the cipher method is not supported
     */
    public function __construct(array $options = [])
    {
        $key = isset($options['key']) ? (string) $options['key'] : '';
        if ($key === '') {
            throw new CryptException('an encryption key is required');
        }

        // printable (ASCII) keys are hashed to a 256-bit binary key, other keys are used as-is.
        // NOTE: this derivation is kept as-is for backward compatibility with already encrypted payloads.
        $this->key = ctype_print($key) ? (string) openssl_digest($key, 'SHA256', true) : $key;

        if (isset($options['method']) && $options['method'] !== '') {
            $this->method = self::assertSupportedMethod((string) $options['method']);
        }
    }

    /**
     * Checks that the given cipher method is available and usable by this library,
     * and returns its normalized (lowercase) name.
     *
     * Rejected methods:
     *  - methods unknown to the local openssl installation;
     *  - methods without an IV (e.g. any ECB mode), which are deterministic and therefore insecure;
     *  - AEAD methods (GCM, CCM, OCB, Poly1305, SIV), which need an authentication tag that is not handled here;
     *  - key-wrap methods, which are not meant for arbitrary data;
     *  - stitched cipher+HMAC methods (e.g. aes-128-cbc-hmac-sha1), which only work inside TLS.
     *
     * @throws CryptException
     */
    public static function assertSupportedMethod(string $method): string
    {
        $normalized = strtolower($method);

        if (!\in_array($normalized, array_map('strtolower', openssl_get_cipher_methods()), true)) {
            throw new CryptException('Encryption cypher method "'.$method.'" is either unsupported in your PHP installation or not a valid encryption algorithm.');
        }
        if (preg_match('/(gcm|ccm|ocb|poly1305|siv|aead)/', $normalized) === 1) {
            throw new CryptException('Encryption cypher method "'.$method.'" is not supported. AEAD modes are not supported.');
        }
        if (strpos($normalized, 'wrap') !== false) {
            throw new CryptException('Encryption cypher method "'.$method.'" is not supported. Key-wrap modes are not supported.');
        }
        if (strpos($normalized, 'hmac') !== false) {
            throw new CryptException('Encryption cypher method "'.$method.'" is not supported. Stitched cipher+HMAC modes are TLS-only.');
        }
        $ivLength = @openssl_cipher_iv_length($normalized);
        if ($ivLength === false || $ivLength === 0 || strpos($normalized, 'ecb') !== false) {
            throw new CryptException('Encryption cypher method "'.$method.'" is not supported. ECB (or IV-less) modes are not secure.');
        }

        return $normalized;
    }

    /**
     * Sets the data to be encrypted or decrypted.
     */
    public function cipher(string $data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Encrypts the data: the result is the hex-encoded IV followed by the base64 cipher text.
     *
     * @throws CryptException
     */
    public function encrypt(): string
    {
        return Encrypt::token(
            $this->data,
            $this->method,
            $this->key
        );
    }

    /**
     * Decrypts the data, or returns false on failure.
     *
     * @return string|false
     */
    public function decrypt()
    {
        return Decrypt::token(
            $this->data,
            $this->method,
            $this->key
        );
    }
}
