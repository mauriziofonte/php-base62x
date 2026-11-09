<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Exception;

class CompressionException extends \RuntimeException implements Base62xException
{
    public const REASON = 'Cannot Compress';
    public const CODE = 0;

    public function __construct(string $compressionAlgo, string $reason, ?\Throwable $previous = null)
    {
        parent::__construct('Exception in compression algorithm "'.$compressionAlgo.'": '.$reason, static::CODE, $previous);
    }
}
