<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Encryption;

use Mfonte\Base62x\Exception\CryptException;

/**
 * Authenticated encryption (XChaCha20-Poly1305, via ext-sodium).
 *
 * Wire format: HEADER . salt . nonce . ciphertext+tag
 *
 *  - HEADER_BLAKE2B  ("[MFB62X.AEAD.1]"): the key is derived with keyed BLAKE2b. Fast: meant for high-entropy keys,
 *    e.g. a framework application key or 32 random bytes.
 *  - HEADER_ARGON2ID ("[MFB62X.AEAD.2]"): the key is derived with Argon2id (interactive limits). Slow on purpose
 *    (~64 MiB of memory per call): meant for human passwords.
 *
 * A fresh random salt and nonce are generated for every payload, and the header is authenticated as additional
 * data: any change to the payload makes decryption fail.
 *
 * @internal This class is not part of the public API: use Base62x::encrypt() / Base62x::decrypt()
 */
final class AuthenticatedCrypt
{
    public const HEADER_BLAKE2B = '[MFB62X.AEAD.1]';
    public const HEADER_ARGON2ID = '[MFB62X.AEAD.2]';

    private const SALT_BYTES = 16;
    private const KEY_BYTES = 32;
    private const NONCE_BYTES = 24;
    private const TAG_BYTES = 16;

    /**
     * Whether the payload was produced by self::encrypt().
     */
    public static function isEncrypted(string $payload): bool
    {
        return self::headerOf($payload) !== null;
    }

    /**
     * @throws CryptException
     */
    public static function encrypt(string $plaintext, string $key, bool $passwordHardening = false): string
    {
        self::assertAvailable();

        $header = $passwordHardening ? self::HEADER_ARGON2ID : self::HEADER_BLAKE2B;
        $salt = random_bytes(self::SALT_BYTES);
        $nonce = random_bytes(self::NONCE_BYTES);
        $subkey = self::deriveKey($key, $salt, $header);

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $header, $nonce, $subkey);
        sodium_memzero($subkey);

        return $header.$salt.$nonce.$ciphertext;
    }

    /**
     * @throws CryptException if the key is wrong, or the payload was tampered with
     */
    public static function decrypt(string $payload, string $key): string
    {
        self::assertAvailable();

        $header = self::headerOf($payload);
        $offset = $header !== null ? \strlen($header) : 0;
        if ($header === null || \strlen($payload) < $offset + self::SALT_BYTES + self::NONCE_BYTES + self::TAG_BYTES) {
            throw new CryptException('Cannot decrypt the payload: it is not a valid authenticated encryption payload');
        }

        $salt = substr($payload, $offset, self::SALT_BYTES);
        $nonce = substr($payload, $offset + self::SALT_BYTES, self::NONCE_BYTES);
        $ciphertext = substr($payload, $offset + self::SALT_BYTES + self::NONCE_BYTES);
        $subkey = self::deriveKey($key, $salt, $header);

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $header, $nonce, $subkey);
        sodium_memzero($subkey);

        if (!\is_string($plaintext)) {
            throw new CryptException('Cannot decrypt the payload: wrong key, or the payload was tampered with');
        }

        return $plaintext;
    }

    /**
     * @throws CryptException
     */
    public static function assertAvailable(): void
    {
        if (!\function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            throw new CryptException('authenticated encryption requires the sodium extension');
        }
    }

    private static function headerOf(string $payload): ?string
    {
        foreach ([self::HEADER_BLAKE2B, self::HEADER_ARGON2ID] as $header) {
            if (strncmp($payload, $header, \strlen($header)) === 0) {
                return $header;
            }
        }

        return null;
    }

    /**
     * @throws CryptException
     */
    private static function deriveKey(string $key, string $salt, string $header): string
    {
        if ($header === self::HEADER_ARGON2ID) {
            if (!\defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) {
                throw new CryptException('Argon2id is not supported by the installed libsodium');
            }

            return sodium_crypto_pwhash(
                self::KEY_BYTES,
                $key,
                $salt,
                \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
                \SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            );
        }

        // keyed BLAKE2b: the (hashed) user key is the MAC key, the random salt and the header are the message
        $masterKey = sodium_crypto_generichash($key, '', self::KEY_BYTES);
        $subkey = sodium_crypto_generichash($header.$salt, $masterKey, self::KEY_BYTES);
        sodium_memzero($masterKey);

        return $subkey;
    }
}
