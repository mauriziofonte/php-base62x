<?php

namespace Mfonte\Base62x;

use Exception;
use Mfonte\Base62x\Exception\InvalidParam;
use Mfonte\Base62x\Exception\CryptException;
use Mfonte\Base62x\Exception\DecodeException;
use Mfonte\Base62x\Exception\EncodeException;
use Mfonte\Base62x\Encoding\Base62x as Encoder;
use Mfonte\Base62x\Encryption\Crypt as Crypter;
use Mfonte\Base62x\Compression\Gzip\GzipCompression as GzipCompressor;
use Mfonte\Base62x\Compression\Huffman\HuffmanCoding as HuffmanCompressor;

class Base62x
{
    const MODE_ENCODE = 1;
    const MODE_DECODE = 2;

    protected $_validCompressionAlgorithms = ['gzip' => ['zlib', 'deflate', 'gzip'], 'huffman'];

    /**
     * The mode: encode or decode.
     *
     * @var int
     */
    protected $mode;

    /**
     * The payload to be encoded or decoded.
     *
     * @var mixed
     */
    protected $payload;

    /**
     * Wheter the payload needs to be compressed prior of encoding. Defaults to null.
     *
     * @var bool
     */
    protected $compressAlgorithm = null;

    /**
     * The compression mode related to the compression algo. Defaults to null.
     *
     * @var bool
     */
    protected $compressEncoding = null;

    /**
     * The encryption method (algorithm) to be used in case of password-protected encoding.
     * This variable *must* be a valid method supported in openssl_get_cipher_methods().
     *
     * @var string
     */
    protected $cryptMethod;

    /**
     * The encrypt/decrypt key (password) to be used to protect/unprotect the encoding.
     *
     * @var mixed
     */
    protected $cryptKey;

    /**
     * Wheter the payload needs to be decompressed after the decoding. Defaults to false.
     *
     * @var bool
     */
    protected $decompressAlgorithm = null;

    /**
     * The compression mode related to the compression algo. Defaults to null.
     *
     * @var bool
     */
    protected $decompressEncoding = null;

    /**
     * @param mixed $payload
     *
     * @return \Mfonte\Base62x\Base62x
     */
    public static function encode($payload): self
    {
        return new self(self::MODE_ENCODE, $payload);
    }

    /**
     * @param mixed $payload
     *
     * @return \Mfonte\Base62x\Base62x
     */
    public static function decode($payload): self
    {
        return new self(self::MODE_DECODE, $payload);
    }

    public function __construct($mode, $payload)
    {
        $this->mode = $mode;

        if (empty($payload)) {
            throw new InvalidParam('payload', __FUNCTION__, __CLASS__, 'The payload cannot be empty');
        } elseif (\is_resource($payload)) {
            throw new InvalidParam('payload', __FUNCTION__, __CLASS__, 'The payload cannot be a resource');
        } elseif (\is_object($payload)) {
            throw new InvalidParam('payload', __FUNCTION__, __CLASS__, 'The payload cannot be an object');
        } elseif (\is_array($payload)) {
            // if the payload is an array, perform here and now the translation to a serialized string
            $payload = \serialize($payload);
        }

        $this->payload = $payload;
    }

    /**
     * Sets the compression type and encoding.
     *
     * @param string $algo     A valid compression algorithm as seen on $_validCompressionAlgorithms
     * @param string $encoding A valid compression encoding as seen on $_validCompressionAlgorithms
     *
     * @return \Mfonte\Base62x\Base62x
     */
    public function compress($algo = 'gzip', $encoding = 'zlib'): self
    {
        if (!\array_key_exists($algo, $this->_validCompressionAlgorithms)) {
            throw new InvalidParam('algo', __FUNCTION__, __CLASS__);
        }
        if (
            \is_array($this->_validCompressionAlgorithms[$algo]) &&
            !\in_array($encoding, $this->_validCompressionAlgorithms[$algo], true)
        ) {
            throw new InvalidParam('encoding', __FUNCTION__, __CLASS__);
        } elseif (!\is_array($this->_validCompressionAlgorithms[$algo])) {
            $encoding = null;
        }

        $this->compressAlgorithm = $algo;
        $this->compressEncoding = $encoding;

        return $this;
    }

    /**
     * As the decompression is done automagically via the "magic string" at the beginning of the
     * encoded payload, this method is pointless.
     * It is present only as a reference.
     *
     * @return \Mfonte\Base62x\Base62x
     */
    public function decompress(): self
    {
        return $this;
    }

