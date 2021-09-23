<?php

namespace Mfonte\Base62x\Compression\Gzip;

use Mfonte\Base62x\Exception\CompressionException;

class GzipCompression
{
    public static function encode($data, $encoding = null)
    {
        if (!\function_exists('gzencode')) {
            throw new CompressionException('gzip', 'Cannot use Gzip as compression algorithm: the current PHP installation does not support this module.');
        }

        $encoded = false;
        switch ($encoding) {
            case 'zlib':
                $encoded = @\gzcompress($data, 9);
            break;
            case 'deflate':
                $encoded = @\gzdeflate($data, 9);
            break;
            case 'gzip':
                $encoded = @\gzencode($data, 9);
            break;
            default:
                $encoded = @\gzencode($data, 9);
        }

        if ($encoded === false) {
            throw new CompressionException('gzip', 'The gz compression function returned a false state while compressing the input data.');
        }

        return $encoded;
    }

    public static function decode($data, $encoding = null)
    {
        if (!\function_exists('gzdecode')) {
            throw new CompressionException('gzip', 'Cannot use Gzip as compression algorithm: the current PHP installation does not support this module.');
        }

        $fn = 'gzdecode';
        $decoded = false;
        switch ($encoding) {
            case 'zlib':
                $fn = 'gzuncompress';
                $decoded = @\gzuncompress($data);
                if (empty($decoded)) {
                    $decoded = @\gzdecode($data);
                }
            break;
            case 'deflate':
                $fn = 'gzinflate';
                $decoded = @\gzinflate($data);
            break;
            case 'gzip':
                $decoded = @\gzdecode($data);
            break;
            default:
                $decoded = @\gzdecode($data);
        }

        if ($decoded === false) {
            throw new CompressionException('gzip', "The {$fn}() function returned a false state while uncompressing the input data.");
        }

        return $decoded;
    }
}