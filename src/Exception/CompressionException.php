<?php

namespace Mfonte\Base62x\Exception;

class CompressionException extends \RuntimeException
{
    public const REASON = 'Cannot Compress';
    public const CODE = 0;

    public function __construct($compressionAlgo, $reason)
    {
        parent::__construct('Exception in compression algorithm "'.$compressionAlgo."\ : ".$reason, static::CODE);
    }
}