    /**
     * Sets the encryption key (password) and method (algorithm).
     *
     * @param string $key    A password for your encoded base62x output string
     * @param string $method A valid openssl cypher method as supported in your environment (openssl_get_cipher_methods)
     *
     * @return \Mfonte\Base62x\Base62x
     */
    public function encrypt(string $key, string $method = 'aes-128-ctr'): self
    {
        if (!\function_exists('openssl_get_cipher_methods')) {
            throw new CryptException('openssl_get_cipher_methods unsupported in your PHP installation');
        }
        if (!\in_array(\mb_strtolower($method), \openssl_get_cipher_methods(), true)) {
            throw new CryptException('Encryption method "'.$method.'" is either unsupported in your PHP installation or not a valid encryption algorithm.');
        }

        $this->cryptMethod = \mb_strtolower($method);
        $this->cryptPassword = $key;

        return $this;
    }

    /**
     * Sets the encryption key (password) and method (algorithm).
     *
     * @see self::encrypt
     */
    public function decrypt(string $key, string $method = 'aes-128-ctr'): self
    {
        return $this->encrypt($key, $method);
    }

    /**
     * Gets the encoded or decoded mixed variable originally passed as $payload to instance.
     *
     * @return mixed
     */
    public function get()
    {
        switch ($this->mode) {
            case self::MODE_ENCODE:
                return $this->_encode($this->payload);
            break;

            case self::MODE_DECODE:
                $decoded = $this->_decode($this->payload);

                // decoded payload can be a serialized array: if so, we return the original representation
                if ($this->_isSerializedString($decoded) && ($unserialized = @\unserialize($decoded)) !== false) {
                    return $unserialized;
                }

                return $decoded;
            break;
        }
    }

    /**
     * Performs the actual Base62x encoding.
     */
    private function _encode(string $payload): string
    {
        if ($this->cryptKey && $this->cryptMethod) {
            $payload = $this->_performEncryption($payload);
        }
        if ($this->compressAlgorithm) {
            $payload = $this->_performCompress($payload);
        }

        $encoded = Encoder::encode($payload);
        if (empty($encoded)) {
            throw new EncodeException();
        }

        return $encoded;
    }

    /**
     * Performs the actual Base62x decoding.
     */
    private function _decode(string $payload): string
    {
        $decoded = Encoder::decode($payload);
        if (empty($decoded)) {
            throw new DecodeException();
        }

        // remove the magic string for Compression
        $data = $this->_getCompressionFootprintAndSanitizePayload($decoded);

        if ($data['compression_algo']) {
            $decoded = $this->_performUncompress($data['payload'], $data['compression_algo'], $data['compression_encoding']);
        }

        // eventually perform decryption
        if ($this->cryptKey && $this->cryptMethod) {
            $decoded = $this->_performDecryption($decoded);
        }

        return $decoded;
    }

    /**
     * Performs the actual compress before chaining it into the Base62x encoder.
     *
     * @param mixed $payload
     */
    private function _performCompress(string $payload): string
    {
        $compressed = null;
        switch ($this->compressAlgorithm) {
            case 'gzip':
                $compressed = GzipCompressor::encode($payload, $this->compressEncoding);
            break;
            case 'huffman':
                $compressed = HuffmanCompressor::encode($payload, HuffmanCompressor::createCodeTree($payload));
            break;
        }

        if (empty($compressed)) {
            throw new EncodeException();
        }

        return $this->_createCompressionFootprint().$compressed;
    }

    /**
     * Decompresses the payload, that was prior compressed using one of the available compression types.
     */
    private function _performUncompress(string $payload, string $compression_algo, ?string $compression_encoding): string
    {
        switch ($compression_algo) {
            case 'gzip':
                $payload = GzipCompressor::decode($payload, $compression_encoding);
            break;
            case 'huffman':
                $payload = HuffmanCompressor::decode($payload);
            break;
        }

        return $payload;
    }

    /**
     * Performs the actual encryption before chaining it into the Base62x encoder.
     *
     * @param mixed $payload
     */
    private function _performEncryption(string $payload): ?string
    {
        try {
            $crypt = new Crypter([
                'key' => $this->cryptKey,
                'method' => $this->cryptMethod,
            ]);

            return $crypt->cipher($payload)->encrypt();
        } catch (Exception $ex) {
            throw new CryptException('Cannot encrypt the payload: '.$ex->getMessage());
        }

        return null;
    }

