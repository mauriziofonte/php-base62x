<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;
use Mfonte\Base62x\Encryption\Crypt;
use Mfonte\Base62x\Exception\CryptException;

class CryptTest extends TestCase
{
    /**
     * All the cipher methods of the local openssl installation that the library accepts.
     *
     * @return list<string>
     */
    public static function supportedCipherMethods(): array
    {
        $supported = [];
        foreach (openssl_get_cipher_methods() as $method) {
            try {
                $normalized = Crypt::assertSupportedMethod($method);
            } catch (CryptException $e) {
                continue; // rejected on purpose
            }

            // some methods are listed but not usable at runtime, e.g. legacy ciphers (Blowfish, CAST5, DES...)
            // when PHP is linked to OpenSSL 3 without the legacy provider
            $ivLength = openssl_cipher_iv_length($normalized);
            if (\is_int($ivLength) && @openssl_encrypt('probe', $normalized, str_repeat('k', 32), 0, str_repeat("\0", $ivLength)) !== false) {
                $supported[] = $normalized;
            }
        }

        return array_values(array_unique($supported));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedCipherProvider(): iterable
    {
        yield 'unknown' => ['not-a-cipher'];
        yield 'aes ecb' => ['aes-128-ecb'];
        yield 'aes ecb uppercase' => ['AES-256-ECB'];
        yield 'aes gcm (aead)' => ['aes-256-gcm'];
        yield 'aes ccm (aead)' => ['aes-128-ccm'];
        yield 'chacha20-poly1305 (aead)' => ['chacha20-poly1305'];
        yield 'key wrap' => ['id-aes128-wrap'];
    }

    /**
     * @dataProvider rejectedCipherProvider
     */
    public function testInsecureOrUnsupportedCiphersAreRejected(string $cipher): void
    {
        $this->expectException(CryptException::class);

        Base62x::encode('payload')->encrypt('key', $cipher);
    }

    public function testCipherNameIsCaseInsensitive(): void
    {
        $encoded = Base62x::encode('payload')->encrypt('key', 'AES-256-CBC')->get();

        self::assertSame('payload', Base62x::decode($encoded)->decrypt('key', 'aes-256-cbc')->get());
    }

    public function testEmptyKeyIsRejected(): void
    {
        $this->expectException(CryptException::class);

        Base62x::encode('payload')->encrypt('');
    }

    public function testKeyZeroActuallyEncrypts(): void
    {
        $encoded = Base62x::encode('secret payload')->encrypt('0')->get();

        self::assertNotSame('secret payload', Base62x::decode($encoded)->get());
        self::assertSame('secret payload', Base62x::decode($encoded)->decrypt('0')->get());
    }

    public function testEncryptionUsesARandomIv(): void
    {
        self::assertNotSame(
            Base62x::encode('payload')->encrypt('key')->get(),
            Base62x::encode('payload')->encrypt('key')->get()
        );
    }

    public function testEncryptedOutputDoesNotLeakThePlaintext(): void
    {
        $encoded = Base62x::encode('a very recognizable plaintext')->encrypt('key')->get();

        self::assertStringNotContainsString('recognizable', Base62x::decode($encoded)->get());
    }

    public function testDecryptingANonEncryptedPayloadFails(): void
    {
        $this->expectException(CryptException::class);

        Base62x::decode(Base62x::encode('x')->get())->decrypt('key', 'aes-256-cbc')->get();
    }

    public function testCryptRequiresAKey(): void
    {
        $this->expectException(CryptException::class);

        new Crypt(['method' => 'aes-128-ctr']);
    }
}
