<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Test\Unit;

use Mfonte\Base62x\Base62x;
use Mfonte\Base62x\Exception\CryptException;
use Mfonte\Base62x\Encryption\AuthenticatedCrypt;

/**
 * @requires extension sodium
 */
class AuthenticatedEncryptionTest extends TestCase
{
    private const KEY = 'a-long-random-application-key-0123456789';

    /**
     * @return iterable<string, array{string}>
     */
    public static function cipherProvider(): iterable
    {
        yield 'blake2b kdf' => [Base62x::CIPHER_XCHACHA20_POLY1305];
        yield 'argon2id kdf' => [Base62x::CIPHER_XCHACHA20_POLY1305_ARGON2ID];
    }

    /**
     * @dataProvider cipherProvider
     */
    public function testRoundTrip(string $cipher): void
    {
        foreach (['a', self::allBytes(), 'Caffè ☕'] as $payload) {
            $encoded = Base62x::encode($payload)->encrypt(self::KEY, $cipher)->get();
            self::assertSame($payload, Base62x::decode($encoded)->decrypt(self::KEY, $cipher)->get());
        }
    }

    /**
     * @dataProvider cipherProvider
     */
    public function testRoundTripWithCompressionAndArrays(string $cipher): void
    {
        $payload = ['route' => 'homepage', 'pagination' => ['currPage' => 1, 'maxPages' => 2]];

        foreach (['gzip', 'huffman'] as $algo) {
            $encoded = Base62x::encode($payload)->encrypt(self::KEY, $cipher)->compress($algo)->get();
            self::assertSame($payload, Base62x::decode($encoded)->decrypt(self::KEY)->get());
        }
    }

    public function testCipherIsAutoDetectedOnDecrypt(): void
    {
        $encoded = Base62x::encode('payload')->encrypt(self::KEY, Base62x::CIPHER_XCHACHA20_POLY1305)->get();

        // the default (OpenSSL) method passed to decrypt() is ignored for authenticated payloads
        self::assertSame('payload', Base62x::decode($encoded)->decrypt(self::KEY)->get());
        self::assertSame('payload', Base62x::decode($encoded)->decrypt(self::KEY, 'aes-256-cbc')->get());
    }

    public function testWrongKeyIsDetected(): void
    {
        $encoded = Base62x::encode('payload')->encrypt(self::KEY, Base62x::CIPHER_XCHACHA20_POLY1305)->get();

        $this->expectException(CryptException::class);
        Base62x::decode($encoded)->decrypt('another key')->get();
    }

    public function testDecodingWithoutKeyFails(): void
    {
        $encoded = Base62x::encode('payload')->encrypt(self::KEY, Base62x::CIPHER_XCHACHA20_POLY1305)->get();

        $this->expectException(CryptException::class);
        Base62x::decode($encoded)->get();
    }

    public function testEveryTamperedByteIsDetected(): void
    {
        $sealed = AuthenticatedCrypt::encrypt('a secret payload', self::KEY);
        $headerLength = \strlen(AuthenticatedCrypt::HEADER_BLAKE2B);

        for ($i = $headerLength; $i < \strlen($sealed); ++$i) {
            $tampered = $sealed;
            $tampered[$i] = \chr(\ord($tampered[$i]) ^ 0x01);
            try {
                AuthenticatedCrypt::decrypt($tampered, self::KEY);
                self::fail("Tampering byte {$i} was not detected");
            } catch (CryptException $e) {
                self::assertStringContainsString('tampered', $e->getMessage());
            }
        }
    }

    public function testTruncatedPayloadIsRejected(): void
    {
        $this->expectException(CryptException::class);

        AuthenticatedCrypt::decrypt(AuthenticatedCrypt::HEADER_BLAKE2B.'short', self::KEY);
    }

    public function testEveryPayloadUsesAFreshSaltAndNonce(): void
    {
        self::assertNotSame(
            Base62x::encode('payload')->encrypt(self::KEY, Base62x::CIPHER_XCHACHA20_POLY1305)->get(),
            Base62x::encode('payload')->encrypt(self::KEY, Base62x::CIPHER_XCHACHA20_POLY1305)->get()
        );
    }

    public function testCipherNameIsCaseInsensitive(): void
    {
        $encoded = Base62x::encode('payload')->encrypt(self::KEY, 'XChaCha20-Poly1305')->get();

        self::assertSame('payload', Base62x::decode($encoded)->decrypt(self::KEY)->get());
    }

    public function testLegacyPayloadsAreStillDecryptedWithOpenssl(): void
    {
        $encoded = Base62x::encode('payload')->encrypt(self::KEY, 'aes-256-cbc')->get();

        self::assertSame('payload', Base62x::decode($encoded)->decrypt(self::KEY, 'aes-256-cbc')->get());
    }
}
