<?php

namespace Mfonte\Base62x\Exception;

class CompressionException extends \RuntimeException
{
    const REASON = 'Cannot Compress';
    const CODE = 0;

    public function __construct($compressionAlgo, $reason)
    {
        parent::__construct('Exception in compression algorithm "'.$compressionAlgo."\ : ".$reason, static::CODE);
    }
}