    /**
     * Decrypts the payload, that was prior encrypted using the on-board encrypter.
     */
    private function _performDecryption(string $payload): string
    {
        if (empty($this->cryptKey)) {
            throw new CryptException('Cannot decrypt the payload without a valid cryptKey');
        }
        if (empty($this->cryptKey)) {
            throw new CryptException('Cannot decrypt the payload without a valid cryptMethod');
        }

        try {
            $crypt = new Crypter([
                'key' => $this->cryptKey,
                'method' => $this->cryptMethod,
            ]);

            return $crypt->cipher($payload)->decrypt();
        } catch (Exception $ex) {
            throw new CryptException('Cannot decrypt the payload: '.$ex->getMessage());
        }

        return null;
    }

    /**
     * Prepares a "magic string" that will be appendend at beginning of the compressed payload,
     * prior of chaining it into the Base62x encoder.
     * Doing so, the decode method will automagically uncompress the encoded payload, so the subsequent "decode"
     * can understand which compression algo+encoding was originally used.
     */
    private function _createCompressionFootprint(): string
    {
        return '[MFB62X.COMPRESS.'.\base64_encode(\implode(',', [$this->compressAlgorithm, $this->compressEncoding])).']';
    }

    /**
     * Gets the decoded Base26x string, and checks if it needs decompression,
     * by analyzing its "compression footprint" placed at the very beginning of the payload.
     */
    private function _getCompressionFootprintAndSanitizePayload(string $payload): array
    {
        $compression_algo = $compression_encoding = null;
        if (\preg_match('/^\[MFB62X\.COMPRESS\.([A-Za-z0-9+\/]+={0,2})\]/', $payload, $match)) {
            list($compression_algo, $compression_encoding) = \explode(',', \base64_decode($match[1], true));
            $payload = \preg_replace('/^'.\preg_quote($match[0]).'/', '', $payload, 1);
        }

        // some sanity checks to avoid tampering with the payload and cause bad behaviour or worse
        if (!empty($compression_algo) && !\array_key_exists($compression_algo, $this->_validCompressionAlgorithms)) {
            throw new DecodeException();
        }
        if (
            !empty($compression_algo) &&
            \array_key_exists($compression_algo, $this->_validCompressionAlgorithms) &&
            !empty($compression_encoding) &&
            !\in_array($compression_encoding, $this->_validCompressionAlgorithms[$compression_algo], true)
        ) {
            throw new DecodeException();
        }

        return [
            'payload' => $payload,
            'compression_algo' => $compression_algo,
            'compression_encoding' => (\mb_strlen($compression_encoding) > 0) ? $compression_encoding : null,
        ];
    }

    /**
     * Checks whether the $data argument is a serialized string, i.e. an array serialized with native PHP's serialize().
     *
     * @param mixed $data   (should always be a string)
     * @param bool  $strict Whether to perform a strict analysis or not
     */
    private function _isSerializedString($data, $strict = true): bool
    {
        // If it isn't a string, it isn't serialized.
        if (!\is_string($data)) {
            return false;
        }
        $data = \trim($data);
        if ($data == 'N;') {
            return true;
        }
        if (\mb_strlen($data) < 4) {
            return false;
        }
        if ($data[1] !== ':') {
            return false;
        }
        if ($strict) {
            $lastc = \mb_substr($data, -1);
            if ($lastc !== ';' && $lastc !== '}') {
                return false;
            }
        } else {
            $semicolon = \mb_strpos($data, ';');
            $brace = \mb_strpos($data, '}');
            // Either ; or } must exist.
            if ($semicolon === false && $brace === false) {
                return false;
            }
            // But neither must be in the first X characters.
            if ($semicolon !== false && $semicolon < 3) {
                return false;
            }
            if ($brace !== false && $brace < 4) {
                return false;
            }
        }
        $token = $data[0];
        switch ($token) {
            case 's':
                if ($strict) {
                    if (\mb_substr($data, -2, 1) !== '"') {
                        return false;
                    }
                } elseif (\mb_strpos($data, '"') === false) {
                    return false;
                }
                // Or else fall through.
                // no break
            case 'a':
            case 'O':
                return (bool) \preg_match("/^{$token}:[0-9]+:/s", $data);
            case 'b':
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';

                return (bool) \preg_match("/^{$token}:[0-9.E+-]+;$end/", $data);
        }

        return false;
    }
}